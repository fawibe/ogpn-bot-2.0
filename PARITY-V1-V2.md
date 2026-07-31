# Parité V1 → V2 consolidée

## Conservé depuis la V2

- Common Crawl, files, réservation, workers distribués ;
- API protégée, tokens individuels, console et diagnostic ;
- SSRF, limites réseau, historique, migration et statistiques.

## Repris de la V1

- scanner multi-requêtes et analyse des variantes linguistiques ;
- taxonomie hiérarchique de 31 catégories ;
- dictionnaires, signaux positifs/négatifs et désambiguïsation ;
- fournisseurs, tracking, consentement, robots IA, parking, identité, avis ;
- TLD, groupes géopolitiques, hébergement et scoring.

## Améliorations apportées

- les balises HTML sont remplacées par des espaces avant normalisation, évitant la fusion de mots ;
- le fret est séparé du voyage de passagers ;
- les blocages HTTP et analyses tronquées ne génèrent pas de faux scores ;
- les redirections sont un état simple et la destination finale est analysée ;
- le traitement d’un lot utilise `curl_multi` au lieu d’une boucle séquentielle ;
- les référentiels sont versionnés et contrôlés par SHA-256 ;
- les erreurs Common Crawl déclenchent un backoff persistant ;
- l’encodage JSON remplace proprement les séquences UTF-8 invalides.

## Tests exécutés deux fois

- syntaxe de tous les fichiers PHP ;
- 31/31 catégories de niveau 1 ;
- sous-catégories rail, bus, fret, hôtel, e-commerce, prêts, cloud, formation, restaurant, cuisine ;
- fournisseurs et prévention des faux positifs sociaux ;
- consentement, identité, parking ;
- redirections, qualité, blocages, troncature et scoring ;
- dictionnaires et doublons.

La validation réseau doit encore être exécutée sur Infomaniak, car l’environnement de construction ne dispose pas d’un accès réseau PHP exploitable.
