<?php
declare(strict_types=1);
namespace Ogpn\Bot;
use PDO;
final class Auth {
 public static function bearer(): string {
  $h=$_SERVER['HTTP_AUTHORIZATION']??($_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
  return preg_match('/^Bearer\s+(.+)$/i',(string)$h,$m)?trim($m[1]):'';
 }
 public static function worker(PDO $pdo,string $permission): array {
  $token=self::bearer(); if($token==='') Bootstrap::json(['error'=>'missing_token'],401);
  $hash=hash('sha256',$token);
  $s=$pdo->prepare('SELECT * FROM v2_workers WHERE token_hash=? AND enabled=1 LIMIT 1');$s->execute([$hash]);$w=$s->fetch();
  if(!$w) Bootstrap::json(['error'=>'invalid_token'],401);
  $perms=json_decode((string)$w['permissions'],true)?:[];
  if(!in_array($permission,$perms,true) && !in_array('*',$perms,true)) Bootstrap::json(['error'=>'forbidden'],403);
  self::rateLimit($pdo,'worker:'.$w['worker_id'],(int)(Bootstrap::defaults()['security']['api_rate_limit_per_minute']??120));
  $pdo->prepare('UPDATE v2_workers SET last_seen_at=UTC_TIMESTAMP() WHERE worker_id=?')->execute([$w['worker_id']]); return $w;
 }
 public static function adminLogin(string $token): bool { $c=Bootstrap::serverConfig(); return isset($c['admin_token_hash']) && password_verify($token,(string)$c['admin_token_hash']); }
 public static function csrfToken(): string { if(session_status()!==PHP_SESSION_ACTIVE) session_start(); return $_SESSION['csrf']??=bin2hex(random_bytes(24)); }
 public static function requireCsrf(?string $token): void { if(!is_string($token)||!hash_equals((string)($_SESSION['csrf']??''),$token)) throw new \RuntimeException('Jeton CSRF invalide.'); }
 public static function rateLimit(PDO $pdo,string $bucket,int $limit): void {
  $minute=gmdate('Y-m-d H:i:00');
  $s=$pdo->prepare('INSERT INTO v2_rate_limits(bucket,window_start,request_count) VALUES(?,?,1) ON DUPLICATE KEY UPDATE request_count=request_count+1');$s->execute([$bucket,$minute]);
  $q=$pdo->prepare('SELECT request_count FROM v2_rate_limits WHERE bucket=? AND window_start=?');$q->execute([$bucket,$minute]);
  if((int)$q->fetchColumn()>$limit) Bootstrap::json(['error'=>'rate_limited'],429);
 }
}
