<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Ogpn\Bot\{Auth,Bootstrap};use Ogpn\Bot\CommonCrawl\Seeder;
$pdo=Bootstrap::pdo();$worker=Auth::worker($pdo,'discovery.claim');
try{Seeder::ensureJobs($pdo,8);}catch(Throwable $e){Bootstrap::log('warning','CC seeding failed',['error'=>$e->getMessage()]);}
$pdo->beginTransaction();
$stmt=$pdo->query("SELECT id,crawl_id,tld,page_number FROM v2_common_crawl_jobs WHERE status='pending' OR (status='reserved' AND reserved_until<UTC_TIMESTAMP()) OR (status='failed' AND attempts<5 AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP())) ORDER BY FIELD(status,'pending','failed','reserved'),COALESCE(next_attempt_at,created_at),id LIMIT 1 FOR UPDATE");
$job=$stmt->fetch();if(!$job){$pdo->commit();Bootstrap::json(['job'=>null]);}
$update=$pdo->prepare("UPDATE v2_common_crawl_jobs SET status='reserved',reserved_by=?,reserved_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 20 MINUTE),attempts=attempts+1,last_error=NULL,error_type=NULL WHERE id=?");
$update->execute([$worker['worker_id'],$job['id']]);$pdo->commit();Bootstrap::json(['job'=>$job]);
