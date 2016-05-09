<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/db/dbStats.php';
require_once __DIR__ . '/../../libs/slack/SlackHandler.php';

print_r("starting tool to generate Stats...\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}


print_r("processing...\n");

$now = new DateTime();

$minutes = $now->format('i');

$end_date = clone $now;

if($minutes > 0) {
	$dateInterval = new DateInterval("PT".$minutes."M");
	$dateInterval->invert = 1;
	$end_date = $end_date->add($dateInterval);
}

$start_date = clone $end_date;

$dateInterval = new DateInterval("PT1H");
$dateInterval->invert = 1;

$start_date = $start_date->add($dateInterval);

$subscriptions = dbStats::getActivatedSubscriptions($start_date, $end_date);

sendMessage("**************************************************");

sendMessage(count($subscriptions)." new subscribers between ".$start_date->format('H')."H and ".$end_date->format('H')."H : ");

foreach ($subscriptions as $subscription) {
	sendMessage($subscription['email']." ".$subscription['internal_plan_name']." (".$subscription['provider_name'].')');
}

print_r("processing done\n");

function sendMessage($msg) {
	print_r($msg.PHP_EOL);
	if(getEnv('SLACK_ACTIVATED') == 1) {
		$slackHandler = new SlackHandler();
		$slackHandler->sendMessage(getEnv('SLACK_STATS_CHANNEL'), $msg);
	}
}


?>