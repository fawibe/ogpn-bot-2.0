# Installation/mise à jour Infomaniak — OGPN-BOT 2.0 consolidée RC1

## 1. Sauvegarde

```bash
cd ~/sites/bot.ogpn.eu
cp -a 2 "2-backup-$(date +%Y%m%d-%H%M)"
```

Conserver impérativement :

- `storage/secrets/server-config.php`
- `storage/secrets/generated-tokens.php`

## 2. Copie de la version

Décompresser dans un dossier temporaire puis utiliser `rsync` :

```bash
rsync -av \
  --exclude='storage/secrets/server-config.php' \
  --exclude='storage/secrets/generated-tokens.php' \
  dossier-extrait/ ~/sites/bot.ogpn.eu/2/
```

L’archive contient directement les fichiers à la racine ; elle ne contient pas de dossier parent supplémentaire.

## 3. Mise à jour non destructive

```bash
cd ~/sites/bot.ogpn.eu/2
php bin/install.php
php tests/run-all.php
php bin/diagnose.php
```

`bin/install.php` ajoute les colonnes et tables manquantes sans effacer les données V1 ou V2.

## 4. Validation réseau avant reprise des crons

```bash
php bin/validate-network-sample.php
```

Contrôler les résultats, puis lancer un test de trois domaines dans le pipeline distribué avec `OGPN_MAX_SCAN_DOMAINS=3`.

## 5. Migration V1

Si elle n’a pas déjà été effectuée :

```bash
php bin/migrate-v1.php --dry-run
php bin/migrate-v1.php --execute
php bin/migrate-v1.php --verify
php bin/update-statistics.php
```

La migration est idempotente et ne supprime pas les tables V1.

## 6. Common Crawl

Une seule page Common Crawl est traitée par exécution. Les erreurs réseau utilisent un backoff persistant et arrêtent le cycle courant afin d’éviter de renforcer un blocage temporaire.
