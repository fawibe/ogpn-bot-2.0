# Audit alpha2 et corrections alpha3

## Défauts bloquants observés dans alpha2

- aucun producteur de jobs Common Crawl ;
- `seed-example.sql` statique et non exploitable en production ;
- options d’activation de la console non respectées par le planificateur ;
- journal `v2_worker_runs` jamais alimenté ;
- absence des endpoints `fail` ;
- worker Infomaniak nécessitant des variables d’environnement manuelles ;
- URL Render encore configurée avec `/2/api` alors que le document root avait déjà basculé ;
- règle `.htaccess` circulaire ;
- permissions MCP déclarées mais non contrôlées outil par outil ;
- aucune limitation de débit ;
- absence de CSRF dans la console ;
- scanner suivant automatiquement les redirections sans contrôle SSRF ;
- réduction au domaine enregistrable limitée à quelques cas ;
- absence de stratégie HTTP après échec HTTPS ;
- limites de taille et erreurs de réservation insuffisamment gérées ;
- installation et création des tokens trop manuelles.

## Corrections alpha3

- seeder Common Crawl automatique avec détection du dernier crawl et pagination par TLD ;
- état d’avancement `v2_common_crawl_tld_state` ;
- jobs en reprise, échec et dead-letter ;
- orchestration tenant compte des activations et de l’arrêt d’urgence ;
- journalisation complète des runs ;
- installation unique `php bin/setup.php` ;
- tokens générés et sauvegardés dans un fichier privé mode 600 ;
- worker Infomaniak exécutable sans variable d’environnement ;
- workers distants toujours configurables par variables ;
- limites de débit API et MCP ;
- contrôle des permissions MCP ;
- CSRF et gestion des workers dans la console ;
- diagnostic étendu ;
- protection SSRF avant chaque requête et chaque redirection ;
- tailles, délais et redirections plafonnés ;
- reprise HTTP si HTTPS est indisponible ;
- ingestion et réservations transactionnelles ;
- Healthchecks déclenchés côté serveur central ;
- tests CLI intégrés.

## Périmètre fonctionnel

Le pipeline découverte → file → scan HTTP → ingestion → rescan est opérationnel. Les analyseurs métier avancés de la V1 ne figurent pas dans l’archive alpha2 fournie pour l’audit et ne peuvent donc pas être portés fidèlement dans cette livraison sans leurs sources.
