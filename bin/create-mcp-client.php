<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Ogpn\Bot\Bootstrap;
$id=$argv[1]??'chatgpt-ogpn';
$token=bin2hex(random_bytes(32));
$perms=['mcp.status.read','mcp.domain.read','mcp.statistics.read','mcp.configuration.read'];
$s=Bootstrap::pdo()->prepare('INSERT INTO v2_mcp_clients(client_id,token_hash,permissions,enabled) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),permissions=VALUES(permissions),enabled=1');
$s->execute([$id,hash('sha256',$token),json_encode($perms)]);
echo "Client MCP: $id\nToken (affiché une seule fois): $token\n";
