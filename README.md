# OGPN-BOT 2.0 — version consolidée RC1

Cette version associe l’orchestration distribuée de la V2 au moteur d’analyse éprouvé de la V1, corrigé et rendu modulaire.

## Fonctions couvertes

- découverte progressive via Common Crawl avec reprise et backoff persistant ;
- files transactionnelles, workers distribués, historique et rescans ;
- redirections suivies, avec état `redirect` et analyse de la destination finale ;
- détection des pages bloquées, challenges, parking et domaines à vendre ;
- taxonomie OGPN inspirée de l’IAB : 31 catégories de niveau 1 et sous-catégories ;
- dictionnaire commun minimal anglais, dictionnaires FR/EN complets et extensions linguistiques versionnées ;
- dépendances, fournisseurs, rôles, tracking, publicité, CMP et consentement ;
- authentification et identités numériques ;
- robots.txt, robots IA et fichiers de gouvernance ;
- hébergement/ASN/territoire lorsque MaxMind est configuré ;
- scores avec état de qualité : aucun score parfait déduit d’une collecte incomplète ;
- migration V1 idempotente et statistiques agrégées.

## Architecture évolutive

Les référentiels sont indépendants du moteur :

- `dictionaries/` : taxonomie et langues ;
- `data/providers.json` : fournisseurs et dépendances ;
- `data/reference-manifest.json` : empreintes et version du moteur ;
- `src/Engine/` : moteur métier issu de la V1 ;
- `src/Scanner/` : adaptateur vers le contrat V2 ;
- `worker/` et `api/` : orchestration distribuée.

Une mise à jour de dictionnaire ou de fournisseur ne nécessite donc pas de réécrire les files, l’API ou les workers.

## Validation

```bash
php tests/run-all.php
php bin/validate-network-sample.php
php bin/diagnose.php
```

La première commande est entièrement hors réseau. La deuxième teste le moteur sur SNCB, SNCF, TEC, Airbnb, Zalando et CBC et écrit les résultats dans `storage/`.

## Limites documentées

Les dictionnaires FR et EN sont complets pour les 31 catégories. Le turc couvre 30 catégories. Les dictionnaires DE, ES, NL, IT et PT sont encore partiels et déclarés comme tels dans `dictionaries/manifest.json`. Les résultats négatifs sur une application fortement JavaScript restent indiqués comme limités ou inconclusifs lorsque la couverture ne permet pas une conclusion fiable.
