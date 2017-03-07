<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../libs/partners/chartmogul/BillingsChartmogulWorkers.php';

/*
 * Tool
 */

print_r("starting tool to sync and merge chartmogul customers..\n");

foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_GET[$e[0]]=$e[1];
    else
        $_GET[$e[0]]=0;
}

print_r("processing...\n");

$billingsChartmogulWorkers = new BillingsChartmogulWorkers(1);
try {
	$billingsChartmogulWorkers->doSyncCustomers();
} catch(Exception $e) {
	print_r("a high level exception occurred while syncing chartmogul customers, message=".$e->getMessage()."\n");	
}

try {
	$billingsChartmogulWorkers->doMergeCustomers();
} catch(Exception $e) {
	print_r("a high level exception occurred while merging chartmogul customers, message=".$e->getMessage()."\n");
}

print_r("processing done\n");

?>