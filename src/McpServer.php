<?php
declare(strict_types=1);
namespace Ogpn\Bot;
use PDO;
final class McpServer {
 public static function authenticate(PDO $pdo): array {
  $token=Auth::bearer();if($token==='')Bootstrap::json(['error'=>'missing_token'],401);
  $s=$pdo->prepare('SELECT * FROM v2_mcp_clients WHERE token_hash=? AND enabled=1 LIMIT 1');$s->execute([hash('sha256',$token)]);$c=$s->fetch();if(!$c)Bootstrap::json(['error'=>'invalid_token'],401);
  $limit=(int)(Bootstrap::serverConfig()['mcp']['rate_limit_per_minute']??30);Auth::rateLimit($pdo,'mcp:'.$c['client_id'],max(1,$limit));$pdo->prepare('UPDATE v2_mcp_clients SET last_seen_at=UTC_TIMESTAMP() WHERE client_id=?')->execute([$c['client_id']]);return $c;
 }
 public static function handle(PDO $pdo,array $client,array $body): array {
  $tool=(string)($body['tool']??'');$map=['get_system_status'=>'mcp.status.read','get_queue_status'=>'mcp.status.read','get_configuration_summary'=>'mcp.configuration.read','get_domain_report'=>'mcp.domain.read','get_statistics'=>'mcp.statistics.read','get_diagnostic_summary'=>'mcp.diagnostics.read'];
  if(!isset($map[$tool]))return ['error'=>'unknown_tool'];$perms=json_decode((string)$client['permissions'],true)?:[];if(!in_array($map[$tool],$perms,true)&&!in_array('*',$perms,true))return ['error'=>'forbidden'];
  return match($tool){
   'get_system_status'=>['version'=>trim((string)@file_get_contents(Bootstrap::root().'/VERSION')),'php'=>PHP_VERSION,'database'=>true,'server_time'=>gmdate('c')],
   'get_queue_status'=>Planner::metrics($pdo),
   'get_configuration_summary'=>self::configSummary($pdo),
   'get_domain_report'=>self::domainReport($pdo,(string)($body['arguments']['domain']??'')),
   'get_statistics'=>self::statistics($pdo),
   'get_diagnostic_summary'=>self::diagnostics($pdo),
   default=>['error'=>'unknown_tool']};
 }
 private static function configSummary(PDO $pdo): array {$d=Bootstrap::defaults();return ['default_language'=>$d['app']['default_language'],'secondary_language'=>$d['app']['secondary_language'],'queue_high_threshold'=>Settings::get($pdo,'queue.high_threshold',$d['queue']['high_threshold']),'queue_low_threshold'=>Settings::get($pdo,'queue.low_threshold',$d['queue']['low_threshold']),'discovery_enabled'=>Settings::bool($pdo,'features.discovery_enabled',true),'scan_enabled'=>Settings::bool($pdo,'features.scan_enabled',true),'emergency_stop'=>Settings::bool($pdo,'features.emergency_stop',false),'orchestrator_mode'=>Settings::get($pdo,'orchestrator.mode','discovery')];}
 private static function domainReport(PDO $pdo,string $domain): array {$d=Domain::normalizeHost($domain);if($d===null)return ['error'=>'invalid_domain'];$s=$pdo->prepare('SELECT domain,tld,http_status,final_url,default_language,selected_language,language_source,language_confidence,analysis_json,first_scanned_at,last_scanned_at FROM v2_domains WHERE domain=?');$s->execute([$d]);$r=$s->fetch();if(!$r)return ['error'=>'not_found'];$r['analysis']=json_decode((string)($r['analysis_json']??'{}'),true);unset($r['analysis_json']);return $r;}
 private static function statistics(PDO $pdo): array {return ['domains_total'=>(int)$pdo->query('SELECT COUNT(*) FROM v2_domains')->fetchColumn(),'queue_total'=>(int)$pdo->query('SELECT COUNT(*) FROM v2_domain_queue')->fetchColumn(),'workers_enabled'=>(int)$pdo->query('SELECT COUNT(*) FROM v2_workers WHERE enabled=1')->fetchColumn(),'runs_24h'=>(int)$pdo->query('SELECT COUNT(*) FROM v2_worker_runs WHERE started_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 24 HOUR)')->fetchColumn(),'scanned_24h'=>(int)$pdo->query('SELECT COALESCE(SUM(domains_scanned),0) FROM v2_worker_runs WHERE started_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 24 HOUR)')->fetchColumn(),'added_24h'=>(int)$pdo->query('SELECT COALESCE(SUM(domains_added),0) FROM v2_worker_runs WHERE started_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 24 HOUR)')->fetchColumn()];}
 private static function diagnostics(PDO $pdo): array {$workers=$pdo->query('SELECT worker_id,platform,preferred_role,capacity,enabled,last_seen_at,last_action FROM v2_workers ORDER BY worker_id')->fetchAll();return ['queue'=>Planner::metrics($pdo),'workers'=>$workers,'latest_runs'=>$pdo->query('SELECT run_id,worker_id,action,reason,started_at,finished_at,domains_added,domains_scanned,error_count FROM v2_worker_runs ORDER BY started_at DESC LIMIT 20')->fetchAll()];}
}
