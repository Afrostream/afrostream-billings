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
$channelSubscriptions = getEnv('SLACK_STATS_CHANNEL');
sendMessage("**************************************************", $channelSubscriptions);

sendMessage(
	count($subscriptions)." new subscribers between ".$start_date->format('H')."H and ".$end_date->format('H')."H : ",
	$channelSubscriptions
);

foreach ($subscriptions as $subscription) {
	sendMessage(
		$subscription['email']." ".$subscription['internal_plan_name']." (".$subscription['provider_name'].')',
		$channelSubscriptions
	);
}

//activated coupons
$couponsActivated = dbStats::getCouponsActivation($start_date, $end_date);
$channelCoupons = getEnv('SLACK_STATS_COUPONS__CHANNEL');

sendMessage('---------------------------------------------------', $channelCoupons);
sendMessage(
	count($couponsActivated)." activated coupons between ".$start_date->format('H')."H and ".$end_date->format('H')."H : ",
	$channelCoupons
);

foreach ($couponsActivated as $coupon) {
	$msg = sprintf('%s %s (%s)', $coupon['user_email'], $coupon['plan_name'], $coupon['provider_name']);
	sendMessage($msg, $channelCoupons);
}

// cahsway coupons in pending status
$couponsActivated = dbStats::getCouponsCashwayGenerated($start_date, $end_date);

sendMessage('---------------------------------------------------', $channelCoupons);
sendMessage(
	count($couponsActivated)." generated cashway coupons between ".$start_date->format('H')."H and ".$end_date->format('H')."H : ",
	$channelCoupons
);

foreach ($couponsActivated as $coupon) {
	$msg = sprintf('%s %s (%s)', $coupon['user_email'], $coupon['plan_name'], $coupon['provider_name']);
	sendMessage($msg, $channelCoupons);
}


print_r("processing done\n");

function sendMessage($msg, $channel) {
	print_r($msg.PHP_EOL);
	if(getEnv('SLACK_ACTIVATED') == 1) {
		$slackHandler = new SlackHandler();
		$slackHandler->sendMessage($channel, $msg);
	}
}


?>