<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Ogpn\Bot\Bootstrap;use Ogpn\Bot\CommonCrawl\Client as CommonCrawlClient;use Ogpn\Bot\Scanner\SiteScanner;
function uuid4(): string {$d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
function callApi(string $method,string $url,string $token,?array $body=null,int $timeout=60):array{
 $ch=curl_init($url);$h=['Authorization: Bearer '.$token,'Accept: application/json'];if($body!==null)$h[]='Content-Type: application/json';
 $payload=null;if($body!==null){try{$payload=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new RuntimeException('Encodage JSON impossible pour '.$url.' : '.$e->getMessage(),0,$e);}}
 curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$h,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>$timeout,CURLOPT_POSTFIELDS=>$payload]);
 $raw=curl_exec($ch);$st=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$e=curl_error($ch);curl_close($ch);
 if($raw===false||$st<200||$st>=300)throw new RuntimeException("API $st — $url — $e $raw");$j=json_decode((string)$raw,true);if(!is_array($j))throw new RuntimeException('JSON API invalide');return $j;
}
$config=Bootstrap::optionalServerConfig();$base=rtrim((string)(getenv('OGPN_API_URL')?:($config['base_url']??'')),'/');if(!str_ends_with($base,'/api'))$base.='/api';$id=(string)(getenv('OGPN_WORKER_ID')?:($config['local_worker_id']??'infomaniak-1'));$token=(string)getenv('OGPN_API_TOKEN');
if($token===''){$p=Bootstrap::root().'/storage/secrets/generated-tokens.php';if(is_file($p)){$tokens=(array)require $p;$token=(string)($tokens[$id]??'');}}
if($base==='/api'||$token===''||$id===''){fwrite(STDERR,"Configuration worker incomplète (URL, ID ou token).\n");exit(2);}
$runId=uuid4();$started=microtime(true);$ccPages=0;$added=0;$scanned=0;$errors=0;$details=[];
try{
 $plan=callApi('POST',$base.'/run-start.php',$token,['run_id'=>$runId]);
 echo "Worker $id : {$plan['action']} ({$plan['reason']})\n";$deadline=$started+(int)($plan['limits']['max_runtime_seconds']??60);
 if($plan['action']==='discovery'){
  for($i=0;$i<(int)$plan['limits']['max_cc_pages']&&microtime(true)<$deadline-5;$i++){
   $claim=callApi('POST',$base.'/common-crawl-claim.php',$token,[]);$job=$claim['job']??null;if(!$job){echo "Aucune page Common Crawl disponible.\n";break;}
   try{$page=CommonCrawlClient::fetchPage((string)$job['crawl_id'],(string)$job['tld'],(int)$job['page_number']);$r=callApi('POST',$base.'/common-crawl-ingest.php',$token,['job_id'=>$job['id']]+$page,90);$ccPages++;$added+=(int)$r['domains_added'];echo "CC {$job['tld']} page {$job['page_number']} : {$r['domains_added']} nouveaux domaines ({$r['root_domains']} distincts).\n";}
   catch(Throwable $e){$errors++;$failure=callApi('POST',$base.'/common-crawl-fail.php',$token,['job_id'=>$job['id'],'error'=>$e->getMessage()]);Bootstrap::log('error','Common Crawl job failed',['job'=>$job,'error'=>$e->getMessage(),'retry'=>$failure]);echo "Échec CC {$job['tld']} page {$job['page_number']} : {$e->getMessage()} — reprise différée.\n";break;}
  }
 } elseif($plan['action']==='scan'){
  $max=(int)$plan['limits']['max_scan_domains'];$chunk=(int)$plan['limits']['scan_chunk_size'];$override=(int)getenv('OGPN_MAX_SCAN_DOMAINS');if($override>0)$max=min($max,$override);$chunkOverride=(int)getenv('OGPN_SCAN_CHUNK_SIZE');if($chunkOverride>0)$chunk=max(1,min($chunk,$chunkOverride));
  while($scanned<$max&&microtime(true)<$deadline-10){$q=callApi('POST',$base.'/scan-claim.php',$token,['limit'=>min($chunk,$max-$scanned)]);$domains=$q['domains']??[];if(!$domains){echo "Aucun domaine dû.\n";break;}$results=[];$failed=[];
   $eligible=[];foreach($domains as $domain){if(microtime(true)>=$deadline-5){$failed[]=$domain;continue;}$eligible[]=(string)$domain;}
   if($eligible){try{$batch=SiteScanner::scanBatch($eligible);foreach($eligible as $domain){if(isset($batch[$domain])){$results[]=$batch[$domain];}else{$failed[]=$domain;$errors++;$details['scan_errors'][]=['domain'=>$domain,'error'=>'missing_batch_result'];}}}catch(Throwable $e){foreach($eligible as $domain)$failed[]=$domain;$errors+=count($eligible);$details['scan_errors'][]=['batch'=>$eligible,'error'=>$e->getMessage()];}}
   if($results){$r=callApi('POST',$base.'/scan-ingest.php',$token,['results'=>$results],180);$scanned+=(int)$r['ingested'];echo $r['ingested']." domaines scannés.\n";}
   if($failed)callApi('POST',$base.'/scan-fail.php',$token,['domains'=>$failed]);
  }
 }
 callApi('POST',$base.'/run-finish.php',$token,['run_id'=>$runId,'cc_pages'=>$ccPages,'domains_added'=>$added,'domains_scanned'=>$scanned,'error_count'=>$errors,'details'=>$details]);exit(0);
}catch(Throwable $e){$errors++;Bootstrap::log('critical','Worker run failed',['worker'=>$id,'run_id'=>$runId,'error'=>$e->getMessage()]);try{callApi('POST',$base.'/run-finish.php',$token,['run_id'=>$runId,'cc_pages'=>$ccPages,'domains_added'=>$added,'domains_scanned'=>$scanned,'error_count'=>$errors,'details'=>['fatal'=>$e->getMessage()]]);}catch(Throwable){}fwrite(STDERR,"[ERREUR] {$e->getMessage()}\n");exit(1);}
