<?php
declare(strict_types=1);
namespace Ogpn\Bot;
use PDO;
final class Planner {
 public static function metrics(PDO $pdo): array {
  return [
   'pending_new'=>(int)$pdo->query("SELECT COUNT(*) FROM domain_queue WHERE last_scanned_at IS NULL AND (reserved_until IS NULL OR reserved_until<UTC_TIMESTAMP())")->fetchColumn(),
   'pending_rescan'=>(int)$pdo->query("SELECT COUNT(*) FROM domain_queue WHERE last_scanned_at IS NOT NULL AND next_scan_at<=UTC_TIMESTAMP() AND (reserved_until IS NULL OR reserved_until<UTC_TIMESTAMP())")->fetchColumn(),
   'cc_pending'=>(int)$pdo->query("SELECT COUNT(*) FROM common_crawl_jobs WHERE status='pending'")->fetchColumn(),
  ];
 }
 public static function plan(PDO $pdo,array $worker): array {
  $d=Bootstrap::defaults();$m=self::metrics($pdo);$high=(int)Settings::get($pdo,'queue.high_threshold',$d['queue']['high_threshold']);$low=(int)Settings::get($pdo,'queue.low_threshold',$d['queue']['low_threshold']);
  $mode=(string)Settings::get($pdo,'orchestrator.mode','discovery');
  if($mode==='discovery' && $m['pending_new']>$high)$mode='scan_relief';
  elseif($mode==='scan_relief' && $m['pending_new']<$low)$mode='discovery';
  Settings::set($pdo,'orchestrator.mode',$mode);
  $capacity=(string)$worker['capacity'];$limits=$d['capacities'][$capacity]??$d['capacities']['low'];$preferred=(string)$worker['preferred_role'];
  $action='idle';$reason='no_due_work';
  if($preferred==='discovery') { if($mode==='scan_relief' && $capacity!=='low'){$action='scan';$reason='queue_above_high_threshold';} elseif($mode==='scan_relief' && $capacity==='low' && $m['pending_new']>$high+10000){$action='scan';$reason='critical_relief';} else {$action='discovery';$reason='preferred_role';} }
  else { if($m['pending_new']+$m['pending_rescan']>0){$action='scan';$reason='scan_work_available';} elseif($m['cc_pending']>0){$action='discovery';$reason='scan_queue_empty';} }
  return ['action'=>$action,'reason'=>$reason,'mode'=>$mode,'metrics'=>$m,'limits'=>$limits,'worker_id'=>$worker['worker_id'],'capacity'=>$capacity];
 }
}
