<?php

require_once __DIR__ . '/../libs/global/BillingCSVsWorkers.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';
/*
 * Tool
 */

print_r("starting tool to generate csvs...\n");

print_r("processing...\n");

$billingCSVsWorkers = new BillingCSVsWorkers(BillingPlatformDAO::getPlatformById(1));
$billingCSVsWorkers->doGenerateCSVs();

print_r("processing done\n");

?>