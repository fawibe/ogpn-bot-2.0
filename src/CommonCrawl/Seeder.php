<?php
declare(strict_types=1);
namespace Ogpn\Bot\CommonCrawl;
use Ogpn\Bot\{Bootstrap,Http,Settings};
use Ogpn\Bot\Engine\TldRegistry;
use PDO;
final class Seeder {
 /**
  * Étend la liste de TLD en fonction de leur palier de priorité
  * (TldRegistry::priorityTier) : le palier 0 (ex. fr/be/lu/ch/mc) apparaît
  * 4x plus souvent dans la séquence, le palier 1 2x plus souvent, le
  * palier 2 (candidats UE) 1x. Round-robin pondéré — jamais de blocage
  * total d'un TLD, juste une fréquence de visite plus faible. Même principe
  * que la cascade appliquée côté scan (api/scan-claim.php).
  * @param string[] $tlds
  * @return string[]
  */
 private static function weightedTldSequence(array $tlds): array {
  $priority=(array)(Bootstrap::defaults()['queue']['priority_tlds']??[]);
  $weights=[0=>4,1=>2,2=>1];
  $sequence=[];
  foreach($tlds as $tld){
   $weight=$weights[TldRegistry::priorityTier($tld,$priority)]??1;
   for($i=0;$i<$weight;$i++)$sequence[]=$tld;
  }
  return $sequence!==[]?$sequence:$tlds;
 }
 public static function latestCrawlId(PDO $pdo): string {
  $cached=(string)Settings::get($pdo,'common_crawl.latest_id','');$checked=(string)Settings::get($pdo,'common_crawl.latest_checked_at','');
  if($cached!==''&&$checked!==''&&strtotime($checked)>time()-21600)return $cached;
  $url=(string)(Bootstrap::defaults()['discovery']['crawl_index_url']??'https://index.commoncrawl.org/collinfo.json');
  $r=Http::get($url,15,1048576);if($r['status']!==200)throw new \RuntimeException('Liste Common Crawl indisponible.');
  $j=json_decode($r['body'],true);if(!is_array($j)||!isset($j[0]['id']))throw new \RuntimeException('Liste Common Crawl invalide.');
  $id=(string)$j[0]['id'];Settings::set($pdo,'common_crawl.latest_id',$id);Settings::set($pdo,'common_crawl.latest_checked_at',gmdate('c'));return $id;
 }
 public static function ensureJobs(PDO $pdo,int $target=8): int {
  if(!Settings::bool($pdo,'features.discovery_enabled',true))return 0;
  $pending=(int)$pdo->query("SELECT COUNT(*) FROM v2_common_crawl_jobs WHERE status IN ('pending','reserved')")->fetchColumn();if($pending>=$target)return 0;
  $crawl=self::latestCrawlId($pdo);$tlds=self::weightedTldSequence((array)(Bootstrap::defaults()['discovery']['allowed_tlds']??[]));$added=0;
  $cursor=(int)Settings::get($pdo,'common_crawl.tld_cursor',0);$tries=0;
  while($pending+$added<$target&&$tries<count($tlds)*2){
   $tld=(string)$tlds[$cursor%count($tlds)];$cursor++;$tries++;
   $state=$pdo->prepare('SELECT next_page,total_pages FROM v2_common_crawl_tld_state WHERE crawl_id=? AND tld=?');$state->execute([$crawl,$tld]);$row=$state->fetch();
   if(!$row){$pages=self::fetchPageCount($crawl,$tld);$pdo->prepare('INSERT INTO v2_common_crawl_tld_state(crawl_id,tld,next_page,total_pages,updated_at) VALUES(?,?,0,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE total_pages=VALUES(total_pages),updated_at=UTC_TIMESTAMP()')->execute([$crawl,$tld,$pages]);$next=0;$total=$pages;}else{$next=(int)$row['next_page'];$total=(int)$row['total_pages'];}
   if($total<=0||$next>=$total)continue;
   $ins=$pdo->prepare("INSERT IGNORE INTO v2_common_crawl_jobs(crawl_id,tld,page_number,status) VALUES(?,?,?,'pending')");$ins->execute([$crawl,$tld,$next]);
   $pdo->prepare('UPDATE v2_common_crawl_tld_state SET next_page=next_page+1,updated_at=UTC_TIMESTAMP() WHERE crawl_id=? AND tld=?')->execute([$crawl,$tld]);
   if($ins->rowCount()>0)$added++;
  }
  Settings::set($pdo,'common_crawl.tld_cursor',$cursor);return $added;
 }
 private static function fetchPageCount(string $crawl,string $tld): int {
  $url='https://index.commoncrawl.org/'.rawurlencode($crawl).'-index?url='.rawurlencode('*.'.$tld).'&output=json&showNumPages=true';
  $r=Http::get($url,30,1048576);if($r['status']!==200)throw new \RuntimeException("Comptage CC $tld HTTP {$r['status']}");
  $j=json_decode(trim($r['body']),true);$pages=(int)($j['pages']??0);return max(0,$pages);
 }
}
