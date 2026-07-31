<?php

declare(strict_types=1);

namespace Ogpn\Bot;

/**
 * Client HTTP minimal, réservé aux appels internes vers des points d'accès
 * fixes et de confiance (API Common Crawl, index.commoncrawl.org) — PAS pour
 * scanner des domaines arbitraires. Pour ça, voir Engine\Http, qui applique
 * les protections SSRF/DomainSafety nécessaires quand l'URL vient d'un
 * domaine externe non maîtrisé (une entrée de la file de scan).
 *
 * Ici l'URL est toujours construite à partir d'un hôte fixe codé en dur côté
 * appelant (CommonCrawl\Client, CommonCrawl\Seeder) — jamais depuis une
 * entrée utilisateur ou une donnée scannée — donc la validation SSRF par
 * saut n'est pas pertinente pour cet usage précis.
 */
final class Http
{
    /**
     * @return array{status: int, body: string}
     */
    public static function get(string $url, int $timeoutSeconds, int $maxBytes): array
    {
        $ch = curl_init($url);
        $body = '';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_HTTPHEADER => ['Accept: application/json, text/plain, */*'],
            CURLOPT_USERAGENT => 'OGPN-BOT/2.0 (+https://bot.ogpn.eu)',
            // Coupe le transfert dès que la taille maximale est dépassée, au
            // lieu de tout charger en mémoire puis de tronquer après coup.
            CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$body, $maxBytes): int {
                $body .= $chunk;
                return strlen($body) > $maxBytes ? -1 : strlen($chunk);
            },
        ]);

        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        // CURLE_WRITE_ERROR (23) correspond à l'arrêt volontaire ci-dessus
        // (dépassement de $maxBytes) — le contenu partiel reste exploitable,
        // ce n'est pas une vraie erreur réseau.
        if ($errno !== 0 && $errno !== CURLE_WRITE_ERROR) {
            return ['status' => 0, 'body' => ''];
        }

        return ['status' => $status, 'body' => $body];
    }
}
