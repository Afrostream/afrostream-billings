<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';

//TODO : REQUEST

$userid = NULL;
if(isset($_GET['userid'])) {
	$userid = $_GET['userid'];
} else {
	echo 'userid is missing';
}

$subscriptionsHandler = new SubscriptionsHandler();
$subscriptionsHandler->doUpdateUserSubscriptions($userid);

//TODO : RESPONSE

?>