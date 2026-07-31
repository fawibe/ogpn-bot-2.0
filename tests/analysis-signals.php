<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Ogpn\Bot\Engine\{FetchResult,Http,Scanner};
$scanner=new Scanner(new Http());$ref=new ReflectionClass($scanner);$fail=0;
$invoke=function(string $method,array $args)use($scanner,$ref){$m=$ref->getMethod($method);$m->setAccessible(true);return $m->invokeArgs($scanner,$args);};
$saleHtml='<html><title>Domain for sale</title><body>This domain is for sale. Buy this domain now.</body></html>';
$fetch=FetchResult::success('https://example.test/',200,$saleHtml,'HTTP/2 200');
$sale=$invoke('detectDomainForSale',[$fetch,$saleHtml]);
$consentHtml='<div>Nous utilisons des cookies. Tout accepter Refuser les cookies Gérer mes préférences</div><script>__tcfapi()</script>';
$consent=$invoke('detectConsentSignals',[$consentHtml,$consentHtml,[]]);
$identity=$invoke('detectDigitalIdentityProviders',['<a href="https://accounts.google.com/o/oauth2/auth">Continuer avec Google</a><a href="https://login.microsoftonline.com/common/oauth2">Microsoft</a>']);
$checks=[
 'domain sale'=>$sale['for_sale']===true,
 'consent banner'=>!empty($consent['signals']),
 'consent friction'=>isset($consent['consent_friction_score_auto']),
 'identity providers'=>count($identity)>=2,
];
foreach($checks as $n=>$ok){echo($ok?'[OK]   ':'[FAIL] '),$n,PHP_EOL;if(!$ok)$fail++;}exit($fail?1:0);
