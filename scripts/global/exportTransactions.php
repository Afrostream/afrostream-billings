<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../libs/global/BillingExportTransactionsWorkers.php';
/*
 * Tool
 */

print_r("starting tool to export transactions...\n");

foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_GET[$e[0]]=$e[1];
    else
        $_GET[$e[0]]=0;
}

print_r("processing...\n");

$billingExportTransactionsWorkers = new BillingExportTransactionsWorkers();
$billingExportTransactionsWorkers->doExportTransactions();

print_r("processing done\n");

?>