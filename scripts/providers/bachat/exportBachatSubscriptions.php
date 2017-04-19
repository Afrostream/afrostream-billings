<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/providers/bachat/BillingsExportBachatSubscriptionsWorkers.php';

/*
 * Tool
 */

print_r("starting tool to export bachat subscriptions...\n");

foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_GET[$e[0]]=$e[1];
    else
        $_GET[$e[0]]=0;
}

$provider = NULL;
$providerUuid = NULL;

if(isset($_GET["-providerUuid"])) {
	$providerUuid = $_GET["-providerUuid"];
	$provider = ProviderDAO::getProviderByUuid($providerUuid);
} else {
	$msg = "-providerUuid field is missing";
	die($msg);
}

if($provider == NULL) {
	$msg = "provider with uuid=".$providerUuid." not found";
	die($msg);
}

if($provider->getName() != 'bachat') {
	$msg = "provider with uuid=".$providerUuid." is not connected to bachat";
	die($msg);
}

print_r("processing...\n");

$billingsExportBachatSubscriptionsWorkers = new BillingsExportBachatSubscriptionsWorkers($provider);
$billingsExportBachatSubscriptionsWorkers->doExportSubscriptions();

print_r("processing done\n");

?>