<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';

//TODO : REQUEST

$userid = 13;
$plan_internal_uuid = 'afrostream_monthly';

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

$subscriptionsHandler = new SubscriptionsHandler();
$subscription = $subscriptionsHandler->doCreateUserSubscription($userid, $plan_internal_uuid);

//TODO : RESPONSE

?>