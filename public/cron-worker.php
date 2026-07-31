<?php
declare(strict_types=1);

/**
 * Point d'entrée URL pour les crons qui ne savent faire qu'un GET/POST sur
 * une adresse (webcron Infomaniak : 1 appel/heure max, pas de CLI, pas de
 * variables d'environnement). Ne duplique aucune logique — se contente de
 * positionner OGPN_WORKER_ID/OGPN_API_TOKEN puis d'inclure worker/run.php,
 * exactement le même code que GitHub Actions/OVH/Render exécutent en CLI.
 *
 * Protection à deux niveaux, comme l'ancien refresh-domains.php en V1 :
 * 1) Basic Auth au niveau Apache (voir .htaccess + storage/secrets/htpasswd-cron,
 *    géré séparément sur le serveur, jamais livré dans les zips) ;
 * 2) le jeton de worker lui-même (?token=...), vérifié par Auth::worker()
 *    comme pour n'importe quel autre worker.
 *
 * Permet aussi d'avoir plusieurs identités Infomaniak distinctes (ex.
 * infomaniak-commoncrawl et infomaniak-scan, chacune sur sa propre entrée de
 * planificateur Infomaniak) : chaque cron URL respecte individuellement la
 * limite "1x/heure", et ensemble elles couvrent découverte + scan sans
 * dépasser cette limite par worker.
 *
 * Exemple d'URL à configurer dans le planificateur de tâches Infomaniak :
 *   https://bot.ogpn.eu/cron-worker.php?worker=infomaniak-commoncrawl&token=XXXX
 *   https://bot.ogpn.eu/cron-worker.php?worker=infomaniak-scan&token=YYYY
 */

$id = (string) ($_GET['worker'] ?? '');
$token = (string) ($_GET['token'] ?? '');

if ($id === '' || $token === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Paramètres requis : ?worker=ID&token=JETON\n";
    exit(1);
}

putenv('OGPN_WORKER_ID=' . $id);
putenv('OGPN_API_TOKEN=' . $token);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

require dirname(__DIR__) . '/worker/run.php';
