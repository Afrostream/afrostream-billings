<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../../../../libs/utils/BillingsException.php';

//TODO : REQUEST

$userid = 13;//RECURLY
//$userid = 16;//GOCARDLESS

$plan_internal_uuid = 'afrostream_monthly';

$billingInfoOpts = new BillingInfoOpts();

$billingInfoOpts->setOpt('subscription_uuid', '32eaefbe04664fc59fbf8644dfb98a3c');//RECURLY
///$billingInfoOpts->setOpt('subscription_uuid', 'SB00001WPVRWE4');//GOCARLESS

$billingInfoOpts->setOpt('number', '4111-1111-1111-1111');
$billingInfoOpts->setOpt('month', 12);
$billingInfoOpts->setOpt('year', 2017);
$billingInfoOpts->setOpt('verification_value', 123);
$billingInfoOpts->setOpt('address1', '400 Alabama St');
$billingInfoOpts->setOpt('city', 'San Francisco');
$billingInfoOpts->setOpt('state', 'CA');
$billingInfoOpts->setOpt('country', 'US');
$billingInfoOpts->setOpt('zip', '94110');

/*if(isset($_GET['userid'])) {
	$userid = $_GET['userid'];
} else {
	die('userid is missing');
}

if(isset($_GET['plan_internal_uuid'])) {
	$plan_internal_uuid = $_GET['plan_internal_uuid'];
} else {
	die('plan_internal_uuid is missing');
}*/

try {
	
	$subscriptionsHandler = new SubscriptionsHandler();
	$subscription = $subscriptionsHandler->doCreateUserSubscription($userid, $plan_internal_uuid, $billingInfoOpts);
	
} catch(BillingsException $e) {
	$msg = "an exception occurred while creating an user subscription, error_type=".$e->getExceptionType().",error_code=".$e->getCode().", error_message=".$e->getMessage();
	config::getLogger()->addError($msg);
	//
	echo $msg;
	//
} catch(Exception $e) {
	$msg = "an unknown exception occurred while creating an user subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
	config::getLogger()->addError($msg);
	//
	echo $msg;
	//
}

//TODO : RESPONSE

?>