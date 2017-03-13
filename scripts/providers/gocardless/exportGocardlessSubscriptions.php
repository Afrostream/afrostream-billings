<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/providers/gocardless/BillingsExportGocardlessSubscriptionsWorkers.php';

/*
 * Tool
 */

print_r("starting tool to export gocardless subscriptions...\n");

foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_GET[$e[0]]=$e[1];
    else
        $_GET[$e[0]]=0;
}

print_r("processing...\n");

$billingsExportGocardlessSubscriptionsWorkers = new BillingsExportGocardlessSubscriptionsWorkers(ProviderDAO::getProviderByName('gocardless', 1));
$billingsExportGocardlessSubscriptionsWorkers->doExportSubscriptions();

print_r("processing done\n");

?>