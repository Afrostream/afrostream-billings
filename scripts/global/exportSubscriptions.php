<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../libs/providers/gocardless/BillingsExportGocardlessSubscriptionsWorkers.php';
require_once __DIR__ . '/../libs/providers/bachat/BillingsExportBachatSubscriptionsWorkers.php';
require_once __DIR__ . '/../libs/providers/afr/BillingsExportAfrSubscriptionsWorkers.php';

/*
 * Tool : Only for AFROSTREAM Platform
 */

$platform = BillingPlatformDAO::getPlatformById(1);

print_r("starting tool to export subscriptions for platform named : ".$platform->getName()."...\n");

foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_GET[$e[0]]=$e[1];
    else
        $_GET[$e[0]]=0;
}

print_r("processing gocardless subscriptions export...\n");

$billingsExportGocardlessSubscriptionsWorkers = new BillingsExportGocardlessSubscriptionsWorkers(ProviderDAO::getProviderByName('gocardless', $platform->getId()));
$billingsExportGocardlessSubscriptionsWorkers->doExportSubscriptions();

print_r("processing gocardless subscriptions export done\n");

print_r("processing bachat subscriptions export...\n");

$billingsExportBachatSubscriptionsWorkers = new BillingsExportBachatSubscriptionsWorkers(ProviderDAO::getProviderByName('bachat', $platform->getId()));
$billingsExportBachatSubscriptionsWorkers->doExportSubscriptions();

print_r("processing bachat subscriptions export done\n");

print_r("processing afr subscriptions export...\n");

$billingsExportAfrSubscriptionsWorkers = new BillingsExportAfrSubscriptionsWorkers(ProviderDAO::getProviderByName('afr', $platform->getId()));
$billingsExportAfrSubscriptionsWorkers->doExportSubscriptions();

print_r("processing afr subscriptions export done\n");

print_r("processing done\n");

?>