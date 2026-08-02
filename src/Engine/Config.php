<?php

declare(strict_types=1);

namespace Ogpn\Bot\Engine;

/**
 * Constantes de configuration du bot — pas de logique ici, uniquement des
 * tables de données à maintenir à jour au fil du temps.
 */
final class Config
{
    /**
     * Bots IA reconnus dans robots.txt — liste volontairement non exhaustive,
     * à enrichir au fil du temps. Le nom est celui utilisé dans la directive
     * "User-agent:" de robots.txt.
     */
    public const AI_BOTS = [
        'GPTBot',           // OpenAI — entraînement
        'ChatGPT-User',     // OpenAI — navigation en direct
        'OAI-SearchBot',    // OpenAI — recherche
        'ClaudeBot',        // Anthropic — entraînement/crawl
        'Claude-User',      // Anthropic — navigation en direct
        'Claude-SearchBot', // Anthropic — recherche
        'CCBot',            // Common Crawl — alimente de nombreux modèles
        'Google-Extended',  // Google — entraînement (distinct de Googlebot)
        'GoogleOther',      // Google — usages divers hors indexation classique
        'Applebot-Extended',// Apple — entraînement (distinct d'Applebot)
        'Bytespider',       // ByteDance/TikTok
        'PerplexityBot',    // Perplexity — crawl
        'Perplexity-User',  // Perplexity — navigation en direct
        'Amazonbot',        // Amazon
        'FacebookBot',      // Meta — entraînement
        'Meta-ExternalAgent', // Meta — entraînement/indexation
        'cohere-ai',        // Cohere
        'Diffbot',          // Diffbot — extraction structurée
        'ImagesiftBot',     // ImageSift
        'omgili',           // Webz.io / omgili
        'YouBot',           // You.com
    ];

    /**
     * User-agents fréquents dans robots.txt qui NE sont PAS des bots IA —
     * moteurs de recherche classiques, outils SEO, aperçus de liens sociaux,
     * wildcard générique. Exclus de la détection "bot IA inconnu" pour que
     * cette liste reste exploitable (des dizaines de sites déclarent des
     * règles pour Googlebot/Bingbot/AhrefsBot sans que ce soit un signal
     * utile pour ce projet). Volontairement non exhaustive, à enrichir au
     * même titre qu'AI_BOTS si du bruit récurrent apparaît.
     */
    public const KNOWN_NON_AI_USER_AGENTS = [
        '*',
        'googlebot', 'googlebot-image', 'googlebot-news', 'googlebot-video', 'adsbot-google', 'mediapartners-google',
        'bingbot', 'msnbot', 'adidxbot',
        'yandexbot', 'yandex',
        'baiduspider',
        'duckduckbot', 'duckduckgo-favicons-bot',
        'slurp', // Yahoo
        'sogou',
        'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot', 'seznambot', 'blexbot', 'petalbot',
        'facebookexternalhit', 'twitterbot', 'linkedinbot', 'pinterestbot', 'whatsapp', 'telegrambot', 'discordbot',
        'applebot', // distinct d'Applebot-Extended, qui LUI est dans AI_BOTS
        'archive.org_bot', 'ia_archiver',
    ];

    /**
     * Groupe A — convention "racine uniquement" : aucune norme well-known
     * n'existe pour ces fichiers, chercher dans .well-known/ n'aurait pas de
     * sens normatif.
     */
    public const GROUP_A_ROOT_ONLY = [
        'llms' => 'llms.txt',
    ];

    /**
     * Groupe B — convention "well-known" : recherché aux deux emplacements
     * (racine ET well-known), avec un flag de positionnement indépendant du
     * contenu. Le nom de fichier bien-known peut différer du nom racine.
     */
    public const GROUP_B_DUAL_LOOKUP = [
        'ai_txt' => 'ai.txt',
        'tdmrep' => 'tdmrep.json',
        'ai_policy' => 'ai-policy.json',
    ];

    public const WELL_KNOWN_PREFIX = '/.well-known/';

    /**
     * Pays par TLD — deux mécanismes distincts :
     *  - ccTLD réels (norme ISO 3166-1 alpha-2) : dérivés automatiquement,
     *    pas besoin de les lister ici (le TLD EST le code pays).
     *  - gTLD villes/régions : aucune norme, table écrite à la main.
     * ".eu" et l'absence de correspondance sont volontairement absents :
     * ils déclenchent la logique de repli (lang/og/microdata) côté Scanner.
     */
    public const COUNTRY_BY_SPECIAL_TLD = [
        'brussels' => 'BE',
        'gent' => 'BE',
        'vlaanderen' => 'BE',
        'wien' => 'AT',
        'tirol' => 'AT',
        'berlin' => 'DE',
        'hamburg' => 'DE',
        'koeln' => 'DE',
        'cologne' => 'DE',
        'bayern' => 'DE',
        'ruhr' => 'DE',
        'saarland' => 'DE',
        'nrw' => 'DE',
        'paris' => 'FR',
        'bzh' => 'FR',
        'alsace' => 'FR',
        'corsica' => 'FR',
        'scot' => 'GB',
        'london' => 'GB',
        'wales' => 'GB',
        'cymru' => 'GB',
        'barcelona' => 'ES',
        'madrid' => 'ES',
        'gal' => 'ES',
        'cat' => 'ES',
        'eus' => 'ES', // Pays basque, frontalier FR/ES — Espagne par convention documentée
        'frl' => 'NL',
        'amsterdam' => 'NL',
        'swiss' => 'CH',
        'zuerich' => 'CH',
    ];

    /**
     * TLD supranationaux ou réservés qui ne doivent jamais être transformés
     * mécaniquement en code pays ISO. Les IDN .eu sont stockés ici en punycode,
     * format le plus robuste pour les URLs et Common Crawl.
     */
    public const NON_COUNTRY_TLDS = [
        'eu', 'xn--e1a4c', 'xn--qxa6a', // .eu, .ею, .ευ
        'gb', // réservé/historique ; le TLD opérationnel britannique est .uk
    ];

    /**
     * Pays réellement membres de l'Union européenne (27) — distinct de
     * "européen" au sens large. CH, GB, NO, IS, LI sont européens mais hors
     * UE : un site chez eux peut être parfaitement souverain sans pour
     * autant relever du même cadre réglementaire (RGPD applicable
     * différemment, AI Act, etc.) — les deux notions ne doivent jamais être
     * confondues dans le scoring.
     */
    public const EU_MEMBER_COUNTRIES = [
        'BE', 'FR', 'DE', 'NL', 'LU', 'AT', 'IT', 'ES', 'PT',
        'IE', 'DK', 'SE', 'FI', 'PL', 'CZ', 'SK', 'HU', 'RO',
        'BG', 'GR', 'HR', 'SI', 'EE', 'LV', 'LT', 'MT', 'CY',
    ];

    public static function isEuMember(?string $countryCode): bool
    {
        return $countryCode !== null && in_array($countryCode, self::EU_MEMBER_COUNTRIES, true);
    }

    /**
     * Pays à langue officielle unique le français — priorité FR directe.
     * Volontairement restreint aux cas non ambigus (contrairement à la
     * Belgique, qui a le français comme langue officielle mais n'est PAS
     * majoritairement francophone au niveau national — voir MULTILINGUAL_COUNTRIES).
     */
    public const FRANCOPHONE_COUNTRIES = ['FR', 'MC'];

