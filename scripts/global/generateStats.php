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

$numberOfSubscriptions = dbStats::getNumberOfSubscriptions();

print_r("total=".$numberOfSubscriptions['total']."\n");

if($numberOfSubscriptions['total']) {

	$numberOfSubscriptionsByProvider = $numberOfSubscriptions['providers'];
	
	foreach ($numberOfSubscriptionsByProvider as $provider_name => $counters) {
		print_r("details : ".$provider_name."=".$counters['total']."\n");
	}

}

$minusOneDay = new DateInterval("P1D");
$minusOneDay->invert = 1;

$yesterday = new DateTime();
$yesterday->setTimezone(new DateTimeZone(config::$timezone));
$yesterday->add($minusOneDay);

$numberOfActivatedSubscriptions = dbStats::getNumberOfActivatedSubscriptions($yesterday);

print_r("activated=".$numberOfActivatedSubscriptions['total']."\n");

if($numberOfActivatedSubscriptions['total'] > 0) {

	$numberOfActivatedSubscriptionsByProvider = $numberOfActivatedSubscriptions['providers'];

	foreach ($numberOfActivatedSubscriptionsByProvider as $provider_name => $counters) {
		print_r("details : ".$provider_name."=".$counters['total']."\n");
	}
}

$numberOfExpiredSubscriptions = dbStats::getNumberOfExpiredSubscriptions($yesterday);

print_r("expired=".$numberOfExpiredSubscriptions['total']."\n");

if($numberOfExpiredSubscriptions['total'] > 0) {
	print_r("expired_cause_pb=".$numberOfExpiredSubscriptions['expired_cause_pb']."\n");
	print_r("expired_cause_ended=".$numberOfExpiredSubscriptions['expired_cause_ended']."\n");
}

$numberOfCanceledSubscriptions = dbStats::getNumberOfCanceledSubscriptions($yesterday);

print_r("canceled=".$numberOfCanceledSubscriptions['total']."\n");

if($numberOfCanceledSubscriptions['total'] > 0) {

	$numberOfCanceledSubscriptionsByProvider = $numberOfCanceledSubscriptions['providers'];

	foreach ($numberOfCanceledSubscriptionsByProvider as $provider_name => $counters) {
		print_r("details : ".$provider_name."=".$counters['total']."\n");
	}
}

/*$slackHandler = new SlackHandler();

$slackHandler->sendMessage('test-channel', 'ceci est encore un test');*/

print_r("processing done\n");

?>