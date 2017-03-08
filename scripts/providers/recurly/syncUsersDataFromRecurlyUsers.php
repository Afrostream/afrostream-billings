<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/providers/recurly/BillingsSyncUsersDataFromRecurlyUsers.php';

/*
 * Tool
 */

print_r("starting tool to sync Users Data From Recurly users...\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

print_r("processing...\n");

$billingsSyncUsersDataFromRecurlyUsers = new BillingsSyncUsersDataFromRecurlyUsers(ProviderDAO::getProviderByName2('recurly', 1));

$billingsSyncUsersDataFromRecurlyUsers->doSyncUsersData();

print_r("processing done\n");

?>