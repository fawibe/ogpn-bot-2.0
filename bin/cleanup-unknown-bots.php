<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Ogpn\Bot\Bootstrap;
use Ogpn\Bot\Engine\Config;

/**
 * Nettoie rétroactivement unknown_ai_bot_groups déjà en base — les deux
 * correctifs du 2026-08-05 (RobotsTxt.php, Scanner.php) n'améliorent que
 * les FUTURS scans ; ce script rejoue la même logique sur ce qui est déjà
 * stocké, pour ne pas attendre le prochain passage naturel de chaque
 * domaine.
 *
 * Ce qui est nettoyé, par nom :
 *  1. Fragment de directive fusionné ("piplbot disallow: /" -> "piplbot")
 *     -- la directive elle-même est perdue (le robots.txt brut n'est pas
 *     conservé), seul le nom est récupérable ici.
 *  2. Tirets/espaces Unicode -> formes ASCII (perplexity‑user -> perplexity-user)
 *  3. Re-filtrage contre la liste actuelle des bots connus/bruit -- un nom
 *     nettoyé peut désormais correspondre à un bot déjà connu, et doit
 *     alors disparaître complètement du tableau (pas juste être renommé).
 *
 * SANS ÉCRITURE PAR DÉFAUT (mode simulation) -- affiche ce qui changerait
 * sans toucher la base. Ajouter --apply pour écrire réellement.
 *
 * Usage :
 *   php bin/cleanup-unknown-bots.php            (simulation, aucune écriture)
 *   php bin/cleanup-unknown-bots.php --apply     (écrit réellement)
 */

$apply = in_array('--apply', $argv, true);
$pdo = Bootstrap::pdo();

$known = array_map(strtolower(...), Config::AI_BOTS);
$noise = Config::KNOWN_NON_AI_USER_AGENTS;

function normalizeAgentName(string $agent): string
{
    $agent = preg_replace('/[\x{2010}-\x{2015}]/u', '-', $agent) ?? $agent;
    $agent = preg_replace('/[\x{00A0}\s]+/u', ' ', $agent) ?? $agent;
    // Suffixe de version -- même correctif que Scanner::normalizeAgentName(),
    // indispensable pour que "wget/1.8.1+cvs" matche bien le filtre de
    // bruit "wget" (comparaison exacte, pas de sous-chaîne).
    $agent = preg_replace('/\/[\d.+_-]+[a-z0-9.]*$/i', '', $agent) ?? $agent;

    return trim($agent);
}

function looksLikeParsingGarbage(string $agent): bool
{
    $lower = strtolower($agent);
    if (str_contains($lower, 'mozilla/') || str_contains($lower, 'compatible;')
        || str_contains($lower, 'windows nt') || str_contains($lower, 'x11;')
        || str_contains($lower, 'macintosh;')) {
        return true;
    }
    if (str_contains($lower, 'http://') || str_contains($lower, 'https://') || str_contains($lower, 'www.')) {
        return true;
    }
    if (mb_strlen($agent) > 60) {
        return true;
    }
    return false;
}

function cleanBotName(string $raw): string
{
    // Même motif que RobotsTxt::parse() -- fragment de directive fusionné
    if (preg_match('/^(.*?)\s+(disallow|allow)\s*:\s*.*$/i', $raw, $m) === 1) {
        $raw = trim($m[1]);
    }

    return normalizeAgentName($raw);
}

$offset = 0;
$batchSize = 500;
$domainsScanned = 0;
$domainsChanged = 0;
$namesBefore = [];
$namesAfter = [];
$afterCounts = []; // nom nettoyé -> nombre de domaines où il apparaît, pour l'export

echo $apply ? "MODE ÉCRITURE -- la base sera modifiée.\n" : "MODE SIMULATION -- aucune écriture (ajoute --apply pour écrire réellement).\n";

$updateStmt = $pdo->prepare('UPDATE v2_domains SET unknown_ai_bot_groups = ? WHERE domain = ?');

while (true) {
    $stmt = $pdo->prepare(
        'SELECT domain, unknown_ai_bot_groups FROM v2_domains
         WHERE unknown_ai_bot_groups IS NOT NULL AND JSON_LENGTH(unknown_ai_bot_groups) > 0
         ORDER BY domain LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, $batchSize, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        break;
    }

    foreach ($rows as $row) {
        $domainsScanned++;
        $original = json_decode((string) $row['unknown_ai_bot_groups'], true) ?: [];
        foreach ($original as $n) {
            $namesBefore[strtolower((string) $n)] = true;
        }

        $cleaned = [];
        foreach ($original as $name) {
            $name = cleanBotName((string) $name);
            if ($name === '') {
                continue;
            }
            $lower = strtolower($name);
            if (in_array($lower, $known, true) || in_array($lower, $noise, true)) {
                continue; // devenu reconnu/bruit après nettoyage -- disparaît complètement
            }
            if (looksLikeParsingGarbage($name)) {
                continue; // débris de parsing (chaîne User-Agent complète, URL embarquée, longueur excessive)
            }
            $cleaned[$lower] = $name;
        }
        $cleaned = array_values($cleaned);
        sort($cleaned);

        foreach ($cleaned as $n) {
            $namesAfter[strtolower($n)] = true;
            $afterCounts[$n] = ($afterCounts[$n] ?? 0) + 1;
        }

        if ($cleaned !== $original) {
            $domainsChanged++;
            if ($apply) {
                $updateStmt->execute([json_encode($cleaned, JSON_UNESCAPED_UNICODE), $row['domain']]);
            }
        }
    }

    $offset += $batchSize;
}

printf(
    "Terminé : %d domaine(s) examiné(s), %d modifié(s)%s.\n",
    $domainsScanned,
    $domainsChanged,
    $apply ? '' : ' (simulation -- rien écrit)'
);
printf("Noms distincts avant : %d -> après nettoyage : %d\n", count($namesBefore), count($namesAfter));

// Export complet, trié du moins fréquent au plus fréquent (c'est dans la
// longue traîne rare que se cache le plus de bruit résiduel, pas dans les
// noms qui reviennent souvent) -- pour revue exhaustive, pas juste un
// extrait de 50 lignes.
asort($afterCounts);
$exportPath = dirname(__DIR__).'/storage/runtime/unknown-bots-export.csv';
$fh = fopen($exportPath, 'w');
if ($fh !== false) {
    fputcsv($fh, ['nom', 'nombre_de_domaines']);
    foreach ($afterCounts as $name => $count) {
        fputcsv($fh, [$name, $count]);
    }
    fclose($fh);
    printf("Export complet (%d lignes, triées du moins fréquent au plus fréquent) : %s\n", count($afterCounts), $exportPath);
} else {
    fwrite(STDERR, "Attention : impossible d'écrire l'export dans $exportPath\n");
}
