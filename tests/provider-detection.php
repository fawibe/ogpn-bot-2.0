<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Ogpn\Bot\Engine\{Http,Scanner};

$scanner=new Scanner(new Http());
$ref=new ReflectionClass($scanner);$method=$ref->getMethod('detectDependencies');$method->setAccessible(true);
$cases=[
 'gtag_path'=>['<script src="https://www.googletagmanager.com/gtag/js?id=G-TEST"></script>','Google Tag Manager'],
 'meta_pixel_active'=>['<script src="https://connect.facebook.net/fr_FR/fbevents.js"></script>','Meta Pixel'],
 'social_link_not_pixel'=>['<a href="https://www.facebook.com/example">Facebook</a>',null],
 'trustarc_cmp'=>['<script src="https://consent.trustarc.com/notice.js"></script>','TrustArc'],
];
$fail=0;
foreach($cases as $name=>[$html,$expected]){
 $items=$method->invoke($scanner,$html,'');$names=array_column($items,'name');
 $ok=$expected===null?!in_array('Meta Pixel',$names,true):in_array($expected,$names,true);
 echo($ok?'[OK]   ':'[FAIL] '),$name,' => ',implode(',',$names),PHP_EOL;if(!$ok)$fail++;
}
exit($fail?1:0);
