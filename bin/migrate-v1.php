<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Ogpn\Bot\Bootstrap;

$mode = $argv[1] ?? '--dry-run';
$pdo = Bootstrap::pdo();
$has = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='domains'")->fetchColumn();
if (!$has) {
    fwrite(STDERR, "Table V1 domains absente.\n");
    exit(2);
}

foreach (['domains','domain_queue','v2_domains','v2_domain_queue'] as $table) {
    echo str_pad($table, 18) . ': ' . (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() . "\n";
}

if ($mode === '--dry-run') {
    $overlap = (int) $pdo->query('SELECT COUNT(*) FROM domains d INNER JOIN v2_domains v ON v.domain=d.domain')->fetchColumn();
    echo str_pad('overlap', 18) . ': ' . $overlap . "\n";
    exit;
}

if ($mode === '--verify') {
    $invalid = (int) $pdo->query("SELECT COUNT(*) FROM v2_domains WHERE domain='' OR tld='' ")->fetchColumn();
    $missing = (int) $pdo->query('SELECT COUNT(*) FROM domains d LEFT JOIN v2_domains v ON v.domain=d.domain WHERE v.domain IS NULL')->fetchColumn();
    echo "Vérification : " . ($invalid === 0 && $missing === 0 ? 'OK' : 'ERREUR') . "\n";
    echo "Domaines V1 absents de V2 : {$missing}\n";
    exit($invalid === 0 && $missing === 0 ? 0 : 1);
}

if ($mode !== '--execute') {
    fwrite(STDERR, "Mode inconnu. Utilisez --dry-run, --execute ou --verify.\n");
    exit(2);
}

$pdo->beginTransaction();
try {
    $pdo->exec("INSERT INTO v2_domain_queue(domain,tld,discovered_source,first_seen_at,last_seen_at,last_scanned_at,next_scan_at,last_scan_status)
        SELECT domain,tld,'v1_migration',COALESCE(discovered_at,created_at,UTC_TIMESTAMP()),COALESCE(last_seen_in_crawl_at,updated_at,UTC_TIMESTAMP()),last_scanned_at,next_scan_at,CASE WHEN last_scanned_at IS NULL THEN NULL ELSE 'migrated' END
        FROM domain_queue
        ON DUPLICATE KEY UPDATE first_seen_at=LEAST(v2_domain_queue.first_seen_at,VALUES(first_seen_at)),last_seen_at=GREATEST(v2_domain_queue.last_seen_at,VALUES(last_seen_at))");

    $pdo->exec("INSERT INTO v2_domains(
        domain,tld,http_status,final_url,default_language,alternate_languages,selected_language,language_source,language_confidence,analysis_json,
        first_scanned_at,last_scanned_at,country_code,eu_member,tld_type,tld_groups,redirect_status,redirect_count,
        category,category_status,category_source,category_confidence,category_signals,category_negative_signals,category_tier2,category_tier2_confidence,category_tier2_signals,taxonomy_version,
        robots_status,robots_blocks_everything,ai_bot_policy,dependencies,unknown_dependencies,provider_reference_version,provider_reference_sha256,
        dependency_provider_count,dependency_red_count,dependency_tracking_role_count,dependency_governance,consent_signals,consent_friction_score_auto,consent_review_needed,
        consent_cmp_count,consent_tracking_provider_count,digital_identity_providers,review_platforms,social_presence,file_presence,file_misplaced,file_conflict,
        tdm_reservation,tdm_policy_url,domain_for_sale,domain_for_sale_source,domain_for_sale_provider,accessibility_statement_present,legal_notice_present,
        has_json_ld,has_microdata,generator,server_header,ip_address,ssl_issuer,foodtruck_mentions,ai_openness_score,eu_sovereignty_score,scan_engine_version,data_quality_status,access_status,
        unreachable_streak,first_unreachable_at,confirmed_unreachable
    )
    SELECT
        domain,COALESCE(tld,SUBSTRING_INDEX(domain,'.',-1)),http_status,analysis_url,default_language,alternate_languages,COALESCE(default_language,'unidentified'),'v1_migration',NULL,
        JSON_OBJECT('migrated_from','v1','legacy_id',id),COALESCE(created_at,last_scanned_at,UTC_TIMESTAMP()),COALESCE(last_scanned_at,updated_at,UTC_TIMESTAMP()),
        country_code,eu_member,tld_type,tld_groups,CASE WHEN redirect_count>0 THEN 'redirect' ELSE 'none' END,redirect_count,
        category,category_status,category_source,category_confidence,category_signals,category_negative_signals,category_tier2,category_tier2_confidence,category_tier2_signals,'ogpn-iab-inspired-v1',
        robots_status,robots_blocks_everything,ai_bot_policy,dependencies,unknown_dependencies,provider_reference_version,provider_reference_sha256,
        dependency_provider_count,dependency_red_count,dependency_tracking_role_count,dependency_governance,consent_signals,consent_friction_score_auto,consent_review_needed,
        consent_cmp_count,consent_tracking_provider_count,digital_identity_providers,review_platforms,social_presence,file_presence,file_misplaced,file_conflict,
        tdm_reservation,tdm_policy_url,domain_for_sale,domain_for_sale_source,domain_for_sale_provider,accessibility_statement_present,legal_notice_present,
        has_json_ld,has_microdata,generator,server_header,ip_address,ssl_issuer,foodtruck_mentions,ai_openness_score,eu_sovereignty_score,'v1-migrated',
        CASE WHEN http_status BETWEEN 200 AND 399 THEN 'legacy_complete' WHEN confirmed_unreachable=1 THEN 'unreachable' ELSE 'legacy_partial' END,
        CASE WHEN confirmed_unreachable=1 THEN 'unreachable' WHEN http_status IN (401,403,407,429) THEN 'blocked' WHEN http_status BETWEEN 200 AND 399 THEN 'accessible' ELSE 'inconclusive' END,
        COALESCE(unreachable_streak,0),first_unreachable_at,COALESCE(confirmed_unreachable,0)
    FROM domains
    ON DUPLICATE KEY UPDATE
        last_scanned_at=GREATEST(v2_domains.last_scanned_at,VALUES(last_scanned_at)),
        unreachable_streak=GREATEST(v2_domains.unreachable_streak,VALUES(unreachable_streak)),
        first_unreachable_at=COALESCE(v2_domains.first_unreachable_at,VALUES(first_unreachable_at)),
        confirmed_unreachable=GREATEST(v2_domains.confirmed_unreachable,VALUES(confirmed_unreachable)),
        category=CASE WHEN v2_domains.category='unidentified' THEN VALUES(category) ELSE v2_domains.category END,
        category_tier2=COALESCE(v2_domains.category_tier2,VALUES(category_tier2)),
        dependencies=COALESCE(v2_domains.dependencies,VALUES(dependencies)),
        unknown_dependencies=COALESCE(v2_domains.unknown_dependencies,VALUES(unknown_dependencies)),
        consent_signals=COALESCE(v2_domains.consent_signals,VALUES(consent_signals)),
        ai_bot_policy=COALESCE(v2_domains.ai_bot_policy,VALUES(ai_bot_policy)),
        alternate_languages=COALESCE(v2_domains.alternate_languages,VALUES(alternate_languages)),
        domain_for_sale=GREATEST(v2_domains.domain_for_sale,VALUES(domain_for_sale))");

    $pdo->commit();
    echo "Migration V1 terminée sans suppression.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
