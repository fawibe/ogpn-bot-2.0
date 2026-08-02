<?php
declare(strict_types=1);
namespace Ogpn\Bot;
use PDO;
final class Planner {
 public static function metrics(PDO $pdo): array {
  return [
   'pending_new'=>(int)$pdo->query("SELECT COUNT(*) FROM v2_domain_queue WHERE last_scanned_at IS NULL AND (reserved_until IS NULL OR reserved_until<UTC_TIMESTAMP())")->fetchColumn(),
   'pending_rescan'=>(int)$pdo->query("SELECT COUNT(*) FROM v2_domain_queue WHERE last_scanned_at IS NOT NULL AND next_scan_at<=UTC_TIMESTAMP() AND (reserved_until IS NULL OR reserved_until<UTC_TIMESTAMP())")->fetchColumn(),
   'cc_pending'=>(int)$pdo->query("SELECT COUNT(*) FROM v2_common_crawl_jobs WHERE status='pending' OR (status='reserved' AND reserved_until<UTC_TIMESTAMP())")->fetchColumn(),
   'cc_failed'=>(int)$pdo->query("SELECT COUNT(*) FROM v2_common_crawl_jobs WHERE status IN ('failed','dead_letter')")->fetchColumn(),
   'queue_total'=>(int)$pdo->query('SELECT COUNT(*) FROM v2_domain_queue')->fetchColumn(),
  ];
 }
 public static function plan(PDO $pdo,array $worker): array {
  $d=Bootstrap::defaults();$m=self::metrics($pdo);
  if(Settings::bool($pdo,'features.emergency_stop',false))return self::response($worker,$m,'idle','emergency_stop','stopped',$d);
  $discovery=Settings::bool($pdo,'features.discovery_enabled',true);$scan=Settings::bool($pdo,'features.scan_enabled',true);
  $high=(int)Settings::get($pdo,'queue.high_threshold',$d['queue']['high_threshold']);$low=(int)Settings::get($pdo,'queue.low_threshold',$d['queue']['low_threshold']);
  if($low>=$high){$low=max(0,$high-1000);Settings::set($pdo,'queue.low_threshold',$low);}
  $mode=(string)Settings::get($pdo,'orchestrator.mode','discovery');
  if($mode==='discovery' && $m['pending_new']>$high)$mode='scan_relief';
  elseif($mode==='scan_relief' && $m['pending_new']<$low)$mode='discovery';
  Settings::set($pdo,'orchestrator.mode',$mode);
  $capacity=(string)$worker['capacity'];$preferred=(string)$worker['preferred_role'];$action='idle';$reason='no_due_work';
  if($preferred==='discovery'){
   if($scan&&$mode==='scan_relief'&&($capacity!=='low'||$m['pending_new']>$high+10000)){$action='scan';$reason=$capacity==='low'?'critical_relief':'queue_above_high_threshold';}
   elseif($discovery){$action='discovery';$reason='preferred_role';}
  } elseif($preferred==='scan'){
   if($scan&&$m['pending_new']+$m['pending_rescan']>0){$action='scan';$reason='scan_work_available';}
   elseif($discovery&&$m['pending_new']<$low){$action='discovery';$reason='queue_below_low_threshold';}
  } elseif($preferred==='auto'){
   if($scan&&$m['pending_new']>$high){$action='scan';$reason='auto_relief';}elseif($discovery){$action='discovery';$reason='auto_discovery';}elseif($scan&&$m['pending_rescan']>0){$action='scan';$reason='auto_rescan';}
  }
  return self::response($worker,$m,$action,$reason,$mode,$d);
 }
 private static function response(array $worker,array $m,string $action,string $reason,string $mode,array $d): array {
  $capacity=(string)$worker['capacity'];$limits=$d['capacities'][$capacity]??$d['capacities']['low'];
  if(isset($worker['max_concurrency_override'])&&$worker['max_concurrency_override']!==null){$limits['max_concurrency']=(int)$worker['max_concurrency_override'];}
  return ['action'=>$action,'reason'=>$reason,'mode'=>$mode,'metrics'=>$m,'limits'=>$limits,'worker_id'=>$worker['worker_id'],'capacity'=>$capacity,'server_time'=>gmdate('c')];
 }
}
