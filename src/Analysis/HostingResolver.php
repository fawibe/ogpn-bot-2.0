<?php

declare(strict_types=1);

namespace Ogpn\Bot\Analysis;

/**
 * Résout l'hébergeur réel d'une IP via une base ASN locale (MaxMind
 * GeoLite2-ASN, en ressource partagée — voir notes de conception du
 * projet : jamais téléchargée par ce script lui-même, jamais embarquée
 * dans le dépôt, fournie séparément par l'infrastructure).
 *
 * Dégrade toujours proprement : si la bibliothèque GeoIp2 n'est pas
 * installée (composer install jamais lancé) ou si le fichier de base
 * n'existe pas à l'emplacement indiqué, resolve() renvoie simplement null
 * plutôt que de faire échouer tout le traitement.
 */
final class HostingResolver
{
    /**
     * Classification connue des hébergeurs par nom d'organisation ASN —
     * recherche par sous-chaîne, insensible à la casse. Même esprit que
     * Config::DEPENDENCY_PROVIDERS côté bot : liste non exhaustive, à
     * enrichir au fil du temps. Un ASN non reconnu renvoie eu_status=null
     * (pas "gris" par défaut — on ne devine pas, on dit "inconnu").
     */
    private const KNOWN_PROVIDERS = [
        ['pattern' => 'ovh', 'eu_status' => 'vert'],
        ['pattern' => 'scaleway', 'eu_status' => 'vert'],
        ['pattern' => 'online s.a.s', 'eu_status' => 'vert'],
        ['pattern' => 'hetzner', 'eu_status' => 'vert'],
        ['pattern' => 'infomaniak', 'eu_status' => 'vert'],
        ['pattern' => 'ionos', 'eu_status' => 'vert'],
        ['pattern' => '1&1', 'eu_status' => 'vert'],
        ['pattern' => 'combell', 'eu_status' => 'vert'],
        ['pattern' => 'hetzner online', 'eu_status' => 'vert'],
        ['pattern' => 'amazon', 'eu_status' => 'rouge'],
        ['pattern' => 'aws', 'eu_status' => 'rouge'],
        ['pattern' => 'google', 'eu_status' => 'rouge'],
        ['pattern' => 'microsoft', 'eu_status' => 'rouge'],
        ['pattern' => 'azure', 'eu_status' => 'rouge'],
        ['pattern' => 'digitalocean', 'eu_status' => 'rouge'],
        ['pattern' => 'digital ocean', 'eu_status' => 'rouge'],
        ['pattern' => 'cloudflare', 'eu_status' => 'rouge'],
        ['pattern' => 'linode', 'eu_status' => 'rouge'],
        ['pattern' => 'akamai', 'eu_status' => 'rouge'],
        ['pattern' => 'fastly', 'eu_status' => 'rouge'],
        ['pattern' => 'godaddy', 'eu_status' => 'rouge'],
        ['pattern' => 'oracle', 'eu_status' => 'rouge'],
    ];

    private mixed $reader = null;
    private bool $available = false;

    public function __construct(?string $maxmindDbPath)
    {
        if ($maxmindDbPath === null || !is_file($maxmindDbPath) || !class_exists(\GeoIp2\Database\Reader::class)) {
            // Dégradation propre : pas de bibliothèque GeoIp2 installée
            // (composer install jamais lancé), ou fichier de base absent —
            // resolve() renverra toujours null, sans jamais planter.
            return;
        }

        try {
            $this->reader = new \GeoIp2\Database\Reader($maxmindDbPath);
            $this->available = true;
        } catch (\Throwable) {
            $this->available = false;
        }
    }

    /** @return array{provider: string, asn: int, eu_status: ?string}|null */
    public function resolve(string $ipAddress): ?array
    {
        if (!$this->available || $this->reader === null) {
            return null;
        }

        try {
            $record = $this->reader->asn($ipAddress);
        } catch (\Throwable) {
            return null; // IP non trouvée dans la base, ou IP invalide — pas une erreur fatale
        }

        $organization = $record->autonomousSystemOrganization ?? null;
        $asn = $record->autonomousSystemNumber ?? null;

        if ($organization === null || $asn === null) {
            return null;
        }

        return [
            'provider' => $organization,
            'asn' => $asn,
            'eu_status' => $this->classifyProvider($organization),
        ];
    }

    private function classifyProvider(string $organization): ?string
    {
        $lower = strtolower($organization);
        foreach (self::KNOWN_PROVIDERS as $entry) {
            if (str_contains($lower, $entry['pattern'])) {
                return $entry['eu_status'];
            }
        }

        return null; // hébergeur non reconnu — honnête plutôt que deviner
    }
}
