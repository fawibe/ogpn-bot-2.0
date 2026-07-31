# OGPN-BOT 2.0 alpha4

Cette version conserve le pipeline alpha3 opérationnel (Common Crawl, orchestration, workers, files, API, console et protections SSRF) et réintègre les principales familles d’analyse de la V1.

## Ajouts

- référentiel `providers.json` de la V1 ;
- détection des dépendances, catégories et statut UE ;
- robots.txt et politiques des principaux robots IA ;
- fichiers `llms.txt`, `ai.txt`, `tdmrep.json`, `security.txt` et `humans.txt` ;
- signaux CMP, consentement et friction automatique ;
- fournisseurs d’identité numérique ;
- catégories sectorielles multilingues ;
- dictionnaire anglais commun minimal et dictionnaires complets initiaux FR, EN, DE, ES, NL, IT et PT ;
- historique des scans dans `v2_scan_history` ;
- migration idempotente V1 vers V2 ;
- agrégats dans `v2_report_statistics` ;
- wrappers API présents dans `public/api` ;
- diagnostic des erreurs API avec l’URL appelée.

## Mise à jour Infomaniak

Conserver `storage/secrets/server-config.php` et `storage/secrets/generated-tokens.php`, remplacer les autres fichiers, puis exécuter :

```bash
php bin/install.php
php tests/run.php
php bin/diagnose.php
php bin/migrate-v1.php --dry-run
php bin/migrate-v1.php --execute
php bin/migrate-v1.php --verify
php bin/update-statistics.php
php worker/run.php
```

La migration ne supprime aucune table ni donnée V1. Elle fusionne les files et n’écrase pas un résultat V2 plus récent.

## Limite connue

Les dictionnaires livrés couvrent complètement le premier lot de sept langues. Les autres langues européennes doivent encore être enrichies et validées avant d’être qualifiées de dictionnaires complets. Le dictionnaire anglais commun reste utilisé comme fallback.
