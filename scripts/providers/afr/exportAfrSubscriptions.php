<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/providers/afr/BillingsExportAfrSubscriptionsWorkers.php';

/*
 * Tool
 */

print_r("starting tool to export afr subscriptions...\n");

foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_GET[$e[0]]=$e[1];
    else
        $_GET[$e[0]]=0;
}

print_r("processing...\n");

$providers = ProviderDAO::getProvidersByName('afr');

foreach ($providers as $provider) {
	$billingsExportAfrSubscriptionsWorkers = new BillingsExportAfrSubscriptionsWorkers($provider);
	$billingsExportAfrSubscriptionsWorkers->doExportSubscriptions();
}

print_r("processing done\n");

?>