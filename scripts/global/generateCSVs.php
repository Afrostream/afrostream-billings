<?php

require_once __DIR__ . '/../libs/global/BillingCSVsWorkers.php';
/*
 * Tool
 */

print_r("starting tool to generate csvs...\n");

print_r("processing...\n");

$billingCSVsWorkers = new BillingCSVsWorkers();
$billingCSVsWorkers->doGenerateCSVs();

print_r("processing done\n");

?>