<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/partners/logista/BillingLogistaProcessStocksReportWorkers.php';

/*
 * Tool
 */

print_r("starting tool to process logista stocks reports...\n");

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
	$billingLogistaProcessStocksReportWorkers = new BillingLogistaProcessStocksReportWorkers($partner);
	$billingLogistaProcessStocksReportWorkers->doProcessStocksReports();
}

print_r("processing done\n");

?>