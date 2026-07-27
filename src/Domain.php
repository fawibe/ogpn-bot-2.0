<?php
declare(strict_types=1);
namespace Ogpn\Bot;
final class Domain {
 public static function fromUrl(string $url): ?string {
  $host=parse_url($url,PHP_URL_HOST); if(!is_string($host)||$host==='')return null; $host=strtolower(rtrim($host,'.'));
  if(function_exists('idn_to_ascii')){$a=idn_to_ascii($host,IDNA_DEFAULT,INTL_IDNA_VARIANT_UTS46);if(is_string($a))$host=$a;}
  if(filter_var($host,FILTER_VALIDATE_IP))return null; if(!preg_match('/^[a-z0-9.-]+$/',$host))return null;
  $parts=explode('.',$host); if(count($parts)<2)return null;
  $compound=['co.uk','org.uk','gov.uk','ac.uk','com.au','net.au','org.au','co.nz','com.br','com.mx','co.jp'];
  $last2=implode('.',array_slice($parts,-2)); return in_array($last2,$compound,true)&&count($parts)>=3?implode('.',array_slice($parts,-3)):$last2;
 }
 public static function tld(string $domain): string { $p=explode('.',$domain);return end($p)?:''; }
}
