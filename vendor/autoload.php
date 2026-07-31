<?php
spl_autoload_register(function(string $class): void {
    $prefix='Ogpn\\Bot\\';
    if(!str_starts_with($class,$prefix)) return;
    $file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file)) require $file;
});