    /**
     * Pays à plusieurs langues officielles sans dominante nationale fiable
     * déductible du seul TLD (ex. .be ne dit pas si le site est FR/NL/DE).
     * Priorité : EN comme dénominateur commun neutre, la langue déclarée
     * par le site lui-même (balise <html lang>, hreflang) prenant ensuite
     * le relais si un dictionnaire existe pour elle — voir Scanner::analysisLanguagePriority().
     */
    public const MULTILINGUAL_COUNTRIES = ['BE', 'CH', 'LU'];

    /**
     * Langue officielle dominante par pays, pour les pays hors des deux
     * listes ci-dessus. Couverture alignée sur FOR_SALE_PHRASES (24 langues
     * UE + turc) : déclarée même pour des langues sans dictionnaire de
     * classification pour l'instant (voir Scanner::hasDictionaryForLanguage,
     * qui ignore silencieusement une langue sans fichier dictionnaire) — la
     * priorité reste correcte le jour où le dictionnaire est ajouté, sans
     * modification nécessaire ici.
     */
    public const NATIONAL_LANGUAGE_BY_COUNTRY = [
        'DE' => 'de', 'AT' => 'de',
        'NL' => 'nl',
        'ES' => 'es',
        'PT' => 'pt',
        'IT' => 'it',
        'SE' => 'sv',
        'DK' => 'da',
        'FI' => 'fi',
        'PL' => 'pl',
        'CZ' => 'cs',
        'SK' => 'sk',
        'HU' => 'hu',
        'RO' => 'ro',
        'BG' => 'bg',
        'GR' => 'el',
        'HR' => 'hr',
        'SI' => 'sl',
        'EE' => 'et',
        'LV' => 'lv',
        'LT' => 'lt',
        'MT' => 'mt',
        'CY' => 'el',
        'IE' => 'en',
        'GB' => 'en',
        'TR' => 'tr',
    ];

