<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/partners/logista/BillingLogistaProcessSalesReportWorkers.php';

/*
 * Tool
 */

print_r("starting tool to process logista sales reports...\n");

foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_GET[$e[0]]=$e[1];
    else
        $_GET[$e[0]]=0;
}

print_r("processing...\n");

$partners = BillingPartnerDAO::getPartnersByName('logista');

foreach ($partners as $partner) {
	$billingLogistaProcessSalesReportWorkers = new BillingLogistaProcessSalesReportWorkers($partner);
	$billingLogistaProcessSalesReportWorkers->doProcessSalesReports();
}

print_r("processing done\n");

?>