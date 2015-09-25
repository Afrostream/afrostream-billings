<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';

$userid = NULL;
if(isset($_GET['userid'])) {
	$userid = $_GET['userid'];
} else {
	echo 'userid is missing';
}

$user = UserDAO::getUserById($userid);
if($user == NULL) {
	//todo
}

$subscriptionsHandler = new SubscriptionsHandler();
$subscriptionsHandler->doUpdateUserSubscriptions($user);


?>