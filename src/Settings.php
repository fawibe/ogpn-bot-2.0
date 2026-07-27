<?php
declare(strict_types=1);
namespace Ogpn\Bot;
use PDO;
final class Settings {
 public static function all(PDO $pdo): array { $r=$pdo->query('SELECT setting_key,setting_value FROM settings')->fetchAll();$o=[];foreach($r as $x)$o[$x['setting_key']]=json_decode($x['setting_value'],true);return $o; }
 public static function get(PDO $pdo,string $key,mixed $default=null): mixed { $s=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$default:json_decode((string)$v,true); }
 public static function set(PDO $pdo,string $key,mixed $value): void { $s=$pdo->prepare('INSERT INTO settings(setting_key,setting_value,updated_at) VALUES(?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=UTC_TIMESTAMP()');$s->execute([$key,json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]); }
}
