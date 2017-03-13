<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/partners/logista/BillingLogistaProcessDestructionReportWorkers.php';

/*
 * Tool
 */

print_r("starting tool to process logista destruction reports...\n");

foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_GET[$e[0]]=$e[1];
    else
        $_GET[$e[0]]=0;
}

print_r("processing...\n");

$billingLogistaProcessDestructionReportWorkers = new BillingLogistaProcessDestructionReportWorkers(BillingPartnerDAO::getPartnerByName('logista', 1));
$billingLogistaProcessDestructionReportWorkers->doProcessDestructionReports();

print_r("processing done\n");

?>