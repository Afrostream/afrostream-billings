<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../../../../libs/utils/BillingsException.php';

//TODO : REQUEST

//$userid = 1;
$user_reference_uuid = '1234';

/*if(isset($_GET['userid'])) {
	$userid = $_GET['userid'];
} else {
	die('userid is missing');
}*/

try {
	$subscriptionsHandler = new SubscriptionsHandler();
	if(isset($user_reference_uuid)) {
		$subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUserReferenceUuid($user_reference_uuid);
	} else if(isset($userid)) {
		$subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUserId($userid);
	} else {
		$msg = "userid or user_reference_uuid ar missing";
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
	}
	
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