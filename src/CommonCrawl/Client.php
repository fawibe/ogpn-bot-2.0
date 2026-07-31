<?php
declare(strict_types=1);
namespace Ogpn\Bot\CommonCrawl;
use Ogpn\Bot\{Bootstrap,Http};
final class Client {
 public static function fetchPage(string $crawl,string $tld,int $page): array {
  $max=(int)(Bootstrap::defaults()['security']['max_cc_response_bytes']??16777216);
  $url='https://index.commoncrawl.org/'.rawurlencode($crawl).'-index?url='.rawurlencode('*.'.$tld).'&output=json&fl=url&page='.$page;
  $start=microtime(true);$r=Http::get($url,45,$max);
  if($r['status']!==200)throw new \RuntimeException("Common Crawl HTTP {$r['status']}");
  $urls=[];$rows=0;$limit=(int)(Bootstrap::defaults()['discovery']['max_urls_per_job']??25000);
  foreach(preg_split('/\R/',trim($r['body']))?:[] as $line){if($line==='')continue;$rows++;$x=json_decode($line,true);if(is_array($x)&&is_string($x['url']??null))$urls[]=$x['url'];if(count($urls)>=$limit)break;}
  return ['urls'=>$urls,'rows_received'=>$rows,'duration_ms'=>(int)round((microtime(true)-$start)*1000),'response_bytes'=>strlen($r['body'])];
 }
}
