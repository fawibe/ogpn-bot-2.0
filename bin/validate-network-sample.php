<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Ogpn\Bot\Scanner\SiteScanner;

$targets = [
    'sncb.be' => ['blocked_or_transport'],
    'sncf.fr' => ['blocked_or_transport'],
    'letec.be' => ['blocked_or_transport'],
    'airbnb.com' => ['travel'],
    'fr.airbnb.be' => ['travel'],
    'fr.zalando.be' => ['online_shop'],
    'cbc.be' => ['personal_finance'],
];

$outDir = dirname(__DIR__) . '/storage/network-validation-' . gmdate('Ymd-His');
if (!is_dir($outDir) && !mkdir($outDir, 0770, true) && !is_dir($outDir)) {
    throw new RuntimeException('Impossible de créer le dossier de validation.');
}

$failures = 0;
foreach ($targets as $domain => $expected) {
    echo PHP_EOL, '=== ', $domain, ' ===', PHP_EOL;
    try {
        $result = SiteScanner::scan($domain);
        $analysis = $result['analysis'] ?? [];
        $status = (int) ($result['http_status'] ?? 0);
        $category = (string) ($analysis['category'] ?? 'unidentified');
        $access = (string) ($analysis['access_status'] ?? 'unknown');
        $blocked = in_array($status, [401, 403, 407, 429], true) || $access === 'blocked';

        $valid = match ($expected[0]) {
            'blocked_or_transport' => $blocked || $category === 'transport',
            default => $category === $expected[0],
        };

        echo 'HTTP          : ', $status, PHP_EOL;
        echo 'Redirection   : ', ($analysis['redirect_status'] ?? 'none'), PHP_EOL;
        echo 'URL finale    : ', ($result['final_url'] ?? '-'), PHP_EOL;
        echo 'Accès         : ', $access, PHP_EOL;
        echo 'Catégorie N1  : ', $category, PHP_EOL;
        echo 'Catégorie N2  : ', ($analysis['category_tier2'] ?? '-'), PHP_EOL;
        echo 'Confiance     : ', ($analysis['category_confidence'] ?? 0), PHP_EOL;
        echo 'Dépendances   : ', ($analysis['dependency_provider_count'] ?? 0), PHP_EOL;
        echo 'CMP           : ', ($analysis['consent_cmp_count'] ?? 0), PHP_EOL;
        echo 'Souveraineté  : ', (($analysis['eu_sovereignty_score'] ?? null) === null ? 'non calculée' : $analysis['eu_sovereignty_score']), PHP_EOL;
        echo $valid ? "[OK]\n" : "[À CONTRÔLER]\n";
        if (!$valid) {
            $failures++;
        }

        file_put_contents(
            $outDir . '/' . preg_replace('/[^a-z0-9]+/i', '-', $domain) . '.json',
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n"
        );
    } catch (Throwable $e) {
        $failures++;
        echo '[ERREUR] ', $e->getMessage(), PHP_EOL;
    }
}

echo PHP_EOL, 'Rapports : ', $outDir, PHP_EOL;
echo 'Contrôles à revoir : ', $failures, PHP_EOL;
exit($failures > 0 ? 2 : 0);
