<?php
declare(strict_types=1);
namespace Ogpn\Bot;
final class Http {
 public static function get(string $url,int $timeout=30,int $maxBytes=16777216): string {
  $ch=curl_init($url);$data='';curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>$timeout,CURLOPT_USERAGENT=>'OGPN-BOT/2.0 (+https://ogpn.eu)',CURLOPT_RETURNTRANSFER=>false,CURLOPT_HEADER=>false,CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$data,$maxBytes){$data.=$chunk;if(strlen($data)>$maxBytes)return 0;return strlen($chunk);}]);
  $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);if($ok===false||$status<200||$status>=300)throw new \RuntimeException("HTTP $status $err");return $data;
 }
}