    /**
     * Fournisseurs/services connus, détectables depuis le HTML (balises
     * <script src>, <link href>) — trois listes de souveraineté :
     *  - rouge : hors UE, juridiction étrangère
     *  - vert  : souveraineté UE confirmée
     *  - gris  : neutre/open source — la souveraineté dépend de l'hébergeur,
     *            pas du logiciel lui-même
     * Liste volontairement non exhaustive, à enrichir au fil du temps.
     * Ne couvre que ce qui est visible dans le HTML — l'hébergeur réel (via
     * IP/ASN) et les enregistrements DNS (MX, etc.) nécessitent une détection
     * séparée, pas encore en place (voir notes de conception du projet).
     */
    public const DEPENDENCY_PROVIDERS = [
        // --- CDN ---
        ['name' => 'Cloudflare', 'category' => 'cdn', 'eu_status' => 'rouge', 'patterns' => ['cloudflare.com', 'cdnjs.cloudflare.com', 'cf-ipv6.com']],
        ['name' => 'jsDelivr', 'category' => 'cdn', 'eu_status' => 'gris', 'patterns' => ['jsdelivr.net']],
        ['name' => 'cdnjs (sans Cloudflare)', 'category' => 'cdn', 'eu_status' => 'gris', 'patterns' => ['cdnjs.com']],
        ['name' => 'Bunny CDN', 'category' => 'cdn', 'eu_status' => 'vert', 'patterns' => ['b-cdn.net', 'bunny.net']],
        ['name' => 'Amazon CloudFront', 'category' => 'cdn', 'eu_status' => 'rouge', 'patterns' => ['cloudfront.net']],
        ['name' => 'Fastly', 'category' => 'cdn_waf', 'eu_status' => 'rouge', 'patterns' => ['fastly.net']],
        ['name' => 'Akamai', 'category' => 'cdn_waf', 'eu_status' => 'rouge', 'patterns' => ['akamaihd.net', 'akamai.net', 'akamai.com', 'akamaized.net']],
        ['name' => 'unpkg', 'category' => 'cdn_js', 'eu_status' => 'rouge', 'patterns' => ['unpkg.com']],
        ['name' => 'Bunny Fonts', 'category' => 'fonts', 'eu_status' => 'vert', 'patterns' => ['fonts.bunny.net']],

        // --- Polices ---
        ['name' => 'Google Fonts', 'category' => 'fonts', 'eu_status' => 'rouge', 'patterns' => ['fonts.googleapis.com', 'fonts.gstatic.com']],
        ['name' => 'Adobe Fonts', 'category' => 'fonts', 'eu_status' => 'rouge', 'patterns' => ['use.typekit.net']],

        // --- Analytics / mesure d'audience ---
        ['name' => 'Google Analytics', 'category' => 'analytics', 'eu_status' => 'rouge', 'patterns' => ['google-analytics.com', 'analytics.js', 'ga.js', '/g/collect']],
        ['name' => 'Google Firebase Analytics', 'category' => 'analytics', 'eu_status' => 'rouge', 'patterns' => ['firebase-analytics.js', 'firebase/analytics', 'firebase.google.com/docs/analytics', 'app-measurement.com']],
        ['name' => 'Matomo (cloud ou auto-hébergé)', 'category' => 'analytics', 'eu_status' => 'jaune', 'patterns' => ['matomo.cloud', 'matomo.js', 'piwik.js']],
        ['name' => 'Plausible', 'category' => 'analytics', 'eu_status' => 'vert', 'patterns' => ['plausible.io']],
        ['name' => 'Hotjar', 'category' => 'analytics', 'eu_status' => 'rouge', 'patterns' => ['hotjar.com']],
        ['name' => 'Microsoft Clarity', 'category' => 'analytics', 'eu_status' => 'rouge', 'patterns' => ['clarity.ms']],
        ['name' => 'Google Tag Manager', 'category' => 'tag_manager', 'eu_status' => 'rouge', 'patterns' => ['googletagmanager.com/gtm.js', 'googletagmanager.com/gtag/js']],
        ['name' => 'Adobe Analytics', 'category' => 'analytics', 'eu_status' => 'rouge', 'patterns' => ['omtrdc.net', '2o7.net', 'adobedc.net', 'adobedtm.com']],
        ['name' => 'Adobe Experience Cloud', 'category' => 'marketing_cloud', 'eu_status' => 'rouge', 'patterns' => ['demdex.net', 'everesttech.net', 'assets.adobedtm.com', 'adobe.com/experience-cloud']],
        ['name' => 'Adobe Advertising / Ad Cloud', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['adobedc.net', 'everesttech.net', 'demdex.net', 'adcloud', 'adobe_mc']],
        ['name' => 'Mixpanel', 'category' => 'analytics', 'eu_status' => 'rouge', 'patterns' => ['mixpanel.com', 'mxpnl.com']],
        ['name' => 'Segment', 'category' => 'analytics', 'eu_status' => 'rouge', 'patterns' => ['segment.com', 'segment.io']],
        ['name' => 'Fathom Analytics', 'category' => 'analytics', 'eu_status' => 'jaune', 'patterns' => ['usefathom.com', 'cdn.usefathom.com']],
        ['name' => 'Statcounter', 'category' => 'analytics', 'eu_status' => 'rouge', 'patterns' => ['statcounter.com', 'statcounter.js']],
        ['name' => 'Cloudflare Web Analytics / Insights', 'category' => 'analytics', 'eu_status' => 'rouge', 'patterns' => ['static.cloudflareinsights.com', 'cloudflareinsights.com/beacon.min.js']],
        ['name' => 'Piano Analytics / AT Internet', 'category' => 'analytics', 'eu_status' => 'jaune', 'patterns' => ['piano.io', 'atinternet.com', 'xiti.com', 'smarttag.js']],
        ['name' => 'Simple Analytics', 'category' => 'analytics', 'eu_status' => 'vert', 'patterns' => ['simpleanalytics.com']],
        ['name' => 'Umami', 'category' => 'analytics', 'eu_status' => 'gris', 'patterns' => ['umami.js', 'umami.is']],
        ['name' => 'Pirsch', 'category' => 'analytics', 'eu_status' => 'vert', 'patterns' => ['pirsch.io']],

        // --- Monitoring navigateur / session replay / experimentation ---
        ['name' => 'Bugsnag', 'category' => 'rum_monitoring', 'eu_status' => 'rouge', 'patterns' => ['bugsnag.com', 'bugsnag-js']],
        ['name' => 'Sentry Browser', 'category' => 'rum_monitoring', 'eu_status' => 'rouge', 'patterns' => ['browser.sentry-cdn.com', 'sentry.io', '@sentry/browser']],
        ['name' => 'Datadog RUM', 'category' => 'rum_monitoring', 'eu_status' => 'rouge', 'patterns' => ['datadoghq-browser-agent.com', 'datadog-rum']],
        ['name' => 'New Relic Browser', 'category' => 'rum_monitoring', 'eu_status' => 'rouge', 'patterns' => ['js-agent.newrelic.com', 'bam.nr-data.net']],
        ['name' => 'Contentsquare', 'category' => 'session_replay', 'eu_status' => 'jaune', 'patterns' => ['contentsquare.net', 'contentsquare.com', 'uxa.cloud']],
        ['name' => 'FullStory', 'category' => 'session_replay', 'eu_status' => 'rouge', 'patterns' => ['fullstory.com', 'edge.fullstory.com']],
        ['name' => 'LogRocket', 'category' => 'session_replay', 'eu_status' => 'rouge', 'patterns' => ['logrocket.com', 'cdn.lr-ingest.com']],
        ['name' => 'Mouseflow', 'category' => 'session_replay', 'eu_status' => 'rouge', 'patterns' => ['mouseflow.com']],
        ['name' => 'Crazy Egg', 'category' => 'session_replay', 'eu_status' => 'rouge', 'patterns' => ['crazyegg.com']],
        ['name' => 'AB Tasty', 'category' => 'ab_testing', 'eu_status' => 'jaune', 'patterns' => ['abtasty.com', 'try.abtasty.com']],
        ['name' => 'Optimizely', 'category' => 'ab_testing', 'eu_status' => 'rouge', 'patterns' => ['optimizely.com', 'cdn.optimizely.com']],

        // --- Authentification ---
        ['name' => 'Google Identity/Sign-In', 'category' => 'auth', 'eu_status' => 'rouge', 'patterns' => ['accounts.google.com/gsi', 'apis.google.com/js/platform.js']],
        ['name' => 'Facebook Login', 'category' => 'auth', 'eu_status' => 'rouge', 'patterns' => ['connect.facebook.net/en_US/sdk.js']],

        // --- Trackers publicitaires / attribution / réseaux sociaux ---
        ['name' => 'Meta Pixel', 'category' => 'social_pixel', 'eu_status' => 'rouge', 'patterns' => ['connect.facebook.net', 'facebook.com/tr']],
        ['name' => 'TikTok Pixel', 'category' => 'social_pixel', 'eu_status' => 'rouge', 'patterns' => ['analytics.tiktok.com', 'business-api.tiktok.com', 'tiktok.com/i18n/pixel/events.js']],
        ['name' => 'Pinterest Tag', 'category' => 'social_pixel', 'eu_status' => 'rouge', 'patterns' => ['s.pinimg.com/ct/', 'ct.pinterest.com', 'analytics.pinterest.com']],
        ['name' => 'LinkedIn Insight', 'category' => 'social_pixel', 'eu_status' => 'rouge', 'patterns' => ['snap.licdn.com', 'linkedin.com/li/track']],
        ['name' => 'X (Twitter) Ads', 'category' => 'social_pixel', 'eu_status' => 'rouge', 'patterns' => ['static.ads-twitter.com', 'analytics.twitter.com']],
        ['name' => 'Snapchat Pixel', 'category' => 'social_pixel', 'eu_status' => 'rouge', 'patterns' => ['sc-static.net/scevent.min.js', 'tr.snapchat.com']],
        ['name' => 'Reddit Pixel', 'category' => 'social_pixel', 'eu_status' => 'rouge', 'patterns' => ['alb.reddit.com', 'events.reddit.com', 'redditstatic.com/ads/pixel.js']],
        ['name' => 'Google Ads', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['googleadservices.com', 'googlesyndication.com', 'google.com/ads', 'googleads.g.doubleclick.net']],
        ['name' => 'DoubleClick', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['doubleclick.net']],
        ['name' => 'Google AdServices', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['www.googleadservices.com/pagead/conversion', 'adservice.google.']],
        ['name' => 'Microsoft Advertising / Bing UET', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['bat.bing.com', 'bing.com/bat.js']],
        ['name' => 'Criteo', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['criteo.com', 'criteo.net']],
        ['name' => 'Taboola', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['taboola.com']],
        ['name' => 'Outbrain', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['outbrain.com', 'outbrainimg.com']],
        ['name' => 'The Trade Desk', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['adsrvr.org', 'thetradedesk.com']],
        ['name' => 'Quantcast', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['quantserve.com', 'quantcount.com', 'quantcast.com']],
        ['name' => 'PubMatic', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['pubmatic.com', 'pub.network']],
        ['name' => 'Magnite / Rubicon', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['rubiconproject.com', 'magnite.com']],
        ['name' => 'Index Exchange', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['indexexchange.com']],
        ['name' => 'OpenX', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['openx.net']],
        ['name' => 'Media.net', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['media.net']],
        ['name' => 'Adform', 'category' => 'advertising_tracker', 'eu_status' => 'jaune', 'patterns' => ['adform.net']],
        ['name' => 'Smart AdServer / Equativ', 'category' => 'advertising_tracker', 'eu_status' => 'jaune', 'patterns' => ['smartadserver.com', 'equativ.net']],
        ['name' => 'Teads', 'category' => 'advertising_tracker', 'eu_status' => 'jaune', 'patterns' => ['teads.tv', 'teads.com']],
        ['name' => 'Amazon Ads', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['amazon-adsystem.com', 'aaxads.com']],
        ['name' => 'AdRoll', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['adroll.com', 'd.adroll.com']],
        ['name' => 'Lotame', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['crwdcntrl.net', 'lotame.com']],
        ['name' => 'LiveRamp', 'category' => 'advertising_tracker', 'eu_status' => 'rouge', 'patterns' => ['liveramp.com', 'rlcdn.com']],
        ['name' => 'Utiq', 'category' => 'identifier_attribution', 'eu_status' => 'jaune', 'patterns' => ['utiq.com', 'utiq.io']],
        ['name' => 'Singular', 'category' => 'identifier_attribution', 'eu_status' => 'rouge', 'patterns' => ['singular.net', 'sdk-api-v1.singular.net']],
        ['name' => 'AppsFlyer', 'category' => 'identifier_attribution', 'eu_status' => 'rouge', 'patterns' => ['appsflyer.com', 'appsflyersdk.com']],
        ['name' => 'Adjust', 'category' => 'identifier_attribution', 'eu_status' => 'rouge', 'patterns' => ['adjust.com', 'adjust.net']],
        ['name' => 'Branch', 'category' => 'identifier_attribution', 'eu_status' => 'rouge', 'patterns' => ['branch.io', 'app.link']],
        ['name' => 'Adometry', 'category' => 'identifier_attribution', 'eu_status' => 'rouge', 'patterns' => ['adometry.com']],

        // --- Gestion du consentement (CMP) ---
        ['name' => 'Axeptio', 'category' => 'cmp', 'eu_status' => 'vert', 'patterns' => ['axept.io', 'axeptio.eu']],
        ['name' => 'Tarteaucitron', 'category' => 'cmp', 'eu_status' => 'gris', 'patterns' => ['tarteaucitron.js']],
        ['name' => 'Cookiebot', 'category' => 'cmp', 'eu_status' => 'vert', 'patterns' => ['cookiebot.com']],
        ['name' => 'OneTrust', 'category' => 'cmp', 'eu_status' => 'rouge', 'patterns' => ['onetrust.com', 'cookielaw.org']],
        ['name' => 'CookieYes', 'category' => 'cmp', 'eu_status' => 'rouge', 'patterns' => ['cookieyes.com']],
        ['name' => 'Didomi', 'category' => 'cmp', 'eu_status' => 'vert', 'patterns' => ['didomi.io']],
        ['name' => 'Sourcepoint', 'category' => 'cmp', 'eu_status' => 'rouge', 'patterns' => ['sourcepointcmp.com']],
        ['name' => 'Quantcast Choice', 'category' => 'cmp', 'eu_status' => 'rouge', 'patterns' => ['quantcast.mgr.consensu.org', 'choice.quantcast.com']],
        ['name' => 'Usercentrics', 'category' => 'cmp', 'eu_status' => 'jaune', 'patterns' => ['usercentrics.eu', 'usercentrics.com']],
        ['name' => 'TrustArc', 'category' => 'cmp', 'eu_status' => 'rouge', 'patterns' => ['trustarc.com', 'truste.com']],
        ['name' => 'Iubenda', 'category' => 'cmp', 'eu_status' => 'jaune', 'patterns' => ['iubenda.com']],
        ['name' => 'Complianz', 'category' => 'cmp', 'eu_status' => 'gris', 'patterns' => ['complianz.io', 'complianz-gdpr']],
        ['name' => 'Sirdata CMP', 'category' => 'cmp', 'eu_status' => 'jaune', 'patterns' => ['sirdata.com', 'cmp.sirdata.io']],
        ['name' => 'SFBX / Commanders Act CMP', 'category' => 'cmp', 'eu_status' => 'jaune', 'patterns' => ['sfbx.io', 'commandersact.com', 'tagcommander.com']],
        ['name' => 'Cookie Information', 'category' => 'cmp', 'eu_status' => 'jaune', 'patterns' => ['cookieinformation.com']],
        ['name' => 'Osano', 'category' => 'cmp', 'eu_status' => 'rouge', 'patterns' => ['osano.com']],

        // --- Captcha / anti-bot ---
        ['name' => 'Google reCAPTCHA', 'category' => 'captcha', 'eu_status' => 'rouge', 'patterns' => ['google.com/recaptcha', 'recaptcha.net']],
        ['name' => 'Cloudflare Turnstile', 'category' => 'captcha', 'eu_status' => 'rouge', 'patterns' => ['challenges.cloudflare.com']],
        ['name' => 'hCaptcha', 'category' => 'captcha', 'eu_status' => 'rouge', 'patterns' => ['hcaptcha.com']],
        ['name' => 'Friendly Captcha', 'category' => 'captcha', 'eu_status' => 'vert', 'patterns' => ['friendlycaptcha.com']],
        ['name' => 'Altcha', 'category' => 'captcha', 'eu_status' => 'gris', 'patterns' => ['altcha.org']],

        // --- Paiement ---
        ['name' => 'Stripe', 'category' => 'payment', 'eu_status' => 'rouge', 'patterns' => ['js.stripe.com']],
        ['name' => 'PayPal', 'category' => 'payment', 'eu_status' => 'rouge', 'patterns' => ['paypal.com/sdk']],
        ['name' => 'Square', 'category' => 'payment', 'eu_status' => 'rouge', 'patterns' => ['squareup.com', 'squarecdn.com']],
        ['name' => 'Mollie', 'category' => 'payment', 'eu_status' => 'vert', 'patterns' => ['mollie.com']],
        ['name' => 'PayPlug', 'category' => 'payment', 'eu_status' => 'vert', 'patterns' => ['payplug.com']],

        // --- Cartographie / vidéo / support ---
        ['name' => 'Google Maps', 'category' => 'maps', 'eu_status' => 'rouge', 'patterns' => ['maps.googleapis.com', 'google.com/maps']],
        ['name' => 'Mapbox', 'category' => 'maps', 'eu_status' => 'rouge', 'patterns' => ['mapbox.com', 'api.mapbox.com']],
        ['name' => 'OpenStreetMap', 'category' => 'maps', 'eu_status' => 'gris', 'patterns' => ['openstreetmap.org', 'tile.openstreetmap.org']],
        ['name' => 'Leaflet', 'category' => 'maps', 'eu_status' => 'gris', 'patterns' => ['leafletjs.com', 'leaflet.js']],
        ['name' => 'Vimeo', 'category' => 'video', 'eu_status' => 'rouge', 'patterns' => ['vimeo.com', 'player.vimeo.com']],
        ['name' => 'Intercom', 'category' => 'support_chat', 'eu_status' => 'rouge', 'patterns' => ['intercom.io', 'intercomcdn.com']],
        ['name' => 'Crisp', 'category' => 'support_chat', 'eu_status' => 'vert', 'patterns' => ['crisp.chat', 'client.crisp.chat']],

        // --- CMS / frameworks (gris — la souveraineté dépend de l'hébergeur) ---
        ['name' => 'WordPress', 'category' => 'cms', 'eu_status' => 'gris', 'patterns' => ['wp-content', 'wp-includes']],
        ['name' => 'Drupal', 'category' => 'cms', 'eu_status' => 'gris', 'patterns' => ['drupal.js', 'drupal.min.js', 'x-generator: drupal']],
        ['name' => 'Joomla', 'category' => 'cms', 'eu_status' => 'gris', 'patterns' => ['joomla', '/media/jui/', '/media/system/']],
        ['name' => 'TYPO3', 'category' => 'cms', 'eu_status' => 'gris', 'patterns' => ['typo3']],
        ['name' => 'PrestaShop', 'category' => 'ecommerce', 'eu_status' => 'gris', 'patterns' => ['prestashop']],
        ['name' => 'WooCommerce', 'category' => 'ecommerce', 'eu_status' => 'gris', 'patterns' => ['woocommerce', '/wp-content/plugins/woocommerce/']],
        ['name' => 'Shopify', 'category' => 'ecommerce', 'eu_status' => 'rouge', 'patterns' => ['cdn.shopify.com', 'myshopify.com']],
        ['name' => 'Magento / Adobe Commerce', 'category' => 'ecommerce', 'eu_status' => 'jaune', 'patterns' => ['Magento_Customer', 'Magento_Ui', 'Magento_Theme']],
        ['name' => "Let's Encrypt", 'category' => 'ssl', 'eu_status' => 'gris', 'patterns' => ["Let's Encrypt", 'letsencrypt.org']],
        ['name' => 'ZeroSSL', 'category' => 'ssl', 'eu_status' => 'gris', 'patterns' => ['ZeroSSL']],
    ];

