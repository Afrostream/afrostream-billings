<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';

require_once __DIR__ . '/../libs/global/BillingUsersInternalPlanChangeHandler.php';

$platform = BillingPlatformDAO::getPlatformById(1);

print_r("starting tool to notify plan change for platform named : ".$platform->getName()."...\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

$fromInternalPlanUuid = NULL;
$toInternalPlanUuid = NULL;

if(isset($_GET["-fromInternalPlanUuid"])) {
	$fromInternalPlanUuid = $_GET["-fromInternalPlanUuid"];
} else {
	die("field 'fromInternalPlanUuid' is missing\n");
}

if(isset($_GET["-toInternalPlanUuid"])) {
	$toInternalPlanUuid = $_GET["-toInternalPlanUuid"];
} else {
	die("field 'toInternalPlanUuid' is missing\n");
}

print_r("processing...\n");

$billingUsersInternalPlanChangeHandler = new BillingUsersInternalPlanChangeHandler($platform);
$billingUsersInternalPlanChangeHandler->notifyUsersPlanChange($fromInternalPlanUuid, $toInternalPlanUuid);

print_r("processing done\n");

?>