<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../libs/global/BillingsCouponsGenerator.php';

print_r("starting tool to generate Coupons...\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

$couponscampaignuuid = false;

if(isset($_GET["-couponscampaignuuid"])) {
	$couponscampaignuuid = $_GET["-couponscampaignuuid"];
} else {
	print_r("-couponscampaignuuid is missing\n");
	exit;
}

print_r("using couponscampaignuuid=".var_export($couponscampaignuuid, true)."\n");

print_r("processing...\n");

$billingsCouponsGenerator = new BillingsCouponsGenerator();

$billingsCouponsGenerator->doGenerateCoupons($couponscampaignuuid);

print_r("processing done\n");

?>