# Patch post-audit V1→V2 : cascade TLD, taux de disparition, nettoyage

Suite à l'audit de parité entre l'ancienne architecture (bin/scan.php,
scan-queue.php...) et la réécriture consolidée 2.0, trois écarts avaient été
identifiés. Ce patch les corrige.

## 1. Cascade TLD à paliers (réintégrée, généralisée aux deux pipelines)

`api/scan-claim.php` ne faisait qu'un boost binaire sur 5 TLD
(`fr,be,lu,ch,mc`). Remplacé par une cascade à 3 paliers, calculée par
`TldRegistry::priorityTier()` (nouvelle méthode partagée) :

- **Palier 0** : TLD listés dans `queue.priority_tlds` (config, pilotable).
- **Palier 1** : reste de l'Europe politique (UE/EEE/COE).
- **Palier 2** : candidats UE / Conseil de l'Europe élargi (`EU_CANDIDATE`).

L'`ORDER BY` ne fait que réordonner — un TLD hors de tout palier tombe en
dernier (palier 3) mais n'est jamais exclu. Jamais bloquant.

La même cascade s'applique maintenant côté **découverte**
(`src/CommonCrawl/Seeder.php::ensureJobs`) via un round-robin pondéré
(`weightedTldSequence()`) : palier 0 visité 4x plus souvent, palier 1 2x,
palier 2 1x — donc les domaines prioritaires sont *découverts* plus vite,
pas seulement scannés en premier une fois découverts.

Testé en conditions réelles (MariaDB) : ordre de sortie confirmé
`fr,be > es,de > ba,tr` sur un échantillon mixte. Séquence pondérée
confirmée `4/4/2/1/1` sur `['fr','be','de','tr','ba']`.

## 2. Taux de disparition / mortalité numérique (réintégré)

`unreachable_streak`, `first_unreachable_at`, `confirmed_unreachable`
existaient encore comme colonnes lues depuis V1 dans `bin/migrate-v1.php`,
mais n'étaient plus ni dans le schéma `v2_domains` ni mis à jour à chaque
scan — perdus silencieusement dans la consolidation alpha4/5.

- **Schéma** (`processing/schema.sql`) : 3 colonnes + index ajoutés en
  `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`, idempotent comme le reste du
  fichier — s'applique via `bin/setup.php` ou `bin/install.php` sans rien
  casser sur une base existante.
- **Mise à jour à chaque scan** (`api/scan-ingest.php`) : logique entièrement
  en SQL (`INSERT ... ON DUPLICATE KEY UPDATE`), aucun SELECT séparé par
  domaine. Seuil double identique à V1 : ≥3 échecs consécutifs **et** ≥7
  jours (604800s) depuis le premier échec de la série. Un seul succès (même
  un 403/blocked) remet le compteur à 0.
- **Migration V1** (`bin/migrate-v1.php`) : les 3 colonnes sont maintenant
  reprises depuis l'ancienne table `domains` au lieu d'être ignorées.

Testé en conditions réelles (MariaDB, requête `INSERT...ON DUPLICATE KEY
UPDATE` exacte, 3 scénarios) :
- 2 échecs puis 1 succès → streak=0, confirmed=0 ✓
- 3 échecs rapprochés (< 7 jours) → streak=3, confirmed=0 ✓
- 3 échecs avec premier échec vieux de 8 jours → streak=3, confirmed=1 ✓

## 3. Nettoyage du code mort

Supprimés (jamais appelés nulle part, vérifié par grep sur tout le
projet — `NomClasse::` et `use ...\NomClasse`) :

- `src/Analysis/GovernanceFiles.php`
- `src/Analysis/HostingResolver.php`
- `src/Analysis/HtmlAnalyzer.php` (version dégradée : pas de `domeinnaam`,
  `social_presence`/`review_platforms` vides en dur)
- `src/Analysis/LanguageClassifier.php`
- `src/Analysis/ProviderRegistry.php` (pointait vers un mauvais chemin,
  `processing/data/providers.json` au lieu de `data/providers.json`)
- `src/Analysis/RobotsAnalyzer.php`
- `src/Http.php` (aucune protection SSRF, doublon de `Engine/Http.php`)

Toute la logique réelle vit dans `src/Engine/Scanner.php` et les classes
`Engine/*`, confirmé par la suite de tests (`tests/run-all.php`, 100% OK)
et par lint complet de tous les fichiers PHP restants (0 erreur).

## Non touché dans ce patch

Les autres points relevés lors de l'audit (rôle de chaque worker
GitHub/OVH/Render dans le nouveau modèle `preferred_role`, découpage
éventuel du monolithe `Scanner.php`) restent à traiter séparément.
