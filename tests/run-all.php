<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$tests = [
    'run.php',
    'engine-parity.php',
    'taxonomy-coverage.php',
    'provider-detection.php',
    'analysis-signals.php',
    'quality-rules.php',
    'result-mapper.php',
    'scanner-flow.php',
];

$failures = 0;
foreach ($tests as $test) {
    echo PHP_EOL, '=== ', $test, ' ===', PHP_EOL;
    passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/' . $test), $code);
    if ($code !== 0) {
        $failures++;
    }
}

echo PHP_EOL, '=== validate-dictionaries.php ===', PHP_EOL;
passthru(PHP_BINARY . ' ' . escapeshellarg($root . '/bin/validate-dictionaries.php'), $code);
if ($code !== 0) {
    $failures++;
}

if ($failures > 0) {
    fwrite(STDERR, "ÉCHECS: {$failures}\n");
    exit(1);
}

echo PHP_EOL, "VALIDATION HORS RÉSEAU : OK\n";
