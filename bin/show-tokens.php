<?php
declare(strict_types=1);$p=dirname(__DIR__).'/storage/secrets/generated-tokens.php';if(!is_file($p)){fwrite(STDERR,"Aucun fichier de tokens générés.\n");exit(1);}foreach((array)require $p as $id=>$token)echo "$id = $token\n";
