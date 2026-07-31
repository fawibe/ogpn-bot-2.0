<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Ogpn\Bot\Bootstrap;

$pdo = Bootstrap::pdo();
$total = (int) $pdo->query('SELECT COUNT(*) FROM v2_domains')->fetchColumn();

$sections = [
    'overview' => [
        'total' => $total,
        'identified' => (int) $pdo->query("SELECT COUNT(*) FROM v2_domains WHERE category_status='identified'")->fetchColumn(),
        'complete' => (int) $pdo->query("SELECT COUNT(*) FROM v2_domains WHERE data_quality_status IN ('complete','legacy_complete')")->fetchColumn(),
        'blocked' => (int) $pdo->query("SELECT COUNT(*) FROM v2_domains WHERE access_status='blocked'")->fetchColumn(),
        'redirected' => (int) $pdo->query("SELECT COUNT(*) FROM v2_domains WHERE redirect_status='redirect'")->fetchColumn(),
        'for_sale' => (int) $pdo->query('SELECT COUNT(*) FROM v2_domains WHERE domain_for_sale=1')->fetchColumn(),
    ],
    'consent' => [
        'cmp_sites' => (int) $pdo->query('SELECT COUNT(*) FROM v2_domains WHERE consent_cmp_count>0')->fetchColumn(),
        'tracking_sites' => (int) $pdo->query('SELECT COUNT(*) FROM v2_domains WHERE consent_tracking_provider_count>0')->fetchColumn(),
        'review_needed' => (int) $pdo->query('SELECT COUNT(*) FROM v2_domains WHERE consent_review_needed=1')->fetchColumn(),
    ],
    'dependencies' => [
        'sites' => (int) $pdo->query('SELECT COUNT(*) FROM v2_domains WHERE dependency_provider_count>0')->fetchColumn(),
        'red_sites' => (int) $pdo->query('SELECT COUNT(*) FROM v2_domains WHERE dependency_red_count>0')->fetchColumn(),
        'unknown_sites' => (int) $pdo->query('SELECT COUNT(*) FROM v2_domains WHERE JSON_LENGTH(COALESCE(unknown_dependencies, JSON_ARRAY()))>0')->fetchColumn(),
    ],
];

$grouped = static function (PDO $pdo, string $sql): array {
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $r): array => [
        'key' => $r['k'] ?? 'unknown',
        'count' => (int) $r['n'],
    ], $rows);
};

$sections['categories_tier1'] = $grouped($pdo, "SELECT category k,COUNT(*) n FROM v2_domains WHERE category_status='identified' GROUP BY category ORDER BY n DESC");
$sections['categories_tier2'] = $grouped($pdo, "SELECT CONCAT(category,'/',category_tier2) k,COUNT(*) n FROM v2_domains WHERE category_tier2 IS NOT NULL GROUP BY category,category_tier2 ORDER BY n DESC");
$sections['countries'] = $grouped($pdo, "SELECT COALESCE(country_code,'unknown') k,COUNT(*) n FROM v2_domains GROUP BY country_code ORDER BY n DESC");
$sections['tlds'] = $grouped($pdo, "SELECT tld k,COUNT(*) n FROM v2_domains GROUP BY tld ORDER BY n DESC");
$sections['languages'] = $grouped($pdo, "SELECT COALESCE(default_language,'unidentified') k,COUNT(*) n FROM v2_domains GROUP BY default_language ORDER BY n DESC");
$sections['robots'] = $grouped($pdo, "SELECT COALESCE(robots_status,'unknown') k,COUNT(*) n FROM v2_domains GROUP BY robots_status ORDER BY n DESC");

// Agrégation des fournisseurs en PHP : portable MariaDB/MySQL sans dépendre de JSON_TABLE.
$providers = [];
$roles = [];
$stmt = $pdo->query('SELECT dependencies FROM v2_domains WHERE dependency_provider_count>0 AND dependencies IS NOT NULL');
while (($raw = $stmt->fetchColumn()) !== false) {
    $items = json_decode((string) $raw, true);
    if (!is_array($items)) continue;
    $seen = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $name = trim((string) ($item['name'] ?? $item['provider'] ?? ''));
        if ($name === '' || isset($seen[$name])) continue;
        $seen[$name] = true;
        $providers[$name] = ($providers[$name] ?? 0) + 1;
        $role = (string) ($item['category'] ?? 'unknown');
        $roles[$role] = ($roles[$role] ?? 0) + 1;
    }
}
arsort($providers); arsort($roles);
$sections['providers'] = array_map(static fn(string $k, int $n): array => ['key'=>$k,'count'=>$n], array_keys($providers), array_values($providers));
$sections['dependency_roles'] = array_map(static fn(string $k, int $n): array => ['key'=>$k,'count'=>$n], array_keys($roles), array_values($roles));

$save = $pdo->prepare('INSERT INTO v2_report_statistics(section_key,payload,sample_size,calculated_at) VALUES(?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE payload=VALUES(payload),sample_size=VALUES(sample_size),calculated_at=VALUES(calculated_at)');
foreach ($sections as $key => $payload) {
    $save->execute([$key, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR), $total]);
}

echo "Statistiques mises à jour pour {$total} domaine(s), " . count($sections) . " sections.\n";
