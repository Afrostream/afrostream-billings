<?php

require_once __DIR__ . '/../../config/config.php';
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

$intervalInMinutes = $minutes + 1;
$dateInterval = new DateInterval("PT".$intervalInMinutes."M");
$dateInterval->invert = 1;
$end_date = $end_date->add($dateInterval);


$start_date = clone $end_date;

$dateInterval = new DateInterval("PT59M");
$dateInterval->invert = 1;

$start_date = $start_date->add($dateInterval);
//
$start_date->setTime($start_date->format('H'), 0, 0);
$end_date->setTime($end_date->format('H'), 59, 59);
//
//subscriptions
$channelSubscriptions = getEnv('SLACK_STATS_CHANNEL');
//activated subscriptions
sendMessage("**************************************************", $channelSubscriptions);
$subscriptions = dbStats::getActivatedSubscriptions($start_date, $end_date);
sendMessage(count($subscriptions)." new subscriptions between ".$start_date->format('H')."h".$start_date->format('i')." and ".$end_date->format('H')."h".$end_date->format('i')." : ", $channelSubscriptions);

foreach ($subscriptions as $subscription) {
	sendMessage($subscription['email']." ".$subscription['internal_plan_name']." (".$subscription['provider_name'].')', $channelSubscriptions);
}

//future subscriptions
sendMessage("**************************************************", $channelSubscriptions);
$futureSubscriptions = dbStats::getFutureSubscriptions($start_date, $end_date);
sendMessage(count($futureSubscriptions)." new subscriptions in future between ".$start_date->format('H')."h".$start_date->format('i')." and ".$end_date->format('H')."h".$end_date->format('i')." : ", $channelSubscriptions);

foreach ($futureSubscriptions as $futureSubscription) {
	sendMessage($futureSubscription['email']." ".$futureSubscription['internal_plan_name']." activation_date=".$futureSubscription['sub_activated_date']." (".$futureSubscription['provider_name'].')', $channelSubscriptions);
}

//coupons
$channelCoupons = getEnv('SLACK_STATS_COUPONS_CHANNEL');
//activated coupons
sendMessage("**************************************************", $channelCoupons);
$couponsActivated = dbStats::getCouponsActivation($start_date, $end_date);
sendMessage(count($couponsActivated)." activated coupons between ".$start_date->format('H')."h".$start_date->format('i')." and ".$end_date->format('H')."h".$end_date->format('i')." : ", $channelCoupons);

foreach ($couponsActivated as $coupon) {
	$msg = sprintf('couponType=%s creator=%s recipient=%s campaignName=%s prefix=%s',
			$coupon['coupon_type'],
			$coupon['user_email'],
			$coupon['recipient_email'],
			$coupon['coupons_campaign_name'],
			$coupon['coupons_campaign_prefix']);
	sendMessage($msg, $channelCoupons);
	sendMessage('---------------------------------------------------', $channelCoupons);
}

//cashway coupons
sendMessage("**************************************************", $channelCoupons);
$couponsActivated = dbStats::getCouponsCashwayGenerated($start_date, $end_date);
sendMessage(count($couponsActivated)." generated cashway coupons between ".$start_date->format('H')."h".$start_date->format('i')." and ".$end_date->format('H')."h".$end_date->format('i')." : ", $channelCoupons);

foreach ($couponsActivated as $coupon) {
	$msg = sprintf('couponType=%s creator=%s recipient=%s campaignName=%s prefix=%s',
			$coupon['coupon_type'],
			$coupon['user_email'],
			$coupon['recipient_email'],
			$coupon['coupons_campaign_name'],
			$coupon['coupons_campaign_prefix']);
	sendMessage($msg, $channelCoupons);
	sendMessage('---------------------------------------------------', $channelCoupons);
}

//afr sponsorship coupons
sendMessage("**************************************************", $channelCoupons);
$couponsActivated = dbStats::getCouponsAfrGenerated($start_date, $end_date, new CouponCampaignType(CouponCampaignType::sponsorship));
sendMessage(count($couponsActivated)." generated sponsorship coupons between ".$start_date->format('H')."h".$start_date->format('i')." and ".$end_date->format('H')."h".$end_date->format('i')." : ", $channelCoupons);

