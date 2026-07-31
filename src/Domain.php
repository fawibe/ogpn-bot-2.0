<?php
declare(strict_types=1);
namespace Ogpn\Bot;
final class Domain {
 private const COMPOUND_SUFFIXES=['ac.uk','co.uk','gov.uk','ltd.uk','me.uk','net.uk','nhs.uk','org.uk','plc.uk','sch.uk','com.au','net.au','org.au','edu.au','gov.au','asn.au','id.au','co.nz','net.nz','org.nz','govt.nz','ac.nz','com.br','com.mx','co.jp','ne.jp','or.jp','co.kr','com.tr','com.ua'];
 public static function normalizeHost(string $host): ?string {
  $host=strtolower(rtrim(trim($host),'.'));
  if(function_exists('idn_to_ascii')){$a=idn_to_ascii($host,IDNA_DEFAULT,INTL_IDNA_VARIANT_UTS46);if(is_string($a)&&$a!=='')$host=strtolower($a);}
  if(filter_var($host,FILTER_VALIDATE_IP)||strlen($host)>253||!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',$host))return null;
  return $host;
 }
 public static function fromUrl(string $url): ?string {
  $host=parse_url(trim($url),PHP_URL_HOST); if(!is_string($host)||$host==='')return null;
  $host=self::normalizeHost($host); if($host===null)return null;
  $parts=explode('.',$host);$last2=implode('.',array_slice($parts,-2));
  return in_array($last2,self::COMPOUND_SUFFIXES,true)&&count($parts)>=3?implode('.',array_slice($parts,-3)):$last2;
 }
 public static function tld(string $domain): string { $p=explode('.',$domain);return (string)(end($p)?:''); }
}
