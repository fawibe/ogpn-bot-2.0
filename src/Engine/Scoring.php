<?php

declare(strict_types=1);

namespace Ogpn\Bot\Engine;

/**
 * Scoring — VERSION 1, DÉLIBÉRÉMENT SIMPLE. Ces formules n'ont jamais été
 * validées ni discutées en détail (voir notes de conception du projet) —
 * elles servent de point de départ concret, pas de résultat final. À
 * affiner une fois qu'on aura de vraies données en volume pour juger si
 * elles produisent des scores qui font sens.
 */
final class Scoring
{
    /**
     * Score d'ouverture IA, 0-100. Pondération actuelle (arbitraire, v1) :
     *  - 60% : proportion de bots IA suivis explicitement autorisés
     *  - 25% : proportion de fichiers RMF présents (llms.txt, ai.txt,
     *          ai-policy.json, tdmrep.json)
     *  - 15% : bonus si une politique TDM est explicitement déclarée
     *          (peu importe son sens — le fait de la déclarer est déjà
     *          un signal de maturité)
     */
    public static function aiOpenness(array $result): ?int
    {
        if (($result['robots_status'] ?? null) === 'unreachable') {
            return null; // pas de données exploitables
        }

        $botPolicy = $result['ai_bot_policy'] ?? [];
        $botScore = 0.0;
        if (is_array($botPolicy) && count($botPolicy) > 0) {
            $allowed = count(array_filter($botPolicy, static fn ($status) => $status === 'allowed'));
            $botScore = ($allowed / count($botPolicy)) * 60;
        }

        $filePresence = $result['file_presence'] ?? [];
        $fileScore = 0.0;
        if (is_array($filePresence) && count($filePresence) > 0) {
            $present = count(array_filter($filePresence));
            $fileScore = ($present / count($filePresence)) * 25;
        }

        $tdmScore = array_key_exists('tdm_reservation', $result) && $result['tdm_reservation'] !== null ? 15 : 0;

        return (int) round(min(100, $botScore + $fileScore + $tdmScore));
    }

    /**
     * Score de souveraineté EU, 0-100. Pondération actuelle (arbitraire, v1) :
     *  - jusqu'à 60% : moyenne des dépendances détectées (vert=1, gris/jaune=0.5, rouge=0)
     *  - jusqu'à 20% : statut de l'hébergeur réel, si résolu (vert=20, gris/jaune=10, rouge=0)
     *  - jusqu'à 20% : bonus si le domaine est dans un pays membre de l'UE
     *  - les dépendances inconnues ne sont pas pénalisées comme rouges ;
     *    elles plafonnent seulement le score pour signaler l'incertitude.
     *
     * @param array{provider: string, asn: int, eu_status: ?string}|null $hosting
     */
    public static function euSovereignty(array $result, ?array $hosting): ?int
    {
        $dependencies = $result['dependencies'] ?? [];
        $depScore = null;
        if (is_array($dependencies) && count($dependencies) > 0) {
            $sum = 0.0;
            foreach ($dependencies as $dep) {
                $sum += match ($dep['eu_status'] ?? null) {
                    'vert' => 1.0,
                    'gris', 'jaune' => 0.5,
                    'rouge' => 0.0,
                    default => 0.0,
                };
            }
            $depScore = ($sum / count($dependencies)) * 60;
        }

        $hostingScore = match ($hosting['eu_status'] ?? null) {
            'vert' => 20,
            'gris', 'jaune' => 10,
            'rouge' => 0,
            default => null, // hébergeur non résolu — pas de point ni de pénalité
        };

        $euMemberScore = !empty($result['eu_member']) ? 20 : 0;

        // Si on n'a NI dépendances NI hébergeur résolu, le score ne serait
        // basé que sur eu_member seul — trop peu pour être significatif,
        // on préfère renvoyer null (pas de donnée) plutôt qu'un faux 20/100.
        if ($depScore === null && $hostingScore === null) {
            return null;
        }

        $score = (int) round(min(100, ($depScore ?? 0) + ($hostingScore ?? 0) + $euMemberScore));

        $unknownDependencies = $result['unknown_dependencies'] ?? [];
        $unknownCount = is_array($unknownDependencies) ? count($unknownDependencies) : 0;
        $knownCount = is_array($dependencies) ? count($dependencies) : 0;

        if ($unknownCount > 0) {
            $score = min($score, $unknownCount >= ($knownCount + 3) ? 80 : 90);
        }

        return $score;
    }
}
