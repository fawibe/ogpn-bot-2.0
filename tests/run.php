<?php
declare(strict_types=1);require dirname(__DIR__).'/vendor/autoload.php';use Ogpn\Bot\{Domain,NetworkGuard,Bootstrap};$tests=[];$test=function(string $name,callable $fn)use(&$tests){try{$fn();$tests[]=[$name,true,''];}catch(Throwable $e){$tests[]=[$name,false,$e->getMessage()];}};$assert=function(bool $v,string $m='assertion failed'){if(!$v)throw new RuntimeException($m);};
$test('Domaine simple',fn()=> $assert(Domain::fromUrl('https://www.example.fr/a')==='example.fr'));
$test('Suffixe composé',fn()=> $assert(Domain::fromUrl('https://blog.example.co.uk/a')==='example.co.uk'));
$test('Rejet IP',fn()=> $assert(Domain::fromUrl('https://127.0.0.1/')===null));
$test('SSRF localhost',function(){try{NetworkGuard::assertPublicHost('127.0.0.1');throw new RuntimeException('non bloqué');}catch(RuntimeException $e){if($e->getMessage()==='non bloqué')throw $e;}});
$test('Autoload et defaults',fn()=> $assert(is_array(Bootstrap::defaults())));
$failed=0;foreach($tests as [$n,$ok,$d]){echo ($ok?'[OK]   ':'[FAIL] ').$n.($d?' — '.$d:'').PHP_EOL;if(!$ok)$failed++;}exit($failed?1:0);
