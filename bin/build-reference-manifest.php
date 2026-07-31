<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'data/providers.json',
    'dictionaries/categories.json',
    'dictionaries/common-minimal.json',
    'dictionaries/fr.json',
    'dictionaries/en.json',
    'dictionaries/tr.json',
    'dictionaries/de.json',
    'dictionaries/es.json',
    'dictionaries/nl.json',
    'dictionaries/it.json',
    'dictionaries/pt.json',
];

$files = [];
foreach ($paths as $relative) {
    $absolute = $root . '/' . $relative;
    if (!is_file($absolute)) {
        throw new RuntimeException("Référentiel absent : {$relative}");
    }
    $files[$relative] = [
        'sha256' => hash_file('sha256', $absolute),
        'bytes' => filesize($absolute),
    ];
}

$manifest = [
    'format' => 1,
    'engine_version' => trim((string) file_get_contents($root . '/VERSION')),
    'generated_at' => gmdate('c'),
    'files' => $files,
];

$out = $root . '/data/reference-manifest.json';
file_put_contents($out, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo "Manifeste écrit : {$out}\n";
