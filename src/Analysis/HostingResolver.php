<?php

declare(strict_types=1);

namespace Ogpn\Bot\Analysis;

/**
 * Résout l'hébergeur réel d'une IP via deux bases MaxMind locales (GeoLite2-ASN
 * pour l'organisation, GeoLite2-Country pour le pays réel de l'IP — toutes
 * deux en ressource partagée, jamais téléchargées par ce script, fournies
 * séparément par l'infrastructure).
 *
 * Le croisement avec GeoLite2-Country corrige un vrai problème méthodologique
 * repéré le 2026-08-02 : un simple appariement par nom d'organisation ASN
 * ("OVH" -> vert) suppose à tort qu'un hébergeur donné n'opère que dans un
 * seul pays — or OVH, comme la plupart des grands hébergeurs, a des
 * datacenters hors UE (Canada, Singapour, Australie...). Le pays réel de
 * l'IP prévaut désormais sur le simple nom d'organisation quand les deux
 * sont disponibles.
 *
 * Dégrade toujours proprement : si la bibliothèque GeoIp2 n'est pas
 * installée, ou qu'un des deux fichiers de base n'existe pas, les champs
 * correspondants renvoient simplement null plutôt que de faire échouer tout
 * le traitement — les deux bases sont indépendantes l'une de l'autre.
 */
final class HostingResolver
{
    /**
     * Classification connue des hébergeurs par nom d'organisation ASN —
     * recherche par sous-chaîne, insensible à la casse. Même esprit que
     * Config::DEPENDENCY_PROVIDERS côté bot : liste non exhaustive, à
     * enrichir au fil du temps. Un ASN non reconnu renvoie eu_status=null
     * (pas "gris" par défaut — on ne devine pas, on dit "inconnu").
     *
     * 'cloud_act' : la société mère est-elle soumise au CLOUD Act américain
     * (juridiction sur les données qu'elle contrôle, indépendamment d'où
     * elles sont physiquement hébergées) ? Volontairement simple — reflète
     * la nationalité de l'organisation, pas une analyse juridictionnelle
     * fine. Note : ce critère n'est PAS un simple alias de eu_status='rouge'
     * — un hébergeur non-UE mais non-américain (suisse, britannique...)
     * serait rouge sans être soumis au CLOUD Act. D'où un champ séparé,
     * pas déduit de eu_status.
     */
    private const KNOWN_PROVIDERS = [
        ['pattern' => 'ovh', 'eu_status' => 'vert', 'cloud_act' => false],
        ['pattern' => 'scaleway', 'eu_status' => 'vert', 'cloud_act' => false],
        ['pattern' => 'online s.a.s', 'eu_status' => 'vert', 'cloud_act' => false],
        ['pattern' => 'hetzner', 'eu_status' => 'vert', 'cloud_act' => false],
        ['pattern' => 'infomaniak', 'eu_status' => 'vert', 'cloud_act' => false],
        ['pattern' => 'ionos', 'eu_status' => 'vert', 'cloud_act' => false],
        ['pattern' => '1&1', 'eu_status' => 'vert', 'cloud_act' => false],
        ['pattern' => 'combell', 'eu_status' => 'vert', 'cloud_act' => false],
        ['pattern' => 'hetzner online', 'eu_status' => 'vert', 'cloud_act' => false],
        ['pattern' => 'amazon', 'eu_status' => 'rouge', 'cloud_act' => true],
        ['pattern' => 'aws', 'eu_status' => 'rouge', 'cloud_act' => true],
        ['pattern' => 'google', 'eu_status' => 'rouge', 'cloud_act' => true],
        ['pattern' => 'microsoft', 'eu_status' => 'rouge', 'cloud_act' => true],
        ['pattern' => 'azure', 'eu_status' => 'rouge', 'cloud_act' => true],
        ['pattern' => 'digitalocean', 'eu_status' => 'rouge', 'cloud_act' => true],
        ['pattern' => 'digital ocean', 'eu_status' => 'rouge', 'cloud_act' => true],
        ['pattern' => 'cloudflare', 'eu_status' => 'rouge', 'cloud_act' => true],
        ['pattern' => 'linode', 'eu_status' => 'rouge', 'cloud_act' => true],
        ['pattern' => 'akamai', 'eu_status' => 'rouge', 'cloud_act' => true],
        ['pattern' => 'fastly', 'eu_status' => 'rouge', 'cloud_act' => true],
        ['pattern' => 'godaddy', 'eu_status' => 'rouge', 'cloud_act' => true],
        ['pattern' => 'oracle', 'eu_status' => 'rouge', 'cloud_act' => true],
    ];

