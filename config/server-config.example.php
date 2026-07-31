<?php
declare(strict_types=1);
return [
    'database' => [
        'dsn' => 'mysql:host=pd9es9.myd.infomaniak.com;dbname=CHANGE_ME;charset=utf8mb4',
        'user' => 'CHANGE_ME',
        'password' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
    'admin_token_hash' => password_hash('CHANGE_ME_ADMIN_TOKEN', PASSWORD_DEFAULT),
    'base_url' => 'https://bot.ogpn.eu',
    'local_worker_id' => 'infomaniak-1',
    'maxmind' => [
        'enabled' => false,
        'country_db' => '',
        'asn_db' => '',
    ],
    'healthcheck' => [
        'enabled' => false,
        'discovery_url' => '',
        'scan_url' => '',
    ],
    'mcp' => [
        'enabled' => false,
        'rate_limit_per_minute' => 30,
    ],
];
