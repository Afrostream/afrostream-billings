<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';

//TODO : REQUEST

$userid = 13;
$plan_internal_uuid = 'afrostream_monthly';

$billingInfoOpts = new BillingInfoOpts();

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
} catch(Exception $e) {
	$msg = "an exception occurred while creating an user, error_code=".$e->getCode().", error_message=".$e->getMessage();
	config::getLogger()->addError($msg);
	//
	echo $msg;
	//
}

//TODO : RESPONSE

?>