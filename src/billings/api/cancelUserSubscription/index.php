<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';

//TODO : REQUEST

$subscriptionid = 1234;

try {
	$subscriptionsHandler = new SubscriptionsHandler();
	$subscription = $subscriptionsHandler->doCancelUserSubscription($subscriptionid);
} catch(Exception $e) {
	$msg = "an unknown exception occurred while cancelling an user subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
	config::getLogger()->addError($msg);
}

//TODO : RESPONSE

?>