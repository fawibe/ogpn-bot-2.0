<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Ogpn\Bot\Engine\{FetchResult,Http,RequestSpec,Scanner};

final class FakeHttp extends Http
{
    public function fetchBatch(array $requestsByDomain): array
    {
        $out=[];
        foreach($requestsByDomain as $domain=>$requests){
            foreach($requests as $key=>$spec){
                $url=$spec->url;
                if(str_ends_with($url,'/robots.txt')){
                    $out[$domain][$key]=FetchResult::success($url,404,'','HTTP/2 404');
                } elseif($key==='html') {
                    $final='https://www.'.$domain.'/fr';
                    $html='<html lang="fr"><head><title>Billets de train</title></head><body>Transport ferroviaire, billets de train et horaires en gare.</body></html>';
                    $out[$domain][$key]=FetchResult::success($final,200,$html,'HTTP/2 200',false,'203.0.113.10',"Let's Encrypt",'2')->withRedirectChain([['url'=>$final,'status'=>301]]);
                } elseif(str_contains($key,'html_lang_')) {
                    $out[$domain][$key]=FetchResult::success($url,404,'','HTTP/2 404');
                } else {
                    $out[$domain][$key]=FetchResult::success($url,404,'','HTTP/2 404');
                }
            }
        }
        return $out;
    }
}

$scanner=new Scanner(new FakeHttp());
$r=$scanner->scanBatch(['railtest.be'])['railtest.be'];
$checks=[
 'final status'=>$r->httpStatus===200,
 'final url'=>$r->analysisUrl==='https://www.railtest.be/fr',
 'redirect count'=>$r->redirectCount===1,
 'redirect status'=>$r->redirectStatus===301,
 'tier1'=>$r->category==='transport',
 'tier2'=>$r->categoryTier2==='rail',
];
$f=0;foreach($checks as $n=>$ok){echo($ok?'[OK]   ':'[FAIL] '),$n,PHP_EOL;if(!$ok)$f++;}exit($f?1:0);
