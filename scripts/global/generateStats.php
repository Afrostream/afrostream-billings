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

sendMessage("********** SUBSCRIPTIONS **********");

sendMessage("*** TOTAL ***");

$minusOneDay = new DateInterval("P1D");
$minusOneDay->invert = 1;

$yesterday = new DateTime();
$yesterday->setTimezone(new DateTimeZone(config::$timezone));
$yesterday->add($minusOneDay);

$yesterdayBeginningOfDay = clone $yesterday;
$yesterdayBeginningOfDay->setTime(0,0,0);
$yesterdayEndOfDay = clone $yesterday;
$yesterdayEndOfDay->setTime(23,59,59);

$numberOfSubscriptions = dbStats::getNumberOfSubscriptions($yesterdayEndOfDay);
$numberOfActiveSubscriptions = dbStats::getNumberOfActiveSubscriptions($yesterdayEndOfDay);
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
$numberOfActiveSubscriptionsExceptMultiscreen = dbStats::getNumberOfActiveSubscriptions($yesterdayEndOfDay, $providerIdsToIgnore);
$numberOfExpiredSubscriptions = dbStats::getNumberOfExpiredSubscriptions(NULL, $yesterdayEndOfDay);

$numberOfActivatedSubscriptionsYesterday = dbStats::getNumberOfActivatedSubscriptions($yesterday);
$numberOfExpiredSubscriptionsYesterday = dbStats::getNumberOfExpiredSubscriptions($yesterdayBeginningOfDay, $yesterdayEndOfDay);
$numberOfCanceledSubscriptionsYesterday = dbStats::getNumberOfCanceledSubscriptions($yesterday);
$numberOfFutureSubscriptionsYesterday = dbStats::getNumberOfFutureSubscriptions($yesterday);

sendMessage("total since launch=".$numberOfSubscriptions['total']);

sendMessage("total active=".$numberOfActiveSubscriptions['total']);

sendMessage("total active (except multiscreen)=".$numberOfActiveSubscriptionsExceptMultiscreen['total']);

sendMessage("total inactive=".$numberOfExpiredSubscriptions['expired_cause_pb']);

sendMessage("total churn=".$numberOfExpiredSubscriptions['expired_cause_ended']);

if($numberOfActiveSubscriptions['total'] > 0) {
	sendMessage("total active details :");
	$numberOfActiveSubscriptionsByProvider = $numberOfActiveSubscriptions['providers'];
	foreach ($numberOfActiveSubscriptionsByProvider as $provider_name => $counters) {
		sendMessage($provider_name."=".$counters['total']);
	}
}

sendMessage("*** YESTERDAY ***");

sendMessage("*** ACTIVATED ***");

sendMessage("total activated=".$numberOfActivatedSubscriptionsYesterday['total']);

sendMessage("new activated=".$numberOfActivatedSubscriptionsYesterday['new']);
sendMessage("Re activated=".$numberOfActivatedSubscriptionsYesterday['returning']);

if($numberOfActivatedSubscriptionsYesterday['total'] > 0) {
	sendMessage("total activated details :");
	$numberOfActivatedSubscriptionsYesterdayByProvider = $numberOfActivatedSubscriptionsYesterday['providers'];
	foreach ($numberOfActivatedSubscriptionsYesterdayByProvider as $provider_name => $counters) {
		sendMessage($provider_name."=".$counters['total']);
	}
}

sendMessage("*** UPCOMING ***");

sendMessage("total upcoming=".$numberOfFutureSubscriptionsYesterday['total']);

if($numberOfFutureSubscriptionsYesterday['total'] > 0) {
	sendMessage("total upcoming details :");
	$numberOfFutureSubscriptionsYesterdayByProvider = $numberOfFutureSubscriptionsYesterday['providers'];
	foreach ($numberOfFutureSubscriptionsYesterdayByProvider as $provider_name => $counters) {
		sendMessage($provider_name."=".$counters['total']);
	}
}

sendMessage("*** EXPIRED ***");

sendMessage("total expired=".$numberOfExpiredSubscriptionsYesterday['total']);

if($numberOfExpiredSubscriptionsYesterday['total'] > 0) {
	sendMessage("expired reasons :");
	sendMessage("Not active - payement issue=".$numberOfExpiredSubscriptionsYesterday['expired_cause_pb']);
	sendMessage("Churn - no access=".$numberOfExpiredSubscriptionsYesterday['expired_cause_ended']);
	sendMessage("total expired details :");
	$numberOfExpiredSubscriptionsYesterdayByProvider = $numberOfExpiredSubscriptionsYesterday['providers'];
	foreach ($numberOfExpiredSubscriptionsYesterdayByProvider as $provider_name => $counters) {
		sendMessage($provider_name." : total expired=".$counters['total']);
		sendMessage($provider_name." : Not active - payement issue=".$counters['expired_cause_pb']);
		sendMessage($provider_name." : Churn - no access=".$counters['expired_cause_ended']);
	}
}

sendMessage("*** CANCELED (human action) ***");

sendMessage("canceled=".$numberOfCanceledSubscriptionsYesterday['total']);

if($numberOfCanceledSubscriptionsYesterday['total'] > 0) {
	sendMessage("total canceled details :");
	$numberOfCanceledSubscriptionsYesterdayByProvider = $numberOfCanceledSubscriptionsYesterday['providers'];
	foreach ($numberOfCanceledSubscriptionsYesterdayByProvider as $provider_name => $counters) {
		sendMessage($provider_name."=".$counters['total']);
	}
}

