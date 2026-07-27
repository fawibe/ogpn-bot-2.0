<?php
declare(strict_types=1);
namespace Ogpn\Bot;
use PDO;
final class Auth {
 public static function bearer(): string { $h=$_SERVER['HTTP_AUTHORIZATION']??''; return preg_match('/^Bearer\s+(.+)$/i',$h,$m)?trim($m[1]):''; }
 public static function worker(PDO $pdo,string $permission): array {
  $token=self::bearer(); if($token==='') Bootstrap::json(['error'=>'missing_token'],401);
  $hash=hash('sha256',$token);
  $s=$pdo->prepare('SELECT * FROM workers WHERE token_hash=? AND enabled=1 LIMIT 1');$s->execute([$hash]);$w=$s->fetch();
  if(!$w) Bootstrap::json(['error'=>'invalid_token'],401);
  $perms=json_decode((string)$w['permissions'],true)?:[];
  if(!in_array($permission,$perms,true) && !in_array('*',$perms,true)) Bootstrap::json(['error'=>'forbidden'],403);
  $pdo->prepare('UPDATE workers SET last_seen_at=UTC_TIMESTAMP() WHERE worker_id=?')->execute([$w['worker_id']]); return $w;
 }
 public static function adminLogin(string $token): bool { $c=Bootstrap::serverConfig(); return isset($c['admin_token_hash']) && password_verify($token,(string)$c['admin_token_hash']); }
}
