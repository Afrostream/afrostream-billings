<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/../libs/db/dbGlobal.php';

require_once __DIR__ . '/libs/global/BillingUsersInternalPlanChangeHandler.php';

$platform = BillingPlatformDAO::getPlatformById(1);

print_r("starting tool to notify plan changes for platform named : ".$platform->getName()."...\n");

$internalPlanUuidsToNotify = [
	'afrostreamambassadeurs' => 'afrostreammonthly-swl-rts',
	'afrostreamannually-ambassadors-active' => 'afrostreammonthly-swl-rts',
	'afrostreamambassadeursrts' => 'afrostreammonthly-swl-rts',
	'afrostreamannually-ambassadors-expired' => 'afrostreammonthly-swl-rts',
	'afrnooneyear' => 'afrostreammonthly-swl-rts',
	'afrnooneyearrts2' => 'afrostreammonthly-swl-rts',
	'afrnooneyearrts' => 'afrostreammonthly-swl-rts'
];

$billingUsersInternalPlanChangeHandler = new BillingUsersInternalPlanChangeHandler($platform);

print_r("processing...\n");

foreach($internalPlanUuidsToNotify as $fromInternalPlanUuid => $toInternalPlanUuid) {
	$billingUsersInternalPlanChangeHandler->notifyUsersPlanChange($fromInternalPlanUuid, $toInternalPlanUuid);
}

print_r("processing done\n");

?>