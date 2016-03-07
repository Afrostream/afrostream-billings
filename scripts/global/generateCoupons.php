<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../libs/global/BillingsCouponsGenerator.php';

print_r("starting tool ...\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

$couponcampaignuuid = false;

if(isset($_GET["-couponcampaignuuid"])) {
	$couponcampaignuuid = $_GET["-couponcampaignuuid"];
} else {
	print_r("-couponcampaignuuid is missing\n");
	exit;
}

print_r("using couponcampaignuuid=".var_export($couponcampaignuuid, true)."\n");

print_r("processing...\n");

$billingsCouponsGenerator = new BillingsCouponsGenerator();

$billingsCouponsGenerator->doGenerateCoupons($couponcampaignuuid);

print_r("processing done\n");

?>