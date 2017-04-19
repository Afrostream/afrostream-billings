<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/providers/bachat/BillingsBachatWorkers.php';

print_r("starting bachat tool to process renew file...\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

print_r("processing...\n");

$providers = ProviderDAO::getProvidersByName('bachat');

foreach ($providers as $provider) {
	$billingsBachatWorkers = new BillingsBachatWorkers($provider);
	$billingsBachatWorkers->doCheckRenewResultFile();
}

print_r("processing done\n");

?>