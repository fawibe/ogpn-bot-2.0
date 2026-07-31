<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Ogpn\Bot\Engine\CategoryClassifier;

$dict = json_decode(file_get_contents(dirname(__DIR__) . '/dictionaries/fr.json'), true);
$classifier = new CategoryClassifier();
$errors = 0;
$total = 0;
foreach ($dict as $category => $terms) {
    if (str_starts_with((string)$category, '_') || !is_array($terms) || count($terms) < 2) continue;
    $selected = array_slice($terms, 0, min(8, count($terms)));
    $title = implode(' ', array_slice($selected, 0, 3));
    $body = implode('. ', array_merge($selected, $selected));
    $html = '<html lang="fr"><head><title>' . htmlspecialchars($title, ENT_QUOTES) . '</title><meta name="description" content="' . htmlspecialchars($body, ENT_QUOTES) . '"></head><body><h1>' . htmlspecialchars($title, ENT_QUOTES) . '</h1><p>' . htmlspecialchars($body, ENT_QUOTES) . '</p></body></html>';
    $result = $classifier->classify($html, 'fr');
    $ok = $result['category'] === $category;
    echo ($ok ? '[OK]   ' : '[FAIL] '), $category, ' => ', $result['category'], ' (', $result['confidence'], ')', PHP_EOL;
    $total++;
    if (!$ok) $errors++;
}
echo "TOTAL={$total}; FAIL={$errors}\n";
exit($errors === 0 ? 0 : 1);
