<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Ogpn\Bot\{Auth, Bootstrap};

$pdo=Bootstrap::pdo();$worker=Auth::worker($pdo,'discovery.fail');
$body=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($body)||!isset($body['job_id'])) Bootstrap::json(['error'=>'invalid_payload'],422);
$error=substr((string)($body['error']??'unknown'),0,2000);
$type=match(true){
 str_contains(strtolower($error),'empty reply')=>'empty_reply',
 str_contains($error,'429')=>'rate_limited',
 str_contains($error,'502'),str_contains($error,'503'),str_contains($error,'504')=>'upstream_unavailable',
 str_contains(strtolower($error),'timeout')=>'timeout',
 default=>'network_or_http',
};
$stmt=$pdo->prepare('SELECT attempts FROM v2_common_crawl_jobs WHERE id=? AND reserved_by=?');
$stmt->execute([(int)$body['job_id'],$worker['worker_id']]);$attempts=(int)$stmt->fetchColumn();
$delay=match(true){$attempts<=1=>300,$attempts===2=>900,$attempts===3=>3600,$attempts===4=>21600,default=>86400};
$status=$attempts>=5?'dead_letter':'failed';
$update=$pdo->prepare("UPDATE v2_common_crawl_jobs SET status=?,reserved_by=NULL,reserved_until=NULL,last_error=?,error_type=?,next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND) WHERE id=? AND reserved_by=?");
$update->execute([$status,$error,$type,$delay,(int)$body['job_id'],$worker['worker_id']]);
Bootstrap::json(['ok'=>true,'status'=>$status,'retry_after_seconds'=>$delay,'error_type'=>$type]);
