<?php

declare(strict_types=1);

namespace Ogpn\Bot\Engine;

/**
 * Résultat d'une requête HTTP unique. Volontairement permissif : un 404 ou un
 * 403 est un succès réseau (on a une réponse du serveur), seul un vrai souci
 * réseau (timeout, DNS, TLS...) produit un résultat d'erreur.
 */
final class FetchResult
{
    private function __construct(
        public readonly string $url,
        public readonly bool $ok,
        public readonly ?int $statusCode,
        public readonly ?string $body,
        public readonly ?string $headersRaw,
        public readonly bool $rangeIgnored,
        public readonly ?string $errorMessage,
        /** IP réellement contactée pour cette requête — donnée brute, aucune interprétation ici (hébergeur/ASN résolus séparément, voir notes de conception). */
        public readonly ?string $primaryIp = null,
        /** Émetteur du certificat SSL, si HTTPS — donnée brute, capturée sur la même connexion, sans requête supplémentaire. */
        public readonly ?string $sslIssuer = null,
        /** Version du protocole HTTP négocié (ex. '1.1', '2', '3') — capturée sur la même connexion, sans requête supplémentaire. */
        public readonly ?string $httpVersion = null,
        /**
         * Sauts suivis après la requête initiale, dans l'ordre, si
         * RequestSpec::$followRedirects était activé (voir Http::fetchWave).
         * Chaque entrée est ['url' => ..., 'status' => 3xx] : le statut de
         * la redirection qui a MENÉ à cette URL (donc le premier élément
         * porte le 301/302 d'origine). Vide si aucune redirection n'a eu
         * lieu. $url ci-dessus reste toujours l'URL réellement contactée en
         * dernier (celle qui a produit $body/$statusCode).
         *
         * @var array<int, array{url: string, status: int}>
         */
        public readonly array $redirectChain = [],
    ) {
    }

    public static function success(
        string $url,
        int $statusCode,
        string $body,
        string $headersRaw,
        bool $rangeIgnored = false,
        ?string $primaryIp = null,
        ?string $sslIssuer = null,
        ?string $httpVersion = null,
    ): self {
        return new self(
            url: $url,
            ok: true,
            statusCode: $statusCode,
            body: $body,
            headersRaw: $headersRaw,
            rangeIgnored: $rangeIgnored,
            errorMessage: null,
            primaryIp: $primaryIp,
            sslIssuer: $sslIssuer,
            httpVersion: $httpVersion,
        );
    }

    public static function error(string $url, string $message): self
    {
        return new self(
            url: $url,
            ok: false,
            statusCode: null,
            body: null,
            headersRaw: null,
            rangeIgnored: false,
            errorMessage: $message,
        );
    }

    /** @param array<int, array{url: string, status: int}> $chain */
    public function withRedirectChain(array $chain): self
    {
        return new self(
            url: $this->url,
            ok: $this->ok,
            statusCode: $this->statusCode,
            body: $this->body,
            headersRaw: $this->headersRaw,
            rangeIgnored: $this->rangeIgnored,
            errorMessage: $this->errorMessage,
            primaryIp: $this->primaryIp,
            sslIssuer: $this->sslIssuer,
            httpVersion: $this->httpVersion,
            redirectChain: $chain,
        );
    }

    /** Vrai si le fichier existe (statut 2xx). Un 404/403 renvoie false, pas une erreur. */
    public function exists(): bool
    {
        return $this->ok && $this->statusCode !== null && $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
