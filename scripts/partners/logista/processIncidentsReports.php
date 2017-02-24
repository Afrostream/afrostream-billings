<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../libs/partners/logista/BillingLogistaProcessIncidentsReportWorkers.php';

/*
 * Tool
 */

print_r("starting tool to process logista incidents reports...\n");

foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_GET[$e[0]]=$e[1];
    else
        $_GET[$e[0]]=0;
}

print_r("processing...\n");

$billingLogistaProcessIncidentsReportWorkers = new BillingLogistaProcessIncidentsReportWorkers();
$billingLogistaProcessIncidentsReportWorkers->doProcessIncidentsReports();

print_r("processing done\n");

?>