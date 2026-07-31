<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';use Ogpn\Bot\{Bootstrap,Auth,Settings};
use Ogpn\Bot\Engine\TldRegistry;
$pdo=Bootstrap::pdo();$w=Auth::worker($pdo,'scan.claim');$body=json_decode(file_get_contents('php://input'),true)?:[];$limit=max(1,min(100,(int)($body['limit']??25)));
if(!Settings::bool($pdo,'features.scan_enabled',true)||Settings::bool($pdo,'features.emergency_stop',false))Bootstrap::json(['domains'=>[]]);
$defaults=Bootstrap::defaults();
$priority=(array)($defaults['queue']['priority_tlds']??[]);
$allowed=(array)($defaults['discovery']['allowed_tlds']??[]);
$sanitize=fn($x)=>preg_replace('/[^a-z0-9-]/','',strtolower((string)$x));
$tiers=[0=>[],1=>[],2=>[]];
foreach(array_unique(array_merge($priority,$allowed)) as $tld){
    $clean=$sanitize($tld);if($clean==='')continue;
    $tiers[TldRegistry::priorityTier($clean,$priority)][]=$clean;
}
// Cascade à paliers : chaque WHEN ne fait que réordonner, jamais exclure —
// un TLD absent de tous les paliers ci-dessous tombe simplement dans le
// ELSE (dernier), il n'est jamais bloqué (voir TldRegistry::priorityTier).
$case='';
foreach([0,1,2] as $tier){
    if($tiers[$tier]===[])continue;
    $case.="WHEN tld IN ('".implode("','",$tiers[$tier])."') THEN $tier ";
}
$case=$case!==''?"CASE $case ELSE 3 END, ":'';
$order=$case."CASE WHEN last_scanned_at IS NULL THEN 0 ELSE 1 END, COALESCE(next_scan_at,'1970-01-01'), COALESCE(last_scanned_at,'1970-01-01'), id";
$pdo->beginTransaction();$s=$pdo->query("SELECT id,domain FROM v2_domain_queue WHERE (reserved_until IS NULL OR reserved_until<UTC_TIMESTAMP()) AND (last_scanned_at IS NULL OR next_scan_at<=UTC_TIMESTAMP()) ORDER BY $order LIMIT $limit FOR UPDATE");$rows=$s->fetchAll();
if($rows){$ids=implode(',',array_map('intval',array_column($rows,'id')));$ttl=(int)(Bootstrap::defaults()['queue']['reservation_ttl']??1800);$pdo->exec("UPDATE v2_domain_queue SET reserved_by=".$pdo->quote($w['worker_id']).",reserved_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL $ttl SECOND) WHERE id IN ($ids)");}
$pdo->commit();Bootstrap::json(['domains'=>array_column($rows,'domain')]);
