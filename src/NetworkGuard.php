<?php
declare(strict_types=1);
namespace Ogpn\Bot;
final class NetworkGuard {
 public static function assertPublicUrl(string $url): void {
  $p=parse_url($url);$scheme=strtolower((string)($p['scheme']??''));$host=(string)($p['host']??'');
  if(!in_array($scheme,['http','https'],true)||$host==='') throw new \RuntimeException('URL non autorisée.');
  self::assertPublicHost($host);
 }
 public static function assertPublicHost(string $host): void {
  $host=rtrim(strtolower($host),'.');
  if(in_array($host,['localhost','localhost.localdomain'],true)||str_ends_with($host,'.local')) throw new \RuntimeException('Hôte local interdit.');
  $ips=[];
  if(filter_var($host,FILTER_VALIDATE_IP))$ips[]=$host;
  else {
   $records=@dns_get_record($host,DNS_A|DNS_AAAA)?:[];
   foreach($records as $r){if(isset($r['ip']))$ips[]=$r['ip'];if(isset($r['ipv6']))$ips[]=$r['ipv6'];}
  }
  if(!$ips) throw new \RuntimeException('Résolution DNS impossible.');
  foreach(array_unique($ips) as $ip){
   if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false) throw new \RuntimeException('Adresse privée ou réservée interdite.');
  }
 }
}
