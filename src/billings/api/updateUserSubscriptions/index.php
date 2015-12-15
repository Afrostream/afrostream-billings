<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';

//TODO : REQUEST

//$userid = 16;
$user_reference_uuid = '1234';

try {
	$subscriptionsHandler = new SubscriptionsHandler();
	if(isset($user_reference_uuid)) {
		$subscriptions = $subscriptionsHandler->doUpdateUserSubscriptionsByUserReferenceUuid($user_reference_uuid);
	} else if(isset($userid)) {
		$subscriptions = $subscriptionsHandler->doUpdateUserSubscriptionsByUserId($userid);
	} else {
		$msg = "userid or user_reference_uuid ar missing";
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
	}

} catch(BillingsException $e) {
	$msg = "an exception occurred while updating an user subscription, error_type=".$e->getExceptionType().",error_code=".$e->getCode().", error_message=".$e->getMessage();
	config::getLogger()->addError($msg);
	//
	echo $msg;
	//
} catch(Exception $e) {
	$msg = "an unknown exception occurred while updating an user subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
	config::getLogger()->addError($msg);
	//
	echo $msg;
	//
}

//TODO : RESPONSE

?>