    private mixed $asnReader = null;
    private mixed $countryReader = null;
    private bool $asnAvailable = false;
    private bool $countryAvailable = false;

    public function __construct(?string $asnDbPath, ?string $countryDbPath = null)
    {
        if ($asnDbPath !== null && is_file($asnDbPath) && class_exists(\GeoIp2\Database\Reader::class)) {
            try {
                $this->asnReader = new \GeoIp2\Database\Reader($asnDbPath);
                $this->asnAvailable = true;
            } catch (\Throwable) {
                $this->asnAvailable = false;
            }
        }

        if ($countryDbPath !== null && is_file($countryDbPath) && class_exists(\GeoIp2\Database\Reader::class)) {
            try {
                $this->countryReader = new \GeoIp2\Database\Reader($countryDbPath);
                $this->countryAvailable = true;
            } catch (\Throwable) {
                $this->countryAvailable = false;
            }
        }
    }

    /** @return array{provider: string, asn: int, eu_status: ?string, country: ?string, cloud_act_exposure: ?bool}|null */
    public function resolve(string $ipAddress): ?array
    {
        if (!$this->asnAvailable || $this->asnReader === null) {
            return null;
        }

        try {
            $record = $this->asnReader->asn($ipAddress);
        } catch (\Throwable) {
            return null; // IP non trouvée dans la base, ou IP invalide — pas une erreur fatale
        }

        $organization = $record->autonomousSystemOrganization ?? null;
        $asn = $record->autonomousSystemNumber ?? null;

        if ($organization === null || $asn === null) {
            return null;
        }

        $matched = $this->matchProvider($organization);
        $country = $this->resolveCountry($ipAddress);

        // Le pays réel prévaut sur le simple nom d'organisation quand les
        // deux sont disponibles — corrige le cas "OVH au Canada" : le motif
        // dirait 'vert', mais un pays hors UE effectivement résolu ramène le
        // statut à 'rouge'. Si le motif ne reconnaît rien du tout, on retombe
        // sur le pays seul (mieux que rien plutôt que null).
        $euStatus = $matched['eu_status'] ?? null;
        if ($country !== null) {
            $countryIsEu = \Ogpn\Bot\Engine\Config::isEuMember($country);
            if ($euStatus === 'vert' && !$countryIsEu) {
                $euStatus = 'rouge';
            } elseif ($euStatus === null) {
                $euStatus = $countryIsEu ? 'vert' : 'rouge';
            }
        }

        return [
            'provider' => $organization,
            'asn' => $asn,
            'eu_status' => $euStatus,
            'country' => $country,
            'cloud_act_exposure' => $matched['cloud_act'] ?? null,
        ];
    }

    private function resolveCountry(string $ipAddress): ?string
    {
        if (!$this->countryAvailable || $this->countryReader === null) {
            return null;
        }

        try {
            $record = $this->countryReader->country($ipAddress);
        } catch (\Throwable) {
            return null;
        }

        return $record->country->isoCode ?? null;
    }

    /** @return array{eu_status: ?string, cloud_act: ?bool}|null */
    private function matchProvider(string $organization): ?array
    {
        $lower = strtolower($organization);
        foreach (self::KNOWN_PROVIDERS as $entry) {
            if (str_contains($lower, $entry['pattern'])) {
                return $entry;
            }
        }

        return null; // hébergeur non reconnu par son nom — honnête plutôt que deviner
    }
}