    /**
     * Réseaux sociaux et plateformes de code — détection VOLONTAIREMENT
     * distincte de DEPENDENCY_PROVIDERS : ici on cherche uniquement à
     * l'intérieur d'attributs href="", jamais dans le texte ou le code
     * technique de la page. Un lien href = très probablement le site a
     * réellement un compte/une page sur ce réseau. Un simple mot dans le
     * texte ou un script chargé (pixel de tracking) n'a rien à voir avec
     * cette présence — c'est justement la distinction qu'on avait retenue.
     *
     * 'patterns' : sous-chaînes à chercher (recherche simple, insensible à
     * la casse) — sauf pour les entrées 'generic' => true, qui utilisent une
     * vraie expression régulière (nécessaire pour Mastodon : instance
     * arbitraire, pas de domaine fixe).
     *
     * 'type_patterns' (optionnel) : sous-motifs testés dans l'ordre pour
     * distinguer profil personnel / page entreprise / groupe — seulement
     * là où la structure d'URL le permet réellement (voir notes de
     * conception : LinkedIn, Facebook, Reddit, WhatsApp, YouTube).
     */
    public const SOCIAL_PLATFORMS = [
        ['name' => 'Facebook', 'slug' => 'facebook', 'eu_status' => 'rouge', 'patterns' => ['facebook.com/'], 'type_patterns' => ['group' => '/groups/']],
        ['name' => 'Instagram', 'slug' => 'instagram', 'eu_status' => 'rouge', 'patterns' => ['instagram.com/']],
        ['name' => 'X (Twitter)', 'slug' => 'x_twitter', 'eu_status' => 'rouge', 'patterns' => ['twitter.com/', 'x.com/']],
        ['name' => 'LinkedIn', 'slug' => 'linkedin', 'eu_status' => 'rouge', 'patterns' => ['linkedin.com/'], 'type_patterns' => ['company' => '/company/', 'personal' => '/in/', 'group' => '/groups/']],
        ['name' => 'YouTube', 'slug' => 'youtube', 'eu_status' => 'rouge', 'patterns' => ['youtube.com/', 'youtu.be/'], 'type_patterns' => ['channel' => '/channel/']],
        ['name' => 'TikTok', 'slug' => 'tiktok', 'eu_status' => 'rouge', 'patterns' => ['tiktok.com/']],
        ['name' => 'Threads', 'slug' => 'threads', 'eu_status' => 'rouge', 'patterns' => ['threads.net/']],
        ['name' => 'Bluesky', 'slug' => 'bluesky', 'eu_status' => 'rouge', 'patterns' => ['bsky.app/']],
        ['name' => 'Twitch', 'slug' => 'twitch', 'eu_status' => 'rouge', 'patterns' => ['twitch.tv/']],
        ['name' => 'Discord', 'slug' => 'discord', 'eu_status' => 'rouge', 'patterns' => ['discord.gg/', 'discord.com/invite'], 'default_type' => 'group'],
        ['name' => 'Snapchat', 'slug' => 'snapchat', 'eu_status' => 'rouge', 'patterns' => ['snapchat.com/']],
        ['name' => 'Pinterest', 'slug' => 'pinterest', 'eu_status' => 'rouge', 'patterns' => ['pinterest.com/', 'pinterest.fr/']],
        ['name' => 'WhatsApp', 'slug' => 'whatsapp', 'eu_status' => 'rouge', 'patterns' => ['wa.me/', 'chat.whatsapp.com/', 'api.whatsapp.com/'], 'type_patterns' => ['group' => 'chat.whatsapp.com/']],
        ['name' => 'Telegram', 'slug' => 'telegram', 'eu_status' => 'rouge', 'patterns' => ['t.me/', 'telegram.me/']],
        ['name' => 'Reddit', 'slug' => 'reddit', 'eu_status' => 'rouge', 'patterns' => ['reddit.com/'], 'type_patterns' => ['community' => '/r/', 'personal' => '/user/']],
        ['name' => 'Xing', 'slug' => 'xing', 'eu_status' => 'vert', 'patterns' => ['xing.com/']],
        ['name' => 'GitHub', 'slug' => 'github', 'eu_status' => 'rouge', 'patterns' => ['github.com/']],
        ['name' => 'GitLab', 'slug' => 'gitlab', 'eu_status' => 'rouge', 'patterns' => ['gitlab.com/']],
        ['name' => 'Codeberg', 'slug' => 'codeberg', 'eu_status' => 'vert', 'patterns' => ['codeberg.org/']],
        ['name' => 'W Social', 'slug' => 'wsocial', 'eu_status' => 'vert', 'patterns' => ['wsocial.eu', 'wsocial.news']],
        ['name' => 'Malt', 'slug' => 'malt', 'eu_status' => 'vert', 'patterns' => ['malt.fr/', 'malt.de/', 'malt.es/', 'malt.ch/', 'malt.nl/']],
        // Matrix : matrix.to est un domaine de redirection universel, fixe
        // quel que soit le serveur d'hébergement réel — pas besoin de motif
        // générique pour celui-là.
        ['name' => 'Matrix', 'slug' => 'matrix', 'eu_status' => 'vert', 'patterns' => ['matrix.to/#/@']],
        // Mastodon : fédéré, instance arbitraire — seul cas nécessitant un
        // vrai motif générique (regex), testé UNIQUEMENT si aucun domaine
        // fixe connu n'a déjà matché (TikTok et Threads utilisent aussi
        // "/@nom" dans leurs URLs — l'ordre de vérification évite la confusion).
        ['name' => 'Mastodon (instance non identifiée)', 'slug' => 'mastodon', 'eu_status' => 'vert', 'generic' => true, 'patterns' => ['/\/@[A-Za-z0-9_.-]+/']],
    ];

