<?php
declare(strict_types=1);
namespace Ogpn\Bot;
use PDO;
final class Bootstrap {
 public static function root(): string { return dirname(__DIR__); }
 public static function defaults(): array { return require self::root().'/config/defaults.php'; }
 public static function hasServerConfig(): bool { return is_file(self::root().'/storage/secrets/server-config.php'); }
 public static function optionalServerConfig(): array { return self::hasServerConfig()?self::serverConfig():[]; }
 public static function serverConfig(): array {
  $p=self::root().'/storage/secrets/server-config.php';
  if(!is_file($p)) throw new \RuntimeException('Configuration serveur absente : storage/secrets/server-config.php');
  $c=require $p; if(!is_array($c)) throw new \RuntimeException('Configuration serveur invalide.'); return $c;
 }
 public static function pdo(): PDO {
  $d=self::serverConfig()['database']??[];
  foreach(['dsn','user','password'] as $k) if(!array_key_exists($k,$d)) throw new \RuntimeException("Configuration DB incomplète : $k");
  return new PDO((string)$d['dsn'],(string)$d['user'],(string)$d['password'],[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
    PDO::ATTR_STRINGIFY_FETCHES=>false,
  ]);
 }
 public static function json(array $data,int $status=200): never {
  http_response_code($status); header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE); exit;
 }
 public static function log(string $level,string $message,array $context=[]): void {
  $dir=self::root().'/storage/logs'; if(!is_dir($dir)) @mkdir($dir,0770,true);
  $line=json_encode(['ts'=>gmdate('c'),'level'=>$level,'message'=>$message,'context'=>$context],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n";
  @file_put_contents($dir.'/app-'.gmdate('Y-m-d').'.log',$line,FILE_APPEND|LOCK_EX);
 }
}
