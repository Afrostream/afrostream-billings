<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/providers/google/BillingsImportGoogleTransactions.php';

/*
 * Tool
 */

print_r("starting tool to import google transactions...\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

$dateFormat = "Ym";

$from = NULL;
$fromStr = NULL;
$to = NULL;
$toStr = NULL;

if(isset($_GET["-lastmonths"])) {
	$lastMonths = $_GET["-lastmonths"];
	$currentDateTime = new DateTime();
	$currentDateTime->setTimezone(new DateTimeZone(ScriptsConfig::$timezone));
	$to = DateTime::createFromFormat($dateFormat, $currentDateTime->format($dateFormat));
	$toStr = $to->format($dateFormat);
	$intervalToRemove = new DateInterval("P".$lastMonths."M");
	$intervalToRemove->invert = 1;
	$from = clone $to;
	$from = $from->add($intervalToRemove);
	$fromStr = $from->format($dateFormat);
} else {
	if(isset($_GET["-from"])) {
		$fromStr = $_GET["-from"];
		$from = DateTime::createFromFormat($dateFormat, $fromStr);
		$from->setTimezone(new DateTimeZone(ScriptsConfig::$timezone));
	}
	if(isset($_GET["-to"])) {
		$toStr = $_GET["-to"];
		$to = DateTime::createFromFormat($dateFormat, $toStr);
		$to->setTimezone(new DateTimeZone(ScriptsConfig::$timezone));
	}
}

if($from == NULL) {
	$msg = "(-from / -to) OR (-lastmonths) fields are missing\n";
	die($msg);
}

if($to == NULL) {
	$msg = "(-from / -to) OR (-lastmonths) fields are missing\n";
	die($msg);
}

print_r("using from=".$fromStr."\n");
print_r("using to=".$toStr."\n");

$providers = array();

if(isset($_GET["-providerUuid"])) {
	$providerUuid = $_GET["-providerUuid"];
	$provider = ProviderDAO::getProviderByUuid($providerUuid);
	if($provider == NULL) {
		$msg = "provider with uuid=".$providerUuid." not found\n";
		die($msg);
	}
	if($provider->getName() != 'google') {
		$msg = "provider with uuid=".$providerUuid." is not connected to google\n";
		die($msg);
	}
	$providers[] = $provider;
} else {
	$providers = ProviderDAO::getProvidersByName('google');
}

print_r("processing...\n");

foreach ($providers as $provider) {
	$billingsImportGoogleTransactions = new BillingsImportGoogleTransactions($provider);
	$billingsImportGoogleTransactions->doImportTransactions($from, $to);
}

print_r("processing done\n");

?>