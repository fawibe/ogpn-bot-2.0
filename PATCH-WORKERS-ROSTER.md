# Rôles par défaut des workers + contrôle de coût + cron URL Infomaniak

## Correction importante par rapport à la première version de ce document

Ton dump de production (`pd9es9_ogpn`) montre que `v2_workers` a déjà 5 workers
réels et actifs, avec des jetons déjà en usage côté GitHub Actions/Render/OVH
et côté Infomaniak :

| worker_id | plateforme | rôle actuel | capacité |
|---|---|---|---|
| `github-1` | github | `scan` | `high` |
| `ovh-1` | ovh | `discovery` | `medium` |
| `render-1` | render | `scan` | `high` |
| `infomaniak-1` | infomaniak | `discovery` | `low` |
| `infomaniak-test` | infomaniak | `scan` | `low` |

**Ne lance jamais `bin/create-worker.php` avec un de ces 5 identifiants** —
`ON DUPLICATE KEY UPDATE` régénère un nouveau jeton à chaque exécution, ce
qui casserait immédiatement le worker concerné (le jeton stocké côté
GitHub/Render/Infomaniak ne correspondrait plus au `token_hash` en base).

Bonne nouvelle : tu as déjà, de fait, exactement la répartition que je
proposais — `infomaniak-1` et `infomaniak-test` sont déjà tes deux identités
découverte/scan séparées pour Infomaniak. Rien à créer.

## Ce qu'il reste réellement à faire

**1. Plafonner le coût de `render-1`** — seul changement nécessaire, ne
touche pas au jeton. Deux façons, au choix :

- Via la console (`public/console.php`, après connexion) : ligne `render-1`,
  champ "max_runs" du formulaire "Modifier" → mets `20` (ou le chiffre que
  tu veux) → OK.
- Ou en SQL direct si tu préfères ne pas passer par l'interface :
  ```sql
  UPDATE v2_workers SET max_runs_per_day = 20 WHERE worker_id = 'render-1';
  ```

**2. Câbler `infomaniak-test` sur l'URL de cron** (`public/cron-worker.php`),
si tu veux qu'il tourne via le planificateur Infomaniak plutôt que
manuellement. Tu as déjà son jeton (`generated-tokens.php` sur le serveur) —
pas besoin d'en générer un nouveau. URL à mettre dans le planificateur :

```
https://user:motdepasse@bot.ogpn.eu/cron-worker.php?worker=infomaniak-test&token=LE_JETON_EXISTANT
```

(remplace `user:motdepasse` par les identifiants Basic Auth une fois
`storage/secrets/htpasswd-cron` configuré, et adapte le chemin
`AuthUserFile` dans `public/.htaccess` — voir plus bas).

**3. Rien à faire pour `github-1`, `ovh-1`, `render-1`, `infomaniak-1`** — ils
tournent déjà avec la bonne architecture, leurs rôles actuels sont
raisonnables tels quels.

## Si tu veux ajouter un nouveau worker plus tard

Uniquement pour un `worker_id` qui n'existe pas encore :

```bash
php bin/create-worker.php NOUVEL_ID plateforme role capacite [plafond_optionnel]
```

## Entrée URL Infomaniak (`public/cron-worker.php`)

Réutilise exactement la logique de `worker/run.php` (même code, déclenché
par une URL au lieu du CLI). Double protection :

1. Basic Auth Apache (`public/.htaccess`, bloc `<Files "cron-worker.php">`)
   → `storage/secrets/htpasswd-cron`, géré séparément sur le serveur.
   Le chemin `AuthUserFile` est un placeholder (`CHEMIN_A_ADAPTER`) — à
   remplacer par le chemin absolu réel sur Infomaniak avant utilisation.
2. Le jeton de worker (`?token=...`), vérifié normalement par `Auth::worker()`.

## Vérifié

- Lint complet (0 erreur) et suite de tests fournie (100% OK).
- `bin/install.php` testé contre une copie fidèle du schéma de production
  réel (22 tables extraites de ton dump) : migration propre, aucune perte de
  données sur des lignes réelles chargées pour le test.
- Plafond de coût testé en conditions réelles (MariaDB) : bloque bien au 3ᵉ
  appel sur un plafond de 2.
