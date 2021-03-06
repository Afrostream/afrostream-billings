<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/partners/chartmogul/BillingsChartmogulWorkers.php';

/*
 * Tool : Only for AFROSTREAM Platform
 */

$platform = BillingPlatformDAO::getPlatformById(1);

print_r("starting tool to sync chartmogul customers for platform named : ".$platform->getName()."..\n");

foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_GET[$e[0]]=$e[1];
    else
        $_GET[$e[0]]=0;
}

print_r("processing...\n");

$billingsChartmogulWorkers = new BillingsChartmogulWorkers($platform);
$billingsChartmogulWorkers->doSyncCustomers();

print_r("processing done\n");

?>