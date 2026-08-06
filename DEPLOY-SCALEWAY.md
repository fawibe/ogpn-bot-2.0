# Déploiement du worker OGPN-BOT sur Scaleway

## Architecture

GitHub Actions construit l'image lors d'une modification de la branche `main`.
Il n'exécute plus les scans planifiés. Scaleway Serverless Jobs exécute ensuite
le worker selon son propre déclencheur cron.

## 1. Registre Scaleway

- Région : Paris (`fr-par`)
- Namespace privé : `ogpn`
- Image : `ogpn-web-scanner`
- Image complète : `rg.fr-par.scw.cloud/ogpn/ogpn-web-scanner:latest`

## 2. Identité IAM de construction

Créer l'application IAM `github-registry-builder`, puis une politique limitée :

- principal : application `github-registry-builder` ;
- portée : projet `OGPN Web Observatory` ;
- permission : `ContainerRegistryFullAccess`.

Créer une clé API pour cette application. Copier sa clé secrète au moment où
Scaleway l'affiche. Ne jamais la placer dans le dépôt.

Dans le dépôt GitHub, ouvrir `Settings > Secrets and variables > Actions`, puis
créer le secret de dépôt :

- `SCW_SECRET_KEY` : clé secrète de l'application IAM Scaleway.

Le workflow `.github/workflows/build-scaleway.yml` construit et publie les tags
`latest` et l'identifiant exact du commit.

## 3. Worker OGPN

Sur le serveur qui héberge l'API OGPN-BOT, créer le worker :

```bash
php bin/create-worker.php scaleway-1 scaleway auto medium 96
```

La commande affiche une seule fois le jeton OGPN du worker. Le conserver dans
un gestionnaire de mots de passe. Ne jamais le ajouter au dépôt GitHub ni à
l'image Docker.

Le plafond de 96 exécutions par jour correspond à un déclenchement toutes les
15 minutes. Pour la période de test, un plafond inférieur peut être choisi.

## 4. Serverless Job

- Source : Scaleway Container Registry
- Registry region : `PAR`
- Namespace : `ogpn`
- Image : `ogpn-web-scanner`
- Tag : `latest`
- Job name : `ogpn-web-scanner`
- Job region : Paris
- CPU initial : 280 mVCPU
- Mémoire initiale : 512 MB
- Durée maximale : 12 minutes
- Tentatives automatiques : 1 au début

Variables d'environnement :

- `OGPN_API_URL=https://bot.ogpn.eu/api`
- `OGPN_WORKER_ID=scaleway-1`
- `OGPN_API_TOKEN=<jeton créé à l'étape 3>`
- `OGPN_MAX_SCAN_DOMAINS=45`
- `OGPN_SCAN_CHUNK_SIZE=10`

Le jeton OGPN est une variable sensible. Si l'option Secret Manager n'est pas
utilisée, il faut limiter strictement les droits d'accès au projet Scaleway et
renouveler ce jeton après tout soupçon d'exposition.

## 5. Déclencheur et bascule

Commencer avec un déclencheur toutes les 30 minutes :

```cron
7,37 * * * *
```

Ce décalage évite les minutes les plus chargées. Vérifier au minimum 24 heures
de journaux et de statistiques avant de désactiver `.github/workflows/worker.yml`.
Passer ensuite à toutes les 15 minutes si les coûts et la durée sont corrects :

```cron
7,22,37,52 * * * *
```

Désactiver Render seulement après 48 à 72 heures de fonctionnement stable. Le
workflow de construction Scaleway doit rester actif ; seul l'ancien workflow
GitHub planifié doit être supprimé après validation.
