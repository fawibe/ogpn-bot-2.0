<?php
declare(strict_types=1);

namespace Ogpn\Bot\Scanner;

use Ogpn\Bot\Domain;
use Ogpn\Bot\Engine\Http;
use Ogpn\Bot\Engine\Scanner as EngineScanner;

final class SiteScanner
{
    public static function scan(string $domain): array
    {
        $batch = self::scanBatch([$domain]);
        $normalized = Domain::normalizeHost($domain)
            ?? throw new \RuntimeException('Domaine invalide');
        return $batch[$normalized] ?? throw new \RuntimeException('Aucun résultat de scan produit.');
    }

    /** @param string[] $domains @return array<string,array> */
    public static function scanBatch(array $domains): array
    {
        $normalized = [];
        foreach ($domains as $domain) {
            $value = Domain::normalizeHost((string) $domain);
            if ($value !== null) $normalized[] = $value;
        }
        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) return [];

        $engine = new EngineScanner(new Http());
        $raw = $engine->scanBatch($normalized);
        $mapped = [];
        foreach ($raw as $domain => $result) {
            $mapped[$domain] = ScanResultMapper::map($result);
        }
        return $mapped;
    }
}
