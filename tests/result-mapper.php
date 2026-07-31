<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Ogpn\Bot\Engine\ScanResult;
use Ogpn\Bot\Scanner\ScanResultMapper;

$scan = new ScanResult(
    domain: 'example.be',
    robotsBlocksEverything: false,
    robotsStatus: 'robots_allowed',
    aiBotPolicy: ['GPTBot'=>'allowed'],
    filePresence: ['llms'=>true],
    fileMisplaced: [],
    fileConflict: [],
    providerReferenceVersion: 'test',
    providerReferenceSha256: str_repeat('a',64),
    countryCode: 'BE',
    euMember: true,
    defaultLanguage: 'fr',
    alternateLanguages: ['en'],
    httpStatus: 200,
    analysisUrl: 'https://www.example.be/fr',
    analysisSource: 'hreflang',
    tld: 'be',
    tldType: 'ccTLD',
    tldGroups: ['EU','EEE','COE'],
    dependencies: [['name'=>'Example US','category'=>'analytics','eu_status'=>'rouge','tracking_governance_role'=>'analytics']],
    unknownDependencies: [['domain'=>'unknown.test','sources'=>['script'],'suggested_category'=>'unknown','evidence_types'=>['src']]],
    consentSignals: ['cmp_providers'=>[['name'=>'CMP']],'advertising_trackers'=>[['name'=>'Tracker']],'consent_friction_score_auto'=>2],
    category: 'personal_finance',
    categoryStatus: 'identified',
    categorySource: 'keywords',
    categoryConfidence: 78,
    categorySignals: ['banque','crédit'],
    categoryTier2: 'loans',
    categoryTier2Confidence: 60,
    categoryTier2Signals: ['prêt personnel'],
    domainForSale: false,
    redirectStatus: 301,
    redirectCount: 1,
);
$r = ScanResultMapper::map($scan);
$checks = [
    'redirect' => $r['analysis']['redirect_status'] === 'redirect',
    'category' => $r['analysis']['category'] === 'personal_finance',
    'tier2' => $r['analysis']['category_tier2'] === 'loans',
    'alternate' => $r['alternate_languages'] === ['en'],
    'dependency' => $r['analysis']['dependency_provider_count'] === 1,
    'cmp' => $r['analysis']['consent_cmp_count'] === 1,
    'score_not_null' => $r['analysis']['eu_sovereignty_score'] !== null,
];
$fail=0;foreach($checks as $name=>$ok){echo ($ok?'[OK]   ':'[FAIL] '),$name,PHP_EOL;if(!$ok)$fail++;}exit($fail?1:0);