    /**
     * Plateformes de parking/revente de domaines connues — nom lisible =>
     * fragments de chaîne à chercher (minuscules), dans le HTML de la page
     * OU dans la cible d'une redirection (en-tête Location). Un seul match
     * suffit à identifier la plateforme avec une confiance élevée — ce ne
     * sont pas des mots génériques qui pourraient apparaître ailleurs par
     * hasard. Volontairement non exhaustive, à enrichir comme AI_BOTS si de
     * nouveaux acteurs apparaissent dans les résultats.
     *
     * @var array<string, string[]>
     */
    public const PARKING_PROVIDERS = [
        'Sedo' => ['sedoparking.com', 'sedo.com/search', 'sedo.com/details'],
        'Afternic' => ['afternic.com'],
        'Dan.com' => ['dan.com'],
        'Bodis' => ['bodis.com'],
        'ParkingCrew' => ['parkingcrew.net'],
        'HugeDomains' => ['hugedomains.com'],
        'Undeveloped' => ['undeveloped.com'],
        'Uniregistry Market' => ['uniregistrymarket.link'],
        'ParkLogic' => ['parklogic.com'],
        'GoDaddy Domains' => ['godaddy.com/domains', 'godaddy.com/domainfind'],
        'Namecheap Market' => ['namecheap.com/market'],
        'Above.com' => ['above.com/domain'],
        'Epik' => ['park.epik.com'],
        'DomainAgents' => ['domainagents.com'],
    ];

    /**
     * Phrases génériques indiquant qu'un domaine est en vente ou parqué,
     * indépendamment de toute plateforme reconnue — indices moins certains
     * qu'une signature de plateforme (une page pourrait mentionner "domain
     * for sale" hors contexte, en théorie), donc classés dans une source
     * séparée (voir Scanner::detectDomainForSale()). Toutes en minuscules.
     *
     * Couverture : les 24 langues officielles de l'UE + le turc (pas de
     * langues régionales). Traductions produites automatiquement, PAS
     * vérifiées par des locuteurs natifs — même réserve que pour le
     * dictionnaire de catégorisation avant validation sur le corpus FR.
     * À corriger au fil des faux positifs/négatifs observés en conditions
     * réelles plutôt qu'à considérer comme définitif.
     *
     * @var string[]
     */
    public const FOR_SALE_PHRASES = [
        // Anglais (en) — aussi la langue de repli générale du projet
        'domain for sale',
        'this domain is for sale',
        'buy this domain',
        'domain name for sale',
        'domain is parked',
        'this domain may be for sale',
        'may be for sale by its owner',
        'inquire about this domain',

        // Français (fr)
        'ce nom de domaine est à vendre',
        'ce domaine est à vendre',
        'domaine à vendre',
        'nom de domaine à vendre',
        'faire une offre pour ce domaine',

        // Allemand (de)
        'diese domain steht zum verkauf',
        'domain zu verkaufen',
        'kaufen sie diese domain',
        'diese domain ist geparkt',

        // Néerlandais (nl)
        'dit domein is te koop',
        'domein te koop',
        'koop dit domein',
        // Variantes avec "domeinnaam" (nom de domaine) — en pratique le terme
        // le plus fréquent sur les pages de revente NL, plus que "domein" seul
        // (confirmé manquant sur un cas réel, 0032.be : "0032.be is te koop" /
        // "domeinnaam te koop", non couverts par les phrases ci-dessus).
        'domeinnaam te koop',
        'deze domeinnaam te koop',
        'koop deze domeinnaam',
        'is te koop',

        // Espagnol (es)
        'este dominio está en venta',
        'dominio en venta',
        'comprar este dominio',

        // Portugais (pt)
        'este domínio está à venda',
        'domínio à venda',
        'comprar este domínio',

        // Italien (it)
        'questo dominio è in vendita',
        'dominio in vendita',
        'acquista questo dominio',

        // Suédois (sv)
        'denna domän är till salu',
        'köp denna domän',

        // Danois (da)
        'dette domæne er til salg',
        'køb dette domæne',

        // Finnois (fi)
        'tämä verkkotunnus on myynnissä',
        'osta tämä verkkotunnus',

        // Polonais (pl)
        'ta domena jest na sprzedaż',
        'kup tę domenę',

        // Tchèque (cs)
        'tato doména je na prodej',
        'koupit tuto doménu',

        // Slovaque (sk)
        'táto doména je na predaj',
        'kúpiť túto doménu',

        // Slovène (sl)
        'ta domena je naprodaj',
        'kupite to domeno',

        // Hongrois (hu)
        'ez a domain eladó',
        'vásárolja meg ezt a domaint',

        // Roumain (ro)
        'acest domeniu este de vânzare',
        'cumpărați acest domeniu',

        // Bulgare (bg)
        'този домейн се продава',
        'купете този домейн',

        // Croate (hr)
        'domena je na prodaju',
        'kupite ovu domenu',

        // Grec (el)
        'αυτός ο τομέας είναι προς πώληση',
        'αγοράστε αυτόν τον τομέα',

        // Estonien (et)
        'see domeen on müügis',
        'ostke see domeen',

        // Letton (lv)
        'šis domēns ir pārdošanā',
        'pirkt šo domēnu',

        // Lituanien (lt)
        'šis domenas parduodamas',
        'pirkti šį domeną',

        // Maltais (mt)
        'dan id-dominju huwa għall-bejgħ',

        // Irlandais (ga)
        'tá an fearann seo le díol',

        // Turc (tr) — hors UE, ajouté explicitement comme demandé
        'bu alan adı satılıktır',
        'bu domaini satın al',
    ];

    /**
     * Systèmes d'identité numérique — publics (UE et non-UE, pour
     * comparaison) et privés majeurs. Même mécanique que SOCIAL_PLATFORMS :
     * détection par lien (href) contenant un des motifs.
     *
     * RÉSERVE IMPORTANTE : le scanner ne récupère que la page d'accueil (et
     * ses variantes de langue) — un bouton "Se connecter avec X" vit
     * presque toujours sur une page /login ou /connexion dédiée, jamais
     * scannée. Ce signal a donc un rappel structurellement faible : il
     * détecte les intégrations visibles depuis l'accueil (bandeau, footer,
     * mention explicite), pas la majorité des cas réels. Utile en tendance
     * agrégée, pas comme inventaire exhaustif.
     *
     * @var array<int, array{name: string, slug: string, region: 'eu'|'non_eu', patterns: string[]}>
     */
    public const DIGITAL_IDENTITY_PROVIDERS = [
        // -- Identité numérique nationale (UE, niveau eIDAS) --
        ['name' => 'FranceConnect', 'slug' => 'franceconnect', 'region' => 'eu', 'type' => 'national_identity', 'patterns' => ['franceconnect.gouv.fr', 'franceconnect.fr']],
        ['name' => 'SPID', 'slug' => 'spid', 'region' => 'eu', 'type' => 'national_identity', 'patterns' => ['spid.gov.it', 'idserver.servizicie.interno.gov.it']],
        ['name' => 'Itsme', 'slug' => 'itsme', 'region' => 'eu', 'type' => 'national_identity', 'patterns' => ['itsme.be', 'itsme-id.com']],
        ['name' => 'DigiD', 'slug' => 'digid', 'region' => 'eu', 'type' => 'national_identity', 'patterns' => ['digid.nl']],
        ['name' => "Cl@ve", 'slug' => 'clave', 'region' => 'eu', 'type' => 'national_identity', 'patterns' => ['clave.gob.es']],
        ['name' => 'Chave Móvel Digital', 'slug' => 'cmd_pt', 'region' => 'eu', 'type' => 'national_identity', 'patterns' => ['autenticacao.gov.pt']],
        ['name' => 'MitID', 'slug' => 'mitid', 'region' => 'eu', 'type' => 'national_identity', 'patterns' => ['mitid.dk']],
        ['name' => 'BankID', 'slug' => 'bankid', 'region' => 'eu', 'type' => 'national_identity', 'patterns' => ['bankid.com', 'bankid.no']],
        ['name' => 'eIDAS (nœud générique)', 'slug' => 'eidas_generic', 'region' => 'eu', 'type' => 'national_identity', 'patterns' => ['eidas-node', '/eidas/']],

        // -- Identité nationale hors UE, pour comparaison --
        ['name' => 'Login.gov', 'slug' => 'login_gov', 'region' => 'non_eu', 'type' => 'national_identity', 'patterns' => ['secure.login.gov']],
        ['name' => 'GOV.UK One Login', 'slug' => 'gov_uk_one_login', 'region' => 'non_eu', 'type' => 'national_identity', 'patterns' => ['signin.account.gov.uk']],
        ['name' => 'ID.me', 'slug' => 'id_me', 'region' => 'non_eu', 'type' => 'national_identity', 'patterns' => ['id.me/sign_in', 'wallet.id.me']],

        // -- Connexion commerciale (login social/OAuth) — PAS une vérification
        // d'identité au sens propre, juste une authentification déléguée.
        ['name' => 'Sign in with Apple', 'slug' => 'apple_id', 'region' => 'non_eu', 'type' => 'commercial_login', 'patterns' => ['appleid.apple.com/auth']],
        ['name' => 'Google Sign-In', 'slug' => 'google_signin', 'region' => 'non_eu', 'type' => 'commercial_login', 'patterns' => ['accounts.google.com/o/oauth2', 'accounts.google.com/signin']],
        ['name' => 'Microsoft Account / Entra ID', 'slug' => 'microsoft_identity', 'region' => 'non_eu', 'type' => 'commercial_login', 'patterns' => ['login.microsoftonline.com', 'login.live.com', 'login.windows.net']],
        ['name' => 'Facebook Login', 'slug' => 'facebook_login', 'region' => 'non_eu', 'type' => 'commercial_login', 'patterns' => ['facebook.com/dialog/oauth', 'www.facebook.com/v', 'connect.facebook.net']],
        ['name' => 'LinkedIn Sign-In', 'slug' => 'linkedin_signin', 'region' => 'non_eu', 'type' => 'commercial_login', 'patterns' => ['linkedin.com/oauth', 'www.linkedin.com/oauth']],
        ['name' => 'GitHub OAuth', 'slug' => 'github_oauth', 'region' => 'non_eu', 'type' => 'commercial_login', 'patterns' => ['github.com/login/oauth']],
        ['name' => 'Auth0', 'slug' => 'auth0', 'region' => 'non_eu', 'type' => 'commercial_login', 'patterns' => ['auth0.com/authorize', '.auth0.com/']],
        ['name' => 'Okta', 'slug' => 'okta', 'region' => 'non_eu', 'type' => 'commercial_login', 'patterns' => ['okta.com/oauth2', '.okta.com/']],
        ['name' => 'Keycloak (SSO générique)', 'slug' => 'keycloak', 'region' => 'eu', 'type' => 'commercial_login', 'patterns' => ['/realms/', '/protocol/openid-connect/']],

        // -- Vérification d'âge — catégorie distincte de l'identité et de la
        // connexion : confirme "plus de X ans", jamais "qui vous êtes".
        // Sujet réglementaire actif en UE (DSA) et Royaume-Uni (Online Safety
        // Act). Liste de départ (2026-08), à enrichir — non exhaustive.
        ['name' => 'Yoti', 'slug' => 'yoti', 'region' => 'non_eu', 'type' => 'age_verification', 'patterns' => ['yoti.com']],
        ['name' => 'AgeChecked', 'slug' => 'agechecked', 'region' => 'non_eu', 'type' => 'age_verification', 'patterns' => ['agechecked.com']],
        ['name' => 'VerifyMy', 'slug' => 'verifymy', 'region' => 'non_eu', 'type' => 'age_verification', 'patterns' => ['verifymy.io', 'verifymyage.com']],
        ['name' => 'Veriff', 'slug' => 'veriff', 'region' => 'eu', 'type' => 'age_verification', 'patterns' => ['veriff.com', 'veriff.me']],
        ['name' => 'Veratad', 'slug' => 'veratad', 'region' => 'non_eu', 'type' => 'age_verification', 'patterns' => ['veratad.com']],
        ['name' => 'Trulioo', 'slug' => 'trulioo', 'region' => 'non_eu', 'type' => 'age_verification', 'patterns' => ['trulioo.com']],
        ['name' => 'IDology (GBG)', 'slug' => 'idology', 'region' => 'non_eu', 'type' => 'age_verification', 'patterns' => ['idology.com']],
        ['name' => 'Sumsub', 'slug' => 'sumsub', 'region' => 'eu', 'type' => 'age_verification', 'patterns' => ['sumsub.com']],
    ];

    /**
     * Fournisseurs de stockage externe (objets/fichiers) référencés dans le
     * HTML — détection gratuite (aucune requête réseau en plus), simple
     * recherche de motifs d'URL déjà présents dans la page récupérée.
     * Liste de départ (2026-08), volume attendu faible mais gratuit à
     * suivre — non exhaustive.
     */
    public const EXTERNAL_STORAGE_PROVIDERS = [
        ['name' => 'Amazon S3', 'slug' => 's3', 'region' => 'non_eu', 'patterns' => ['s3.amazonaws.com', '.s3.eu-', '.s3.us-', '.s3.ap-']],
        ['name' => 'Azure Blob Storage', 'slug' => 'azure_blob', 'region' => 'non_eu', 'patterns' => ['blob.core.windows.net']],
        ['name' => 'Google Cloud Storage', 'slug' => 'gcs', 'region' => 'non_eu', 'patterns' => ['storage.googleapis.com']],
        ['name' => 'Backblaze B2', 'slug' => 'backblaze_b2', 'region' => 'non_eu', 'patterns' => ['backblazeb2.com']],
        ['name' => 'Cloudinary', 'slug' => 'cloudinary', 'region' => 'non_eu', 'patterns' => ['cloudinary.com', 'res.cloudinary.com']],
        ['name' => 'OVH Object Storage', 'slug' => 'ovh_storage', 'region' => 'eu', 'patterns' => ['cloud.ovh.net/storage', 'storage.gra.cloud.ovh']],
        ['name' => 'Scaleway Object Storage', 'slug' => 'scaleway_storage', 'region' => 'eu', 'patterns' => ['scw.cloud']],
    ];

    /** Outils de recherche interne tiers (widgets embarqués), détection gratuite par motif d'URL. */
    public const INTERNAL_SEARCH_TOOLS = [
        ['name' => 'Algolia', 'slug' => 'algolia', 'region' => 'non_eu', 'patterns' => ['algolia.net', 'algolianet.com', 'algoliasearch']],
        ['name' => 'Elastic App Search / Swiftype', 'slug' => 'swiftype', 'region' => 'non_eu', 'patterns' => ['swiftype.com', 'app-search']],
        ['name' => 'Typesense', 'slug' => 'typesense', 'region' => 'non_eu', 'patterns' => ['typesense.org', 'a1.typesense.net']],
        ['name' => 'Klevu', 'slug' => 'klevu', 'region' => 'eu', 'patterns' => ['klevu.com']],
        ['name' => 'Doofinder', 'slug' => 'doofinder', 'region' => 'eu', 'patterns' => ['doofinder.com']],
    ];

    /**
     * Fournisseurs de formulaires externes connus, détectés par motif d'URL
     * — en complément, Scanner::detectExternalForms() signale aussi tout
     * <form action="..."> pointant vers un domaine différent du site
     * analysé, même hors de cette liste (services non répertoriés).
     */
    public const EXTERNAL_FORM_PROVIDERS = [
        ['name' => 'Typeform', 'slug' => 'typeform', 'region' => 'non_eu', 'patterns' => ['typeform.com']],
        ['name' => 'HubSpot Forms', 'slug' => 'hubspot_forms', 'region' => 'non_eu', 'patterns' => ['hsforms.com', 'forms.hubspot.com']],
        ['name' => 'Google Forms', 'slug' => 'google_forms', 'region' => 'non_eu', 'patterns' => ['docs.google.com/forms', 'forms.gle']],
        ['name' => 'Jotform', 'slug' => 'jotform', 'region' => 'non_eu', 'patterns' => ['jotform.com']],
        ['name' => 'Wufoo', 'slug' => 'wufoo', 'region' => 'non_eu', 'patterns' => ['wufoo.com']],
        ['name' => 'Formspree', 'slug' => 'formspree', 'region' => 'non_eu', 'patterns' => ['formspree.io']],
    ];

