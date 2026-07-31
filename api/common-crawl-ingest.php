<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';use Ogpn\Bot\{Bootstrap,Auth,Domain};
$pdo=Bootstrap::pdo();$w=Auth::worker($pdo,'discovery.ingest');$raw=file_get_contents('php://input');
$max=(int)(Bootstrap::defaults()['security']['max_body_bytes']??2097152);if(strlen($raw)>$max)Bootstrap::json(['error'=>'payload_too_large'],413);
$b=json_decode($raw,true);if(!is_array($b)||!isset($b['job_id'],$b['urls'])||!is_array($b['urls']))Bootstrap::json(['error'=>'invalid_payload'],422);
$domains=[];foreach($b['urls'] as $url){if(is_string($url)&&($d=Domain::fromUrl($url)))$domains[$d]=Domain::tld($d);} $pdo->beginTransaction();
$added=0;$chunks=array_chunk($domains,500,true);
foreach($chunks as $chunk){foreach($chunk as $d=>$t){$ins=$pdo->prepare("INSERT INTO v2_domain_queue(domain,tld,last_seen_at) VALUES(?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE last_seen_at=UTC_TIMESTAMP(),common_crawl_seen_count=common_crawl_seen_count+1");$ins->execute([$d,$t]);if($ins->rowCount()===1)$added++;}}
$u=$pdo->prepare("UPDATE v2_common_crawl_jobs SET status='completed',reserved_by=NULL,reserved_until=NULL,rows_received=?,root_domains_found=?,domains_added=?,duration_ms=? WHERE id=? AND reserved_by=?");$u->execute([(int)($b['rows_received']??count($b['urls'])),count($domains),$added,(int)($b['duration_ms']??0),(int)$b['job_id'],$w['worker_id']]);
if($u->rowCount()!==1){$pdo->rollBack();Bootstrap::json(['error'=>'job_not_owned'],409);} $pdo->commit();Bootstrap::json(['ok'=>true,'root_domains'=>count($domains),'domains_added'=>$added]);
