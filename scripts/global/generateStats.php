<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/db/dbStats.php';
require_once __DIR__ . '/../../libs/slack/SlackHandler.php';

print_r("starting tool ...\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}


print_r("processing...\n");

sendMessage("*** TOTAL ***");

$numberOfSubscriptions = dbStats::getNumberOfSubscriptions();

sendMessage("total=".$numberOfSubscriptions['total']);

if($numberOfSubscriptions['total']) {

	$numberOfSubscriptionsByProvider = $numberOfSubscriptions['providers'];
	
	foreach ($numberOfSubscriptionsByProvider as $provider_name => $counters) {
		sendMessage("details : ".$provider_name."=".$counters['total']);
	}
}

$minusOneDay = new DateInterval("P1D");
$minusOneDay->invert = 1;

$yesterday = new DateTime();
$yesterday->setTimezone(new DateTimeZone(config::$timezone));
$yesterday->add($minusOneDay);

sendMessage("*** ACTIVATED YESTERDAY ***");

$numberOfActivatedSubscriptions = dbStats::getNumberOfActivatedSubscriptions($yesterday);

sendMessage("activated=".$numberOfActivatedSubscriptions['total']);

if($numberOfActivatedSubscriptions['total'] > 0) {
	sendMessage("details :");
	$numberOfActivatedSubscriptionsByProvider = $numberOfActivatedSubscriptions['providers'];

	foreach ($numberOfActivatedSubscriptionsByProvider as $provider_name => $counters) {
		sendMessage($provider_name."=".$counters['total']);
	}
}

sendMessage("*** EXPIRED YESTERDAY ***");

$numberOfExpiredSubscriptions = dbStats::getNumberOfExpiredSubscriptions($yesterday);

sendMessage("expired=".$numberOfExpiredSubscriptions['total']);

if($numberOfExpiredSubscriptions['total'] > 0) {
	sendMessage("details :");
	sendMessage("expired_cause_pb=".$numberOfExpiredSubscriptions['expired_cause_pb']);
	sendMessage("expired_cause_ended=".$numberOfExpiredSubscriptions['expired_cause_ended']);
}

sendMessage("*** CANCELED (human action) YESTERDAY ***");

$numberOfCanceledSubscriptions = dbStats::getNumberOfCanceledSubscriptions($yesterday);

sendMessage("canceled=".$numberOfCanceledSubscriptions['total']);

if($numberOfCanceledSubscriptions['total'] > 0) {
	sendMessage("details :");
	$numberOfCanceledSubscriptionsByProvider = $numberOfCanceledSubscriptions['providers'];

	foreach ($numberOfCanceledSubscriptionsByProvider as $provider_name => $counters) {
		sendMessage($provider_name."=".$counters['total']);
	}
}

print_r("processing done\n");

function sendMessage($msg) {
	print_r($msg.PHP_EOL);
	if(getEnv('SLACK_STATS_ACTIVATED') == 1) {
		$slackHandler = new SlackHandler();
		$slackHandler->sendMessage(getEnv('SLACK_STATS_CHANNEL'), $msg);
	}
}


?>