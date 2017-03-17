<?php

require_once __DIR__ . '/../libs/global/BillingCSVsWorkers.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';

/*
 * Tool : Only for AFROSTREAM Platform
 */

$platform = BillingPlatformDAO::getPlatformById(1);

print_r("starting tool to generate csvs for platform named : ".$platform->getName()."...\n");

print_r("processing...\n");

$billingCSVsWorkers = new BillingCSVsWorkers($platform);
$billingCSVsWorkers->doGenerateCSVs();

print_r("processing done\n");

?>