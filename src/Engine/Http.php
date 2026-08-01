<?php

declare(strict_types=1);

namespace Ogpn\Bot\Engine;

/**
 * Couche réseau du bot OGPN.
 *
 * Principes retenus (voir notes de conception du projet) :
 *  - User-Agent transparent, avec URL de contact — jamais de mimétisme navigateur.
 *  - Timeouts stricts et courts : un domaine lent ne doit jamais plomber tout le batch.
 *  - GET uniquement (HEAD ne renvoie jamais le corps dont on a besoin) — mais un GET
 *    "intelligent" via Range pour la page HTML, afin de ne récupérer que le <head>.
 *  - Parallélisme interne à un domaine (curl_multi), mais nombre de domaines traités
 *    en parallèle volontairement plafonné, pour rester raisonnable sur un hébergement
 *    mutualisé (ou un runner CI partagé) plutôt que de viser la vitesse brute.
 */
class Http
{
    /** User-Agent transparent, avec contact — jamais de mimétisme navigateur. */
    public const USER_AGENT = 'OgpnBot/1.0 (+https://ogpn.eu/bot; contact@ogpn.eu)';

    /** Simple nom du bot, tel qu'il doit apparaître dans les lignes "User-agent:" de robots.txt. */
    public const USER_AGENT_TOKEN = 'OgpnBot';

    /**
     * Taille de la fenêtre récupérée pour la page d'accueil, via Range.
     * 150 Ko plutôt que 16 Ko : comme rien du HTML n'est jamais stocké (voir
     * notes de conception — seuls les signaux dérivés le sont), il n'y a
     * aucune contrainte de poids de stockage à ce choix. Le seul coût réel
     * est le temps de lecture, déjà négligeable pour la quasi-totalité des
     * sites (le timeout reste le vrai facteur limitant, pas la taille
     * demandée) — et cette fenêtre plus large augmente les chances
     * d'attraper les liens sociaux souvent situés en pied de page.
     */
    public const HTML_HEAD_RANGE_BYTES = 150000;

    /**
     * Plafond de taille appliqué à TOUTE requête, quel que soit le fichier —
     * protection contre une réponse anormalement volumineuse (site mal
     * configuré, ou pire, une ressource inattendue atteinte par erreur).
     * Généreux par rapport à la taille réelle d'un robots.txt/RMF normal
     * (quelques Ko), pour ne jamais couper un fichier légitime même verbeux.
     */
    private const MAX_BODY_BYTES = 1_048_576; // 1 Mo

    /** Timeouts stricts — un domaine mort ne doit jamais plomber le batch. */
    private const CONNECT_TIMEOUT_S = 2;
    private const TOTAL_TIMEOUT_S = 3;

    /**
     * Nombre maximum de sauts suivis pour une requête avec
     * RequestSpec::$followRedirects (choix de langue root -> /fr, etc.).
     * 3 couvre largement les cas réels (souvent 1 seul saut) sans laisser un
     * domaine mal configuré consommer un temps disproportionné du batch.
     */
    private const MAX_REDIRECTS = 3;

    /**
     * Nombre de domaines traités en parallèle simultanément (throttle
     * volontaire). Passé de 4 à 12 pour réduire le temps par lot — un
     * runner GitHub Actions a largement la bande passante pour ça. À
     * surveiller après ce changement : si le taux de domaines "injoignable"
     * augmente sensiblement (voir scan.yml), ça peut vouloir dire que
     * certains domaines/hébergeurs répondent mal à une charge plus
     * concurrente et qu'il faut redescendre cette valeur, pas forcément un
     * souci réseau généralisé.
     *
     * Configurable par instance depuis le 2026-08-01 (auparavant figé pour
     * tous les workers, y compris ceux en capacité "low" sur hébergement
     * mutualisé contraint — capacities.*.max_concurrency existait déjà en
     * config mais n'était branché nulle part). Même prudence qu'au-dessus :
     * ne pas monter au-delà de 12 sans validation réelle, cette valeur a
     * déjà été éprouvée, une plus haute non.
     */
    private readonly int $maxConcurrentDomains;

    public function __construct(int $maxConcurrentDomains = 12)
    {
        $this->maxConcurrentDomains = max(1, $maxConcurrentDomains);
    }

    /** @var array<int, string> Buffer manuel par handle (spl_object_id => contenu accumulé), voir buildCurlHandle(). */
    private array $buffers = [];

    /**
     * Récupère un lot de requêtes en parallèle, plafonné à $maxConcurrentDomains
     * lots de requêtes "actifs" en même temps.
     *
     * @param array<string, RequestSpec[]> $requestsByDomain Domaine => liste de requêtes à exécuter pour lui.
     * @return array<string, array<string, FetchResult>> Domaine => (clé de requête => résultat).
     */
    public function fetchBatch(array $requestsByDomain): array
    {
        $results = [];
        $domainChunks = array_chunk(
            array_keys($requestsByDomain),
            $this->maxConcurrentDomains,
            preserve_keys: true,
        );

        foreach ($domainChunks as $domainsInWave) {
            $wave = [];
            foreach ($domainsInWave as $domain) {
                $wave[$domain] = $requestsByDomain[$domain];
            }
            $results += $this->fetchWave($wave);
        }

        return $results;
    }

    /**
     * Exécute réellement en parallèle (curl_multi) toutes les requêtes de tous les
     * domaines d'une vague. Appelé uniquement avec un nombre de domaines déjà
     * plafonné par fetchBatch().
     *
     * @param array<string, RequestSpec[]> $requestsByDomain
     * @return array<string, array<string, FetchResult>>
     */
    private function fetchWave(array $requestsByDomain): array
    {
        /** @var array<string, array<string, FetchResult>> $results */
        $results = [];
        /** @var array<string, array<string, RequestSpec>> $pending */
        $pending = $requestsByDomain;
        /** @var array<string, array<string, string[]>> $visitedUrls Domaine => clé => URLs déjà traversées pour cette clé (détection de boucle). */
        $visitedUrls = [];
        /** @var array<string, array<string, array<int, array{url: string, status: int}>>> $chains */
        $chains = [];

        // Boucle de sauts : un round curl_multi par saut, mais seules les
        // entrées encore "en redirection" (3xx + followRedirects + cible
        // sûre) repartent au tour suivant — tout le reste sort dès son
        // premier round, donc aucun coût pour l'immense majorité des
        // requêtes (robots.txt, RMF, pages HTML sans redirection).
        for ($hop = 0; $hop <= self::MAX_REDIRECTS && $pending !== []; $hop++) {
            $hopResults = $this->runMultiRound($pending);
            $nextPending = [];

            foreach ($hopResults as $domain => $domainResults) {
                foreach ($domainResults as $key => [$result, $spec]) {
                    $chain = $chains[$domain][$key] ?? [];
                    $visited = $visitedUrls[$domain][$key] ?? [$spec->url];

                    $canFollowFurther = $hop < self::MAX_REDIRECTS
                        && $spec->followRedirects
                        && $result->ok
                        && $result->statusCode !== null
                        && $result->statusCode >= 300 && $result->statusCode < 400;

                    if ($canFollowFurther) {
                        $nextUrl = $this->resolveNextRedirectUrl($spec->url, $result->headersRaw ?? '', $visited);
                        if ($nextUrl !== null) {
                            $chain[] = ['url' => $nextUrl, 'status' => $result->statusCode];
                            $visited[] = $nextUrl;
                            $chains[$domain][$key] = $chain;
                            $visitedUrls[$domain][$key] = $visited;
                            $nextPending[$domain][$key] = new RequestSpec(
                                url: $nextUrl,
                                rangeBytes: $spec->rangeBytes,
                                method: $spec->method,
                                headers: $spec->headers,
                                body: $spec->body,
                                timeoutSeconds: $spec->timeoutSeconds,
                                followRedirects: true,
                            );
                            continue; // pas de résultat final pour cette clé à ce saut
                        }
                        // Pas de Location exploitable ou cible jugée non sûre par
                        // DomainSafety (voir resolveNextRedirectUrl) : on s'arrête
                        // ici et on garde le 3xx tel quel, chaîne comprise.
                    }

                    $results[$domain][$key] = $result->withRedirectChain($chain);
                }
            }

            $pending = $nextPending;
        }

        return $results;
    }

    /**
     * Résout la prochaine URL à suivre pour une redirection, ou null si elle
     * ne doit pas être suivie (pas de Location, format non exploitable,
     * boucle détectée, ou hôte cible ne passant pas DomainSafety — c'est ce
     * dernier point qui remplace la protection perdue en désactivant
     * CURLOPT_FOLLOWLOCATION, voir buildCurlHandle()).
     *
     * @param string[] $alreadyVisited URLs déjà traversées pour cette clé (détection de boucle).
     */
    private function resolveNextRedirectUrl(string $currentUrl, string $headersRaw, array $alreadyVisited): ?string
    {
        $location = $this->extractHeaderValue($headersRaw, 'Location');
        if ($location === null || $location === '') {
            return null;
        }

        $nextUrl = $this->resolveRedirectUrl($currentUrl, $location);
        if ($nextUrl === null || in_array($nextUrl, $alreadyVisited, true)) {
            return null;
        }

        $nextHost = parse_url($nextUrl, PHP_URL_HOST);
        if (!is_string($nextHost) || $nextHost === '' || !DomainSafety::isPlausibleDomain($nextHost)) {
            return null;
        }

        return $nextUrl;
    }

    /** Résout une valeur d'en-tête Location (absolue ou relative) en URL absolue, ou null si non résolvable. */
    private function resolveRedirectUrl(string $baseUrl, string $location): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location) === 1) {
            return $location; // déjà absolue
        }

        $baseParts = parse_url($baseUrl);
        if (!isset($baseParts['scheme'], $baseParts['host'])) {
            return null;
        }
        $origin = $baseParts['scheme'] . '://' . $baseParts['host']
            . (isset($baseParts['port']) ? ':' . $baseParts['port'] : '');

        if (str_starts_with($location, '//')) {
            return $baseParts['scheme'] . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $basePath = $baseParts['path'] ?? '/';
        $baseDir = substr($basePath, 0, (int) strrpos($basePath, '/') + 1);
        $baseDir = $baseDir === '' ? '/' : $baseDir;

        return $origin . $baseDir . $location;
    }

    /** Extrait la valeur d'un en-tête HTTP donné depuis le bloc d'en-têtes brut d'une réponse. */
    private function extractHeaderValue(string $headersRaw, string $headerName): ?string
    {
        if (preg_match('/^' . preg_quote($headerName, '/') . ':\s*(.+)$/mi', $headersRaw, $m) === 1) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Un seul round curl_multi, sans logique de redirection — utilisé aussi
     * bien pour le premier envoi d'une vague que pour chaque saut de
     * redirection suivant (voir fetchWave()).
     *
     * @param array<string, array<string, RequestSpec>> $requestsByDomain
     * @return array<string, array<string, array{0: FetchResult, 1: RequestSpec}>>
     */
    private function runMultiRound(array $requestsByDomain): array
    {
        $multiHandle = curl_multi_init();
        /** @var array<int, array{0: string, 1: string, 2: \CurlHandle, 3: RequestSpec}> $handleMap */
        $handleMap = [];
        /** @var array<string, array<string, array{0: FetchResult, 1: RequestSpec}>> $results */
        $results = [];

        foreach ($requestsByDomain as $domain => $requests) {
            foreach ($requests as $key => $spec) {
                // buildCurlHandle() valide l'URL (NetworkGuard : DNS, SSRF...)
                // avant même d'ouvrir une connexion. Une erreur ici (DNS cassé,
                // hôte privé...) ne concerne QUE cette requête précise — elle ne
                // doit jamais faire échouer les autres domaines du même round.
                // Sans ce try/catch, un seul domaine à la résolution DNS cassée
                // faisait perdre tous les résultats du lot entier (voir
                // PATCH-DNS-BATCH-ISOLATION.md).
                try {
                    $curlHandle = $this->buildCurlHandle($spec);
                } catch (\Throwable $e) {
                    $results[$domain][$key] = [FetchResult::error($spec->url, $e->getMessage()), $spec];
                    continue;
                }
                curl_multi_add_handle($multiHandle, $curlHandle);
                $handleMap[spl_object_id($curlHandle)] = [$domain, $key, $curlHandle, $spec];
            }
        }

        // curl_multi_exec peut renvoyer CURLM_CALL_MULTI_PERFORM (obsolète mais
        // encore possible) sans avoir réellement démarré les transferts — il faut
        // l'appeler au moins une fois avant de passer à la boucle select/exec.
        do {
            $status = curl_multi_exec($multiHandle, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        $running = null;
        while (true) {
            do {
                $status = curl_multi_exec($multiHandle, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            if ($running === 0 || $status !== CURLM_OK) {
                break;
            }
            curl_multi_select($multiHandle, 0.2);
        }

        // curl_errno()/curl_error() sur un handle individuel ne sont fiables
        // qu'après avoir drainé les messages de complétion de la multi-queue —
        // sans ça, un échec réel (connexion refusée, timeout) rapporte errno=0.
        while (curl_multi_info_read($multiHandle) !== false) {
            // on ne fait rien du message lui-même : curl_errno($handle) suffira
            // juste après, on veut seulement forcer la mise à jour interne.
        }

        foreach ($handleMap as [$domain, $key, $curlHandle, $spec]) {
            $result = $this->collectResult($curlHandle, $spec);
            curl_multi_remove_handle($multiHandle, $curlHandle);
            curl_close($curlHandle);


            if (!$result->ok && $spec->method === 'GET') {
                $result = $this->retryOnce($spec);
            }

            $results[$domain][$key] = [$result, $spec];
        }
        curl_multi_close($multiHandle);

        return $results;
    }

    private function retryOnce(RequestSpec $spec): FetchResult
    {
        $curlHandle = $this->buildCurlHandle($spec, true);
        curl_exec($curlHandle);
        $result = $this->collectResult($curlHandle, $spec);
        curl_close($curlHandle);

        return $result;
    }

    private function buildCurlHandle(RequestSpec $spec, bool $retryMode = false): \CurlHandle
    {
        \Ogpn\Bot\NetworkGuard::assertPublicUrl($spec->url);
        $curlHandle = curl_init($spec->url);

        $headerLines = ['Accept: text/html,application/json,text/plain,*/*'];
        foreach ($spec->headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        $handleId = spl_object_id($curlHandle);
        $this->buffers[$handleId] = '';

        $options = [
            CURLOPT_HEADER => true,
            // Suivi de redirection désactivé volontairement — CURLOPT_FOLLOWLOCATION
            // ne revérifie jamais l'IP de la cible : un site pourrait rediriger
            // vers 127.0.0.1, 169.254.x.x ou une plage privée, contournant le
            // filtrage de DomainSafety qui n'a lieu qu'avant la PREMIÈRE requête.
            // Un 3xx est donc traité comme "non accessible directement" plutôt
            // que suivi aveuglément — léger coût en couverture de données,
            // acceptable face au risque SSRF.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_S,
            CURLOPT_TIMEOUT => $spec->timeoutSeconds ?? self::TOTAL_TIMEOUT_S,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_ENCODING => '', // accepte gzip/deflate automatiquement
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CERTINFO => true, // capture l'émetteur SSL sur la même connexion, gratuit
            // Remplace CURLOPT_RETURNTRANSFER : on accumule nous-mêmes dans un
            // buffer borné, et on interrompt le transfert (retour < $length)
            // dès que MAX_BODY_BYTES est dépassé — protège la mémoire ET le
            // temps/bande passante, pas seulement l'un des deux.
            CURLOPT_WRITEFUNCTION => function (\CurlHandle $handle, string $data) use ($handleId): int {
                $length = strlen($data);
                if (strlen($this->buffers[$handleId]) >= self::MAX_BODY_BYTES) {
                    return 0; // fait échouer proprement le transfert (CURLE_WRITE_ERROR)
                }
                $this->buffers[$handleId] .= $data;
                return $length;
            },
        ];

        if ($spec->method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $spec->body ?? '';
        }

        if ($spec->rangeBytes !== null) {
            $options[CURLOPT_RANGE] = '0-' . ($spec->rangeBytes - 1);
        }

        if ($retryMode) {
            // Repli contrôlé pour les serveurs qui réinitialisent parfois une
            // connexion TLS/HTTP2 au premier contact. Le second essai reste
            // transparent, vérifie TLS et force HTTP/1.1 avec une connexion neuve.
            $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
            $options[CURLOPT_FRESH_CONNECT] = true;
            $options[CURLOPT_FORBID_REUSE] = true;
            $options[CURLOPT_CONNECTTIMEOUT] = max(4, self::CONNECT_TIMEOUT_S);
            $options[CURLOPT_TIMEOUT] = max(6, $spec->timeoutSeconds ?? self::TOTAL_TIMEOUT_S);
        }

        curl_setopt_array($curlHandle, $options);

        return $curlHandle;
    }

    private function collectResult(\CurlHandle $curlHandle, RequestSpec $spec): FetchResult
    {
        $handleId = spl_object_id($curlHandle);
        $raw = $this->buffers[$handleId] ?? '';
        unset($this->buffers[$handleId]); // libère la mémoire, plus jamais réutilisé pour ce handle

        $errorNumber = curl_errno($curlHandle);

        if ($errorNumber !== 0) {
            return FetchResult::error($spec->url, curl_error($curlHandle) ?: 'unknown_curl_error');
        }

        $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($curlHandle, CURLINFO_HEADER_SIZE);
        $headerRaw = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        $primaryIp = curl_getinfo($curlHandle, CURLINFO_PRIMARY_IP);
        $primaryIp = is_string($primaryIp) && $primaryIp !== '' ? $primaryIp : null;
        $sslIssuer = $this->extractSslIssuer($curlHandle);
        $httpVersion = $this->translateHttpVersion((int) curl_getinfo($curlHandle, CURLINFO_HTTP_VERSION));

        // Un serveur qui honore Range répond 206 (Partial Content). S'il ignore
        // l'entête et renvoie 200 avec le corps entier, on l'a quand même reçu
        // en totalité (le "gain bande passante" est perdu pour cette requête),
        // mais on le signale : c'est une donnée en soi (maturité technique du
        // serveur), et on tronque nous-mêmes pour rester cohérent en aval.
        $rangeWasRequested = $spec->rangeBytes !== null;
        $rangeIgnored = $rangeWasRequested && $statusCode === 200;

        if ($rangeIgnored) {
            $body = substr($body, 0, $spec->rangeBytes);
        }

        return FetchResult::success(
            url: $spec->url,
            statusCode: $statusCode,
            body: $body,
            headersRaw: $headerRaw,
            rangeIgnored: $rangeIgnored,
            primaryIp: $primaryIp,
            sslIssuer: $sslIssuer,
            httpVersion: $httpVersion,
        );
    }

    /** Traduit la constante CURL_HTTP_VERSION_* en libellé lisible — donnée brute, aucune interprétation au-delà du mapping. */
    private function translateHttpVersion(int $curlConstant): ?string
    {
        if (defined('CURL_HTTP_VERSION_3') && $curlConstant === CURL_HTTP_VERSION_3) {
            return '3';
        }

        return match ($curlConstant) {
            CURL_HTTP_VERSION_1_0 => '1.0',
            CURL_HTTP_VERSION_1_1 => '1.1',
            CURL_HTTP_VERSION_2_0 => '2',
            default => null,
        };
    }

    /** Extrait le champ "Issuer" du certificat serveur — null si HTTP (pas de certificat) ou info indisponible. */
    private function extractSslIssuer(\CurlHandle $curlHandle): ?string
    {
        $certInfo = curl_getinfo($curlHandle, CURLINFO_CERTINFO);
        if (!is_array($certInfo) || !isset($certInfo[0]['Issuer']) || !is_string($certInfo[0]['Issuer'])) {
            return null;
        }

        return $certInfo[0]['Issuer'];
    }
}
