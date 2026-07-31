<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Ogpn\Bot\{Auth, Bootstrap, Domain};
use Ogpn\Bot\Analysis\HostingResolver;
use Ogpn\Bot\Engine\Scoring;

$pdo = Bootstrap::pdo();
$worker = Auth::worker($pdo, 'scan.ingest');
$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body) || !is_array($body['results'] ?? null)) {
    Bootstrap::json(['error' => 'invalid_payload'], 422);
}

$serverConfig = Bootstrap::optionalServerConfig();
$hostingResolver = new HostingResolver((string)($serverConfig['maxmind']['asn_db'] ?? '') ?: null);

$json = static fn(mixed $value): string => json_encode(
    $value ?? [],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
);

$columns = [
    'domain','tld','http_status','final_url','default_language','alternate_languages','selected_language',
    'language_source','language_confidence','analysis_json','first_scanned_at','last_scanned_at',
    'country_code','eu_member','tld_type','tld_groups','redirect_status','redirect_count',
    'category','category_status','category_source','category_confidence','category_signals',
    'category_negative_signals','category_tier2','category_tier2_confidence','category_tier2_signals',
    'taxonomy_version','robots_status','robots_blocks_everything','ai_bot_policy','dependencies',
    'unknown_dependencies','provider_reference_version','provider_reference_sha256','dependency_provider_count',
    'dependency_red_count','dependency_tracking_role_count','dependency_governance','consent_signals',
    'consent_friction_score_auto','consent_review_needed','consent_cmp_count','consent_tracking_provider_count',
    'digital_identity_providers','review_platforms','social_presence','file_presence','file_misplaced','file_conflict',
    'tdm_reservation','tdm_policy_url','domain_for_sale','domain_for_sale_source','domain_for_sale_provider',
    'accessibility_statement_present','legal_notice_present','has_json_ld','has_microdata','generator','server_header',
    'ip_address','ssl_issuer','foodtruck_mentions','ai_openness_score','eu_sovereignty_score','scan_engine_version',
    'data_quality_status','access_status','hosting_provider','hosting_asn','hosting_eu_status'
];
$mutable = array_values(array_filter($columns, static fn(string $c): bool => !in_array($c, ['domain','tld','first_scanned_at'], true)));
// Taux de disparition (repris de V1) : unreachable_streak/first_unreachable_at/
// confirmed_unreachable sont calculés uniquement à partir de l'état précédent
// de la ligne (v2_domains.*) et du VALUES() inséré pour cette ligne — pas de
// SELECT séparé par domaine. Seuil double : >=3 échecs consécutifs ET >=7
// jours (604800s) depuis le premier échec de la série en cours. Un seul
// succès (n'importe quel statut HTTP reçu, même 403/blocked) remet tout à 0.
$sql = 'INSERT INTO v2_domains (' . implode(',', $columns) . ',unreachable_streak,first_unreachable_at,confirmed_unreachable) VALUES ('
    . implode(',', array_fill(0, count($columns), '?'))
    . ',IF(?,1,0),IF(?,UTC_TIMESTAMP(),NULL),0) ON DUPLICATE KEY UPDATE '
    . implode(',', array_map(static fn(string $c): string => "$c=VALUES($c)", $mutable))
    . ',unreachable_streak=IF(VALUES(unreachable_streak)=1,v2_domains.unreachable_streak+1,0)'
    . ',first_unreachable_at=IF(VALUES(unreachable_streak)=1,IF(v2_domains.unreachable_streak=0,UTC_TIMESTAMP(),v2_domains.first_unreachable_at),NULL)'
    . ',confirmed_unreachable=IF(VALUES(unreachable_streak)=1 AND (v2_domains.unreachable_streak+1)>=3 AND v2_domains.first_unreachable_at IS NOT NULL AND TIMESTAMPDIFF(SECOND,v2_domains.first_unreachable_at,UTC_TIMESTAMP())>=604800,1,0)';