sendMessage("********** TRANSACTIONS **********");

$numberOfTransactionEvents = dbStats::getNumberOfTransactions($yesterdayBeginningOfDay, $yesterdayEndOfDay, array('purchase', 'refund'), array('success'));

sendMessage("*** YESTERDAY ***");

if($numberOfTransactionEvents['total'] > 0) {

	sendMessage("*** TOTAL ***");
	$msg = "number of transactions=".$numberOfTransactionEvents['total'];
	$globalCurrencies = $numberOfTransactionEvents['currencies'];
	$first = true;
	foreach ($globalCurrencies as $currency => $amount) {
		if($first) {
			$first = false;
			$msg.= ", amounts :";
		}
		$msg.= " amount=".$amount." ".$currency;
	}
	sendMessage($msg);
	sendMessage("*** BY TRANSACTION_TYPE ***");
	$byTransactionTypes = $numberOfTransactionEvents['transaction_types'] ;
	foreach ($byTransactionTypes as $key => $value) {
		$msg = "transaction_type=".$key." : number of transactions=".$value['total'];
		$first = true;
		$currencies = $value['currencies'];
		foreach ($currencies as $currency => $amount) {
			if($first) {
				$first = false;
				$msg.= ", amounts :";
			}
			$msg.= " amount=".$amount." ".$currency;
		}
		sendMessage($msg);
	}
	sendMessage("*** BY PROVIDER ***");
	$byProviders = $numberOfTransactionEvents['providers'];
	foreach ($byProviders as $key => $value) {
		$msg = "provider=".$key." : \n";
		$transactionTypes = $value['transaction_types'];
		foreach ($transactionTypes as $transaction_type => $transaction_type_values) {
			$msg.= "	transaction_type=".$transaction_type." : number of transactions=".$transaction_type_values['total'];
			$first = true;
			$currencies = $transaction_type_values['currencies'];
			foreach($currencies as $currency => $amount) {
				if($first) {
					$first = false;
					$msg.= ", amounts :";
				}
				$msg.= " amount=".$amount." ".$currency;
			}
			$msg.= "\n";
		}
		sendMessage($msg);
	}
} else {
	sendMessage("total=0");
}

sendMessage("********** COUPONS **********");

sendMessage("*** YESTERDAY ***");

sendMessage("*** GENERATED ***");

$numberOfCouponsGenerated = dbStats::getNumberOfCouponsGenerated($yesterdayBeginningOfDay, $yesterdayEndOfDay);

sendMessage("total generated=".$numberOfCouponsGenerated['total']);

if($numberOfCouponsGenerated['total'] > 0) {
	sendMessage("*** BY COUPON_TYPE ***");
	$numberOfCouponsGeneratedByCouponType = $numberOfCouponsGenerated['coupon_types'];
	foreach ($numberOfCouponsGeneratedByCouponType as $couponType => $details) {
		$msg = "coupon_type=".$couponType." : total=".$details['total'];
		sendMessage($msg);
	}
	sendMessage("*** BY PROVIDER ***");
	$numberOfCouponsGeneratedByProvider = $numberOfCouponsGenerated['providers'];
	foreach ($numberOfCouponsGeneratedByProvider as $provider_name => $details) {
		$msg = "provider=".$provider_name." : \n";
		$couponTypes = $details['coupon_types'];
		foreach ($couponTypes as $couponType => $couponTypeValues) {
			$msg.= "	coupon_type=".$couponType." : total=".$couponTypeValues['total'];
			$msg.= "\n";
		}
		sendMessage($msg);
	}
}

sendMessage("*** ACTIVATED ***");

$numberOfCouponsActivated = dbStats::getNumberOfCouponsActivated($yesterdayBeginningOfDay, $yesterdayEndOfDay);

sendMessage("total activated=".$numberOfCouponsActivated['total']);

if($numberOfCouponsActivated['total'] > 0) {
	sendMessage("*** BY COUPON_TYPE ***");
	$numberOfCouponsActivatedByCouponType = $numberOfCouponsActivated['coupon_types'];
	foreach ($numberOfCouponsActivatedByCouponType as $couponType => $details) {
		$msg = "coupon_type=".$couponType." : total=".$details['total'];
		sendMessage($msg);
	}
	sendMessage("*** BY PROVIDER ***");
	$numberOfCouponsActivatedByProvider = $numberOfCouponsActivated['providers'];
	foreach ($numberOfCouponsActivatedByProvider as $provider_name => $details) {
		$msg = "provider=".$provider_name." : \n";
		$couponTypes = $details['coupon_types'];
		foreach ($couponTypes as $couponType => $couponTypeValues) {
			$msg.= "	coupon_type=".$couponType." : total=".$couponTypeValues['total'];
			$msg.= "\n";
		}
		sendMessage($msg);
	}	
}

print_r("processing done\n");

function sendMessage($msg) {
	print_r($msg.PHP_EOL);
	if(getEnv('SLACK_ACTIVATED') == 1) {
		$slackHandler = new SlackHandler();
		$slackHandler->sendMessage(getEnv('SLACK_GROWTH_CHANNEL'), $msg);
	}
}


?>