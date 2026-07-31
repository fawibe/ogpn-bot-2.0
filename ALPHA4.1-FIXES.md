# OGPN-BOT 2.0 alpha4.1

Correctif cohérent du pipeline scan :

- gestion des réponses HTML tronquées sans erreur cURL ;
- propagation de `response_truncated` et `analysis_complete` ;
- aucun score de souveraineté publié pour une analyse incomplète ;
- encodage JSON robuste côté worker ;
- ingestion alignée sur le payload réel du scanner ;
- historique enrichi avec les colonnes nécessaires aux requêtes statistiques ;
- qualité `partial` pour les pages tronquées ;
- compteur `ingested` indépendant du nombre de réservations libérées ;
- limite de test possible avec `OGPN_MAX_SCAN_DOMAINS` et `OGPN_SCAN_CHUNK_SIZE` ;
- diagnostic utilisant la version réelle du fichier `VERSION`.