foreach ($couponsActivated as $coupon) {
	$msg = sprintf('couponType=%s creator=%s recipient=%s campaignName=%s prefix=%s', 
			$coupon['coupon_type'],
			$coupon['user_email'],
			$coupon['recipient_email'],
			$coupon['coupons_campaign_name'],
			$coupon['coupons_campaign_prefix']);
	sendMessage($msg, $channelCoupons);
	sendMessage('---------------------------------------------------', $channelCoupons);
}

//afr standard coupons
sendMessage("**************************************************", $channelCoupons);
$couponsActivated = dbStats::getCouponsAfrGenerated($start_date, $end_date, new CouponCampaignType(CouponCampaignType::standard));
sendMessage(count($couponsActivated)." generated standard coupons between ".$start_date->format('H')."h".$start_date->format('i')." and ".$end_date->format('H')."h".$end_date->format('i')." : ", $channelCoupons);

foreach ($couponsActivated as $coupon) {
	$msg = sprintf('couponType=%s creator=%s recipient=%s campaignName=%s prefix=%s',
			$coupon['coupon_type'],
			$coupon['user_email'],
			$coupon['recipient_email'],
			$coupon['coupons_campaign_name'],
			$coupon['coupons_campaign_prefix']);
	sendMessage($msg, $channelCoupons);
	sendMessage('---------------------------------------------------', $channelCoupons);
}

//transactions
$channelTransactions = getEnv('SLACK_STATS_TRANSACTIONS_CHANNEL');
sendMessage("**************************************************", $channelTransactions);
$transactionEvents = dbStats::getTransactions($start_date, $end_date, array('purchase', 'refund'), array('success', 'declined', 'void', 'failed', 'canceled'));
sendMessage(count($transactionEvents)." transactions between ".$start_date->format('H')."h".$start_date->format('i')." and ".$end_date->format('H')."h".$end_date->format('i')." : ", $channelTransactions);

foreach ($transactionEvents as $transactionEvent) {
	$msg = $transactionEvent['provider_name']." ".$transactionEvent['transaction_type']." ".$transactionEvent['transaction_status']."\n";
	$msg.= $transactionEvent['amount']." ".$transactionEvent['currency']."\n";
	$msg.= "transaction_provider_uuid=".$transactionEvent['transaction_provider_uuid']."\n";
	$msg.= "transaction_billing_uuid=".$transactionEvent['transaction_billing_uuid'];
	sendMessage($msg, $channelTransactions);
	sendMessage('---------------------------------------------------', $channelTransactions);
}

//Grafana

$numberOfActiveSubscriptions = dbStats::getNumberOfActiveSubscriptions($now);
$providerIdsToIgnore = array();
$providerNamesToIgnore = ['orange', 'bouygues'];
foreach ($providerNamesToIgnore as $providerNameToIgnore) {
	$provider = ProviderDAO::getProviderByName($providerNameToIgnore);
	if($provider == NULL) {
		$msg = "unknown provider named : ".$providerNameToIgnore;
		ScriptsConfig::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
	}
	$providerIdsToIgnore[] = $provider->getId();
}

$numberOfActiveSubscriptionsExceptMultiscreen = dbStats::getNumberOfActiveSubscriptions($now, $providerIdsToIgnore);

//Active Subscriptions Number
BillingStatsd::gauge('route.providers.all.subscriptions.status.active.counter', $numberOfActiveSubscriptions['total']);
//Active Subscriptions Number Except Temporary Subscriptions
BillingStatsd::gauge('route.providers.allMinusTemporaries.subscriptions.status.active.counter', $numberOfActiveSubscriptionsExceptMultiscreen['total']);
//Active Subscriptions By Provider
$numberOfActiveSubscriptionsByProvider = $numberOfActiveSubscriptions['providers'];
foreach ($numberOfActiveSubscriptionsByProvider as $provider_name => $counters) {
	BillingStatsd::gauge('route.providers.'.$provider_name.'.subscriptions.status.active.counter', $counters['total']);
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