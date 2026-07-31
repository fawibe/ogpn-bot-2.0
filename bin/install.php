<?php
declare(strict_types=1);require dirname(__DIR__).'/vendor/autoload.php';use Ogpn\Bot\Bootstrap;$pdo=Bootstrap::pdo();$sql=file_get_contents(dirname(__DIR__).'/processing/schema.sql');$pdo->exec($sql);echo "Schéma OGPN-BOT 2.0 installé.\n";
