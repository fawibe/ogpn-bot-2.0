<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Ogpn\Bot\{Bootstrap,McpServer};
$c=Bootstrap::serverConfig()['mcp']??[];
if(empty($c['enabled'])) Bootstrap::json(['error'=>'mcp_disabled'],503);
$pdo=Bootstrap::pdo(); $client=McpServer::authenticate($pdo);
$body=json_decode(file_get_contents('php://input'),true);
if(!is_array($body)) Bootstrap::json(['error'=>'invalid_payload'],422);
Bootstrap::json(McpServer::handle($pdo,$client,$body));
