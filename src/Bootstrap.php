<?php
declare(strict_types=1);
namespace Ogpn\Bot;
use PDO;
final class Bootstrap {
 public static function root(): string { return dirname(__DIR__); }
 public static function defaults(): array { return require self::root().'/config/defaults.php'; }
 public static function serverConfig(): array {
  $p=self::root().'/storage/secrets/server-config.php';
  if(!is_file($p)) throw new \RuntimeException('Configuration serveur absente. Copiez config/server-config.example.php.');
  $c=require $p; if(!is_array($c)) throw new \RuntimeException('Configuration serveur invalide.'); return $c;
 }
 public static function pdo(): PDO {
  $d=self::serverConfig()['database'];
  return new PDO($d['dsn'],$d['user'],$d['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 }
 public static function json(array $data,int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
}
