<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';use Ogpn\Bot\{Bootstrap,Auth};
$pdo=Bootstrap::pdo();$w=Auth::worker($pdo,'scan.fail');$b=json_decode(file_get_contents('php://input'),true);
if(!is_array($b)||!is_array($b['domains']??null))Bootstrap::json(['error'=>'invalid_payload'],422);
$s=$pdo->prepare("UPDATE v2_domain_queue SET reserved_by=NULL,reserved_until=NULL,scan_attempts=scan_attempts+1,last_scan_status='worker_error',next_scan_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE domain=? AND reserved_by=?");foreach($b['domains'] as $d)if(is_string($d))$s->execute([$d,$w['worker_id']]);Bootstrap::json(['ok'=>true]);
