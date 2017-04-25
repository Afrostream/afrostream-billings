<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/../libs/db/dbGlobal.php';

require_once __DIR__ . '/libs/global/BillingUsersInternalPlanChangeHandler.php';

$platform = BillingPlatformDAO::getPlatformById(1);

print_r("starting tool to process plan changes for platform named : ".$platform->getName()."...\n");

$internalPlanUuidsToProcess = [
	'afrostreamambassadeurs',
	'afrostreamannually-ambassadors-active',
	'afrostreamambassadeursrts',
	'afrostreamannually-ambassadors-expired',
	'afrnooneyear',
	'afrnooneyearrts2',
	'afrnooneyearrts'
];

$billingUsersInternalPlanChangeHandler = new BillingUsersInternalPlanChangeHandler($platform);

print_r("processing...\n");

foreach($internalPlanUuidsToProcess as $fromInternalPlanUuid) {
	$billingUsersInternalPlanChangeHandler->doUsersPlanChange($fromInternalPlanUuid);
}

print_r("processing done\n");

?>
