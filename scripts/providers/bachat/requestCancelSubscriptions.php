<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/providers/bachat/BillingsBachatWorkers.php';

print_r("starting bachat tool to request cancelations...\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

$force = true;

if(isset($_GET["-force"])) {
	$force = boolval($_GET["-force"]);
}

print_r("using force=".var_export($force, true)."\n");

print_r("processing...\n");

$billingsBachatWorkers = new BillingsBachatWorkers(ProviderDAO::getProviderByName('bachat', 1));

$billingsBachatWorkers->doRequestCancelSubscriptions($force);

print_r("processing done\n");

?>