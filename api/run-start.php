<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';use Ogpn\Bot\{Bootstrap,Auth,Planner,RunLogger,Healthcheck};
$pdo=Bootstrap::pdo();$w=Auth::worker($pdo,'worker.plan');$b=json_decode(file_get_contents('php://input'),true)?:[];$run=(string)($b['run_id']??'');if(!preg_match('/^[a-f0-9-]{36}$/',$run))Bootstrap::json(['error'=>'invalid_run_id'],422);
$cap=$w['max_runs_per_day']!==null?(int)$w['max_runs_per_day']:null;
if($cap!==null){
    $count=(int)$pdo->query('SELECT COUNT(*) FROM v2_worker_runs WHERE worker_id='.$pdo->quote($w['worker_id']).' AND started_at>=UTC_DATE()')->fetchColumn();
    if($count>=$cap){
        $pdo->prepare('UPDATE v2_workers SET last_action=? WHERE worker_id=?')->execute(['idle_daily_cap',$w['worker_id']]);
        Bootstrap::json(['action'=>'idle','reason'=>'daily_cap_reached','mode'=>null,'metrics'=>[],'limits'=>[],'worker_id'=>$w['worker_id'],'capacity'=>$w['capacity'],'server_time'=>gmdate('c')]);
    }
}
$plan=Planner::plan($pdo,$w);RunLogger::start($pdo,$run,$w,$plan);$pdo->prepare('UPDATE v2_workers SET last_action=? WHERE worker_id=?')->execute([$plan['action'],$w['worker_id']]);if(in_array($plan['action'],['discovery','scan'],true))Healthcheck::ping($plan['action'],'/start');Bootstrap::json($plan);