    /**
     * Fonctionnalités IA embarquées détectables par motif d'URL/script —
     * volontairement limité à ce qui est fiable à repérer ainsi (référence
     * explicite à un fournisseur, ou widget connu). Des catégories demandées
     * (recommandation, reconnaissance d'image, notation automatisée) n'ont
     * pas de signature HTML fiable et ne sont pas couvertes ici — nécessite
     * une approche différente si un jour utile. Liste de départ (2026-08).
     */
    public const AI_FEATURE_PROVIDERS = [
        // -- Référence explicite à un fournisseur de modèle --
        ['name' => 'OpenAI (API/widget)', 'slug' => 'openai', 'category' => 'model_provider', 'patterns' => ['api.openai.com', 'chat.openai.com', 'cdn.openai.com']],
        ['name' => 'Anthropic (API/widget)', 'slug' => 'anthropic', 'category' => 'model_provider', 'patterns' => ['api.anthropic.com', 'claude.ai']],
        ['name' => 'Google Gemini', 'slug' => 'gemini', 'category' => 'model_provider', 'patterns' => ['generativelanguage.googleapis.com', 'gemini.google.com']],
        ['name' => 'Microsoft Copilot', 'slug' => 'copilot', 'category' => 'model_provider', 'patterns' => ['copilot.microsoft.com']],
        ['name' => 'Mistral AI', 'slug' => 'mistral', 'category' => 'model_provider', 'patterns' => ['api.mistral.ai', 'mistral.ai']],
        // -- Chatbots/assistants conversationnels génériques (fournisseur du widget, pas forcément le modèle sous-jacent) --
        ['name' => 'Intercom (Fin AI)', 'slug' => 'intercom', 'category' => 'chatbot', 'patterns' => ['widget.intercom.io', 'js.intercomcdn.com']],
        ['name' => 'Drift', 'slug' => 'drift', 'category' => 'chatbot', 'patterns' => ['js.driftt.com', 'drift.com/widget']],
        ['name' => 'Zendesk (Answer Bot)', 'slug' => 'zendesk', 'category' => 'chatbot', 'patterns' => ['static.zdassets.com', 'zendesk.com/embeddable']],
        ['name' => 'Tidio', 'slug' => 'tidio', 'category' => 'chatbot', 'patterns' => ['code.tidio.co']],
        ['name' => 'Crisp', 'slug' => 'crisp', 'category' => 'chatbot', 'patterns' => ['client.crisp.chat']],
        ['name' => 'Chatbase', 'slug' => 'chatbase', 'category' => 'chatbot', 'patterns' => ['chatbase.co']],
        ['name' => 'Voiceflow', 'slug' => 'voiceflow', 'category' => 'chatbot', 'patterns' => ['voiceflow.com']],
        // -- Traduction automatique --
        ['name' => 'Weglot', 'slug' => 'weglot', 'category' => 'translation', 'patterns' => ['weglot.com']],
        ['name' => 'Google Translate (widget)', 'slug' => 'google_translate_widget', 'category' => 'translation', 'patterns' => ['translate.google.com/translate_a', 'translate.googleapis.com']],
        ['name' => 'DeepL (widget)', 'slug' => 'deepl_widget', 'category' => 'translation', 'patterns' => ['deepl.com/translator']],
    ];

    /**
     * Phrases indiquant la présence d'une déclaration d'accessibilité —
     * obligation légale pour le secteur public sous la directive UE
     * 2016/2102. Couverture identique à FOR_SALE_PHRASES : 24 langues UE +
     * turc, traductions non vérifiées par des locuteurs natifs.
     *
     * @var string[]
     */
    public const ACCESSIBILITY_STATEMENT_PHRASES = [
        'accessibility statement', 'déclaration d\'accessibilité', 'barrierefreiheitserklärung',
        'toegankelijkheidsverklaring', 'declaración de accesibilidad', 'declaração de acessibilidade',
        'dichiarazione di accessibilità', 'tillgänglighetsredogörelse', 'tilgængelighedserklæring',
        'saavutettavuusseloste', 'deklaracja dostępności', 'prohlášení o přístupnosti',
        'vyhlásenie o prístupnosti', 'izjava o dostopnosti', 'akadálymentesítési nyilatkozat',
        'declarație de accesibilitate', 'декларация за достъпност', 'izjava o pristupačnosti',
        'δήλωση προσβασιμότητας', 'juurdepääsetavuse avaldus', 'pieejamības paziņojums',
        'prieinamumo deklaracija', 'dikjarazzjoni ta\' aċċessibbiltà', 'ráiteas inrochtaineachta',
        'erişilebilirlik beyanı',
    ];

    /**
     * Phrases indiquant la présence de mentions légales/impressum —
     * obligation légale dans plusieurs pays UE (ex. Impressumspflicht en
     * Allemagne/Autriche). Même réserve de couverture que ci-dessus.
     *
     * @var string[]
     */
    public const LEGAL_NOTICE_PHRASES = [
        'mentions légales', 'impressum', 'aviso legal', 'note legali',
        'juridische mededeling', 'wettelijke vermeldingen', 'nota legal',
        'avisos legais', 'juridisk meddelelse', 'rättsligt meddelande',
        'oikeudellinen huomautus', 'nota prawna', 'právní upozornění',
        'právne upozornenie', 'pravno obvestilo', 'jogi nyilatkozat',
        'notă legală', 'правна информация', 'pravna napomena',
        'νομική σημείωση', 'õigusteave', 'juridisks paziņojums',
        'teisinė informacija', 'avviż legali', 'fógra dlíthiúil',
        'yasal uyarı', 'legal notice',
    ];

    /**
     * Plateformes d'avis clients — signal de confiance, mais aussi de
     * souveraineté (siège UE ou non). Détection par lien ET par présence
     * dans le HTML brut (les widgets d'avis se chargent souvent par
     * <script src="..."> plutôt que par lien cliquable, d'où les deux
     * méthodes combinées — voir Scanner::detectReviewPlatforms()).
     *
     * Classification région = siège social de l'entreprise, pas langue du
     * site. Trustpilot est danois (UE) malgré son usage très anglophone ;
     * Feefo est britannique (hors UE post-Brexit).
     *
     * @var array<int, array{name: string, slug: string, region: 'eu'|'non_eu', patterns: string[]}>
     */
    public const REVIEW_PLATFORMS = [
        // -- Sièges UE --
        ['name' => 'Trustpilot', 'slug' => 'trustpilot', 'region' => 'eu', 'patterns' => ['trustpilot.com']],
        ['name' => 'Avis Vérifiés', 'slug' => 'avis_verifies', 'region' => 'eu', 'patterns' => ['avis-verifies.com']],
        ['name' => 'Trusted Shops', 'slug' => 'trusted_shops', 'region' => 'eu', 'patterns' => ['trustedshops.com', 'trustedshops.de']],
        ['name' => 'eKomi', 'slug' => 'ekomi', 'region' => 'eu', 'patterns' => ['ekomi.com', 'ekomi.de', 'ekomi-consumer-network.com']],
        ['name' => 'Kiyoh', 'slug' => 'kiyoh', 'region' => 'eu', 'patterns' => ['kiyoh.com']],
        ['name' => 'Custplace', 'slug' => 'custplace', 'region' => 'eu', 'patterns' => ['custplace.com']],
        ['name' => 'Guest Suite', 'slug' => 'guest_suite', 'region' => 'eu', 'patterns' => ['guest-suite.com']],

        // -- Sièges hors UE, pour comparaison --
        ['name' => 'Google Reviews', 'slug' => 'google_reviews', 'region' => 'non_eu', 'patterns' => ['g.page/r/', 'search.google.com/local/writereview']],
        ['name' => 'Feefo', 'slug' => 'feefo', 'region' => 'non_eu', 'patterns' => ['feefo.com']],
        ['name' => 'Yotpo', 'slug' => 'yotpo', 'region' => 'non_eu', 'patterns' => ['yotpo.com']],
        ['name' => 'Bazaarvoice', 'slug' => 'bazaarvoice', 'region' => 'non_eu', 'patterns' => ['bazaarvoice.com']],
        ['name' => 'TripAdvisor', 'slug' => 'tripadvisor', 'region' => 'non_eu', 'patterns' => ['tripadvisor.com', 'tripadvisor.fr']],
        ['name' => 'Yelp', 'slug' => 'yelp', 'region' => 'non_eu', 'patterns' => ['yelp.com', 'yelp.fr']],
    ];
}
