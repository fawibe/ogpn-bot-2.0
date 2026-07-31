<?php
declare(strict_types=1);
namespace Ogpn\Bot;
final class Healthcheck {
 public static function ping(string $kind,string $suffix=''): void {
  $c=Bootstrap::optionalServerConfig()['healthcheck']??[];if(empty($c['enabled']))return;$url=(string)($c[$kind.'_url']??'');if($url==='')return;$target=rtrim($url,'/').$suffix;
  $ch=curl_init($target);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);@curl_exec($ch);curl_close($ch);
 }
}
