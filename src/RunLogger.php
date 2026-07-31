<?php
declare(strict_types=1);
namespace Ogpn\Bot;
use PDO;
final class RunLogger {
 public static function start(PDO $pdo,string $runId,array $worker,array $plan): void {$s=$pdo->prepare('INSERT INTO v2_worker_runs(run_id,worker_id,action,reason,capacity,started_at,details) VALUES(?,?,?,?,?,UTC_TIMESTAMP(),?)');$s->execute([$runId,$worker['worker_id'],$plan['action'],$plan['reason'],$worker['capacity'],json_encode(['mode'=>$plan['mode'],'metrics'=>$plan['metrics']],JSON_UNESCAPED_SLASHES)]);}
 public static function finish(PDO $pdo,string $runId,int $ccPages,int $added,int $scanned,int $errors,array $details=[]): void {$s=$pdo->prepare('UPDATE v2_worker_runs SET finished_at=UTC_TIMESTAMP(),duration_ms=TIMESTAMPDIFF(MICROSECOND,started_at,UTC_TIMESTAMP(6))/1000,cc_pages=?,domains_added=?,domains_scanned=?,error_count=?,details=? WHERE run_id=?');$s->execute([$ccPages,$added,$scanned,$errors,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$runId]);}
}
