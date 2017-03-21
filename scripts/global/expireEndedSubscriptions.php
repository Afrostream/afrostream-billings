<?php

require_once __DIR__ . '/../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../libs/global/BillingExpireSubscriptionsWorkers.php';

/*
 * Tool
 */

print_r("starting tool to expire Ended Subscriptions\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

$firstId = NULL;

if(isset($_GET["-firstId"])) {
	$firstId = $_GET["-firstId"];
}

print_r("using firstId=".$firstId."\n");

$offset = 0;

if(isset($_GET["-offset"])) {
	$offset = $_GET["-offset"];
}

print_r("using offset=".$offset."\n");

$limit = 100;

if(isset($_GET["-limit"])) {
	$limit = $_GET["-limit"];
}

print_r("using limit=".$limit."\n");

print_r("processing...\n");

$platforms = BillingPlatformDAO::getPlatforms();

foreach ($platforms as $platform) {
	$billingExpireSubscriptionsWorkers = new BillingExpireSubscriptionsWorkers($platform);
	$billingExpireSubscriptionsWorkers->doExpireEndedSubscriptions();
}

print_r("processing done");
	
?>