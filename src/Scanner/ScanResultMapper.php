<?php
declare(strict_types=1);

namespace Ogpn\Bot\Scanner;

use Ogpn\Bot\Domain;
use Ogpn\Bot\Engine\ScanResult;
use Ogpn\Bot\Engine\Scoring;

final class ScanResultMapper
{
    public const ENGINE_VERSION = '2.0.0-alpha5-consolidated';
    public const TAXONOMY_VERSION = 'ogpn-iab-inspired-v1.1';

    public static function map(ScanResult $scan): array
    {
        $raw = [];
        foreach (get_object_vars($scan) as $key => $value) {
            $raw[self::snake($key)] = $value;
        }

        $domain = Domain::normalizeHost((string) $raw['domain'])
            ?? throw new \RuntimeException('Domaine invalide dans le résultat.');
        $httpStatus = $raw['http_status'] ?? null;
        $blocked = in_array($httpStatus, [401, 403, 407, 429], true);
        $analysisComplete = $httpStatus !== null
            && $httpStatus >= 200 && $httpStatus < 300
            && !$blocked
            && ($raw['error'] ?? null) === null;

        $dependencies = is_array($raw['dependencies'] ?? null) ? $raw['dependencies'] : [];
        $consentSignals = is_array($raw['consent_signals'] ?? null) ? $raw['consent_signals'] : [];
        $analysis = [
            'analysis_url' => $raw['analysis_url'] ?? null,
            'analysis_source' => $raw['analysis_source'] ?? 'missing',
            'redirect_status' => ((int) ($raw['redirect_count'] ?? 0)) > 0 ? 'redirect' : 'none',
            'redirect_count' => (int) ($raw['redirect_count'] ?? 0),
            'analysis_complete' => $analysisComplete,
            'access_status' => $blocked ? 'blocked' : ($analysisComplete ? 'accessible' : 'inconclusive'),
            'category' => $raw['category'] ?? 'unidentified',
            'category_status' => $raw['category_status'] ?? 'unidentified',
            'category_source' => $raw['category_source'] ?? 'homepage_insufficient_signals',
            'category_confidence' => (int) ($raw['category_confidence'] ?? 0),
            'category_signals' => $raw['category_signals'] ?? [],
            'category_negative_signals' => $raw['category_negative_signals'] ?? [],
            'category_tier2' => $raw['category_tier2'] ?? null,
            'category_tier2_confidence' => (int) ($raw['category_tier2_confidence'] ?? 0),
            'category_tier2_signals' => $raw['category_tier2_signals'] ?? [],
            'taxonomy_version' => self::TAXONOMY_VERSION,
            'dependencies' => $dependencies,
            'unknown_dependencies' => $raw['unknown_dependencies'] ?? [],
            'provider_reference_version' => $raw['provider_reference_version'] ?? null,
            'provider_reference_sha256' => $raw['provider_reference_sha256'] ?? null,
            'dependency_provider_count' => count($dependencies),
            'dependency_red_count' => count(array_filter($dependencies, static fn(array $d): bool => ($d['eu_status'] ?? null) === 'rouge')),
            'dependency_tracking_role_count' => count(array_filter($dependencies, static fn(array $d): bool => !empty($d['tracking_governance_role']))),
            'dependency_status' => $analysisComplete ? 'complete' : 'inconclusive',
            'consent_signals' => $consentSignals,
            'consent_friction_score_auto' => $consentSignals['consent_friction_score_auto'] ?? null,
            'consent_review_needed' => !empty($consentSignals['human_review_needed']),
            'consent_cmp_count' => count($consentSignals['cmp_providers'] ?? []),
            'consent_tracking_provider_count' => count($consentSignals['advertising_trackers'] ?? []),
            'robots_status' => $raw['robots_status'] ?? null,
            'robots_blocks_everything' => !empty($raw['robots_blocks_everything']),
            'ai_bot_policy' => $raw['ai_bot_policy'] ?? [],
            'unknown_ai_bot_groups' => $raw['unknown_ai_bot_groups'] ?? [],
            'file_presence' => $raw['file_presence'] ?? [],
            'file_misplaced' => $raw['file_misplaced'] ?? [],
            'file_conflict' => $raw['file_conflict'] ?? [],
            'tdm_reservation' => $raw['tdm_reservation'] ?? null,
            'tdm_policy_url' => $raw['tdm_policy_url'] ?? null,
            'domain_for_sale' => !empty($raw['domain_for_sale']),
            'domain_for_sale_source' => $raw['domain_for_sale_source'] ?? null,
            'domain_for_sale_provider' => $raw['domain_for_sale_provider'] ?? null,
            'accessibility_statement_present' => !empty($raw['accessibility_statement_present']),
            'legal_notice_present' => !empty($raw['legal_notice_present']),
            'has_json_ld' => !empty($raw['has_json_ld']),
            'has_microdata' => !empty($raw['has_microdata']),
            'generator' => $raw['generator'] ?? null,
            'server_header' => $raw['server_header'] ?? null,
            'http_version' => $raw['http_version'] ?? null,
            'ip_address' => $raw['ip_address'] ?? null,
            'ssl_issuer' => $raw['ssl_issuer'] ?? null,
            'digital_identity_providers' => $raw['digital_identity_providers'] ?? [],
            'review_platforms' => $raw['review_platforms'] ?? [],
            'social_presence' => $raw['social_presence'] ?? [],
            'foodtruck_mentions' => (int) ($raw['foodtruck_mentions'] ?? 0),
            'country_code' => $raw['country_code'] ?? null,
            'eu_member' => !empty($raw['eu_member']),
            'tld' => $raw['tld'] ?? Domain::tld($domain),
            'tld_type' => $raw['tld_type'] ?? null,
            'tld_groups' => $raw['tld_groups'] ?? [],
        ];

        $scoreInput = array_merge($raw, $analysis);
        $analysis['ai_openness_score'] = $analysisComplete ? Scoring::aiOpenness($scoreInput) : null;
        $analysis['eu_sovereignty_score'] = $analysisComplete ? Scoring::euSovereignty($scoreInput, null) : null;
        $analysis['eu_sovereignty_score_status'] = $analysis['eu_sovereignty_score'] === null ? 'inconclusive' : 'complete';

        return [
            'domain' => $domain,
            'http_status' => $httpStatus,
            'final_url' => $raw['analysis_url'] ?? ('https://' . $domain . '/'),
            'redirected' => ((int) ($raw['redirect_count'] ?? 0)) > 0,
            'redirects' => [],
            'default_language' => $raw['default_language'] ?? null,
            'alternate_languages' => $raw['alternate_languages'] ?? [],
            'selected_language' => $raw['default_language'] ?? null,
            'language_source' => ($raw['default_language'] ?? null) !== null ? 'site_signals' : 'unidentified',
            'language_confidence' => ($raw['default_language'] ?? null) !== null ? 0.9 : 0.0,
            'response_truncated' => false,
            'response_bytes' => null,
            'analysis' => $analysis,
            'scanned_at' => gmdate('c'),
            'error' => $raw['error'] ?? null,
        ];
    }

    private static function snake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}