$upsert = $pdo->prepare($sql);
$release = $pdo->prepare('UPDATE v2_domain_queue SET last_scanned_at=UTC_TIMESTAMP(),next_scan_at=?,reserved_by=NULL,reserved_until=NULL,scan_attempts=scan_attempts+1,last_scan_status=? WHERE domain=? AND reserved_by=?');
$history = $pdo->prepare('INSERT INTO v2_scan_history(domain,worker_id,scanned_at,http_status,selected_language,category,category_tier2,category_confidence,data_quality_status,engine_version,response_truncated,analysis_complete,dependency_provider_count,consent_cmp_count,robots_status,eu_sovereignty_score,redirect_status,redirect_count,domain_for_sale,access_status,taxonomy_version,hosting_provider,hosting_asn,hosting_eu_status,result_json) VALUES(?,?,UTC_TIMESTAMP(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

$pdo->beginTransaction();
$accepted = 0;
$released = 0;
try {
    foreach ($body['results'] as $result) {
        if (!is_array($result)) continue;
        $domain = Domain::normalizeHost((string) ($result['domain'] ?? ''));
        if ($domain === null) continue;
        $a = is_array($result['analysis'] ?? null) ? $result['analysis'] : [];
        $status = isset($result['http_status']) ? (int) $result['http_status'] : null;
        $complete = (bool) ($a['analysis_complete'] ?? false);
        $blocked = ($a['access_status'] ?? null) === 'blocked';
        $quality = $status === null || $status === 0
            ? 'unreachable'
            : ($complete ? 'complete' : ($blocked ? 'blocked' : 'inconclusive'));
        $now = gmdate('Y-m-d H:i:s');
        $taxonomyVersion = 'ogpn-iab-inspired-v1';
        $hosting = null;
        if (!empty($a['ip_address'])) {
            $hosting = $hostingResolver->resolve((string)$a['ip_address']);
        }
        $a['eu_sovereignty_score'] = $complete
            ? Scoring::euSovereignty(array_merge($a, ['dependencies'=>$a['dependencies'] ?? [], 'unknown_dependencies'=>$a['unknown_dependencies'] ?? [], 'eu_member'=>$a['eu_member'] ?? false]), $hosting)
            : null;
        $dependencyGovernance = [
            'provider_count' => (int) ($a['dependency_provider_count'] ?? 0),
            'red_count' => (int) ($a['dependency_red_count'] ?? 0),
            'tracking_role_count' => (int) ($a['dependency_tracking_role_count'] ?? 0),
            'status' => $a['dependency_status'] ?? 'inconclusive',
        ];
        $values = [
            $domain, Domain::tld($domain), $status, $result['final_url'] ?? null,
            $result['default_language'] ?? null, $json($result['alternate_languages'] ?? []),
            $result['selected_language'] ?? null, $result['language_source'] ?? null,
            $result['language_confidence'] ?? null, $json($a), $now, $now,
            $a['country_code'] ?? null, (int) ($a['eu_member'] ?? 0), $a['tld_type'] ?? null,
            $json($a['tld_groups'] ?? []), $a['redirect_status'] ?? 'none', (int) ($a['redirect_count'] ?? 0),
            $a['category'] ?? 'unidentified', $a['category_status'] ?? 'unidentified',
            $a['category_source'] ?? 'homepage_insufficient_signals', (int) ($a['category_confidence'] ?? 0),
            $json($a['category_signals'] ?? []), $json($a['category_negative_signals'] ?? []),
            $a['category_tier2'] ?? null, (int) ($a['category_tier2_confidence'] ?? 0),
            $json($a['category_tier2_signals'] ?? []), $taxonomyVersion,
            $a['robots_status'] ?? null, (int) ($a['robots_blocks_everything'] ?? 0),
            $json($a['ai_bot_policy'] ?? []), $json($a['dependencies'] ?? []),
            $json($a['unknown_dependencies'] ?? []), $a['provider_reference_version'] ?? null,
            $a['provider_reference_sha256'] ?? null, (int) ($a['dependency_provider_count'] ?? 0),
            (int) ($a['dependency_red_count'] ?? 0), (int) ($a['dependency_tracking_role_count'] ?? 0),
            $json($dependencyGovernance), $json($a['consent_signals'] ?? []),
            $a['consent_friction_score_auto'] ?? null, (int) ($a['consent_review_needed'] ?? 0),
            (int) ($a['consent_cmp_count'] ?? 0), (int) ($a['consent_tracking_provider_count'] ?? 0),
            $json($a['digital_identity_providers'] ?? []), $json($a['review_platforms'] ?? []),
            $json($a['social_presence'] ?? []), $json($a['file_presence'] ?? []),
            $json($a['file_misplaced'] ?? []), $json($a['file_conflict'] ?? []),
            array_key_exists('tdm_reservation', $a) && $a['tdm_reservation'] !== null ? (int) $a['tdm_reservation'] : null,
            $a['tdm_policy_url'] ?? null, (int) ($a['domain_for_sale'] ?? 0),
            $a['domain_for_sale_source'] ?? null, $a['domain_for_sale_provider'] ?? null,
            (int) ($a['accessibility_statement_present'] ?? 0), (int) ($a['legal_notice_present'] ?? 0),
            (int) ($a['has_json_ld'] ?? 0), (int) ($a['has_microdata'] ?? 0),
            $a['generator'] ?? null, $a['server_header'] ?? null, $a['ip_address'] ?? null,
            $a['ssl_issuer'] ?? null, (int) ($a['foodtruck_mentions'] ?? 0),
            $a['ai_openness_score'] ?? null, $a['eu_sovereignty_score'] ?? null,
            '2.0.0-alpha5-consolidated', $quality, $a['access_status'] ?? 'inconclusive',
            $hosting['provider'] ?? null, $hosting['asn'] ?? null, $hosting['eu_status'] ?? null,
        ];
        $isUnreachable = $quality === 'unreachable' ? 1 : 0;
        $values[] = $isUnreachable;
        $values[] = $isUnreachable;
        $upsert->execute($values);
        $accepted++;

        $next = $quality === 'complete' ? time() + 2592000 : time() + 86400;
        $release->execute([gmdate('Y-m-d H:i:s', $next), $status ? 'http_' . $status : $quality, $domain, $worker['worker_id']]);
        $released += $release->rowCount();

        $history->execute([
            $domain, $worker['worker_id'], $status, $result['selected_language'] ?? null,
            $a['category'] ?? 'unidentified', $a['category_tier2'] ?? null,
            (int) ($a['category_confidence'] ?? 0), $quality, '2.0.0-alpha5-consolidated',
            (int) ($result['response_truncated'] ?? 0), (int) $complete,
            (int) ($a['dependency_provider_count'] ?? 0), (int) ($a['consent_cmp_count'] ?? 0),
            $a['robots_status'] ?? null, $a['eu_sovereignty_score'] ?? null,
            $a['redirect_status'] ?? 'none', (int) ($a['redirect_count'] ?? 0),
            (int) ($a['domain_for_sale'] ?? 0), $a['access_status'] ?? 'inconclusive',
            $taxonomyVersion, $hosting['provider'] ?? null, $hosting['asn'] ?? null, $hosting['eu_status'] ?? null, $json($result)
        ]);
    }
    $pdo->commit();
    Bootstrap::json(['ok' => true, 'ingested' => $accepted, 'queue_released' => $released]);
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
