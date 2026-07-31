<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Ogpn\Bot\Engine\ScanResult;
use Ogpn\Bot\Scanner\ScanResultMapper;

function make(int $status, array $deps=[]): ScanResult {
 return new ScanResult(domain:'quality.be',robotsBlocksEverything:false,robotsStatus:'robots_allowed',aiBotPolicy:[],filePresence:[],fileMisplaced:[],fileConflict:[],providerReferenceVersion:'test',providerReferenceSha256:str_repeat('b',64),countryCode:'BE',euMember:true,defaultLanguage:'fr',alternateLanguages:[],httpStatus:$status,analysisUrl:'https://quality.be/',dependencies:$deps);
}
$blocked=ScanResultMapper::map(make(403));
$noDeps=ScanResultMapper::map(make(200));
$checks=[
 '403 blocked'=>$blocked['analysis']['access_status']==='blocked',
 '403 incomplete'=>$blocked['analysis']['analysis_complete']===false,
 '403 no score'=>$blocked['analysis']['eu_sovereignty_score']===null,
 'no deps inconclusive score'=>$noDeps['analysis']['eu_sovereignty_score']===null,
];
$f=0;foreach($checks as $n=>$ok){echo($ok?'[OK]   ':'[FAIL] '),$n,PHP_EOL;if(!$ok)$f++;}exit($f?1:0);
