<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Ogpn\Bot\Engine\CategoryClassifier;
use Ogpn\Bot\Engine\ProviderRegistry;

$cases = [
    ['rail', 'fr', '<html lang="fr"><title>Billets de train</title><body>Réservez vos billets de train, horaires en gare et info trafic.</body></html>', 'transport', 'rail'],
    ['public_bus', 'fr', '<html lang="fr"><title>Transport public</title><body>Bus, tram et métro. Horaires et voyages en bus.</body></html>', 'transport', 'public_transport'],
    ['freight', 'fr', '<html lang="fr"><title>Logistique</title><body>Transport de marchandises, fret, entreposage et douane.</body></html>', 'transport', 'freight_logistics'],
    ['airbnb', 'fr', '<html lang="fr"><title>Locations de vacances</title><body>Réservez un hébergement touristique, une chambre ou un séjour.</body></html>', 'travel', 'hotel'],
    ['zalando', 'fr', '<html lang="fr"><title>Boutique mode</title><body>Ajouter au panier, commander en ligne, livraison et retour gratuit.</body></html>', 'online_shop', 'style_and_fashion'],
    ['bank', 'fr', '<html lang="fr"><title>Banque</title><body>Compte bancaire, carte de crédit, prêt personnel et épargne.</body></html>', 'personal_finance', 'loans'],
    ['hosting', 'fr', '<html lang="fr"><title>Hébergement cloud</title><body>Hébergement web, cloud computing et serveurs.</body></html>', 'technology_and_computing', 'hosting_cloud'],
    ['training', 'fr', '<html lang="fr"><title>Centre de formation</title><body>Formation professionnelle avec certification.</body></html>', 'education', 'vocational_training'],
    ['restaurant', 'fr', '<html lang="fr"><title>Restaurant</title><body>Notre menu, plat du jour et réservation de table.</body></html>', 'food_and_drink', 'restaurant'],
    ['kitchen', 'fr', '<html lang="fr"><title>Cuisines équipées</title><body>Cuisine sur mesure et rénovation de cuisine.</body></html>', 'home_and_garden', 'kitchen_design'],
];

$classifier = new CategoryClassifier();
$errors = 0;
foreach ($cases as [$name, $lang, $html, $tier1, $tier2]) {
    $r = $classifier->classify($html, $lang);
    $ok = $r['category'] === $tier1 && $r['category_tier2'] === $tier2;
    echo ($ok ? '[OK]   ' : '[FAIL] '), $name, ' => ', $r['category'], '/', ($r['category_tier2'] ?? '-'), ' confidence=', $r['confidence'], PHP_EOL;
    if (!$ok) $errors++;
}

$providerData = json_decode(file_get_contents(dirname(__DIR__) . '/data/providers.json'), true);
$providerNames = array_column($providerData['providers'] ?? [], 'name');
$providerOk = in_array('Google Analytics', $providerNames, true) && in_array('Google Fonts', $providerNames, true);
echo ($providerOk ? '[OK]   ' : '[FAIL] '), 'provider reference loaded', PHP_EOL;
if (!$providerOk) $errors++;

exit($errors === 0 ? 0 : 1);
