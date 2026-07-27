<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';use Ogpn\Bot\{Bootstrap,Auth,Planner};$pdo=Bootstrap::pdo();$w=Auth::worker($pdo,'worker.plan');$plan=Planner::plan($pdo,$w);$pdo->prepare('UPDATE workers SET last_action=? WHERE worker_id=?')->execute([$plan['action'],$w['worker_id']]);Bootstrap::json($plan);
