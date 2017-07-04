<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/providers/gocardless/BillingsImportGocardlessTransactions.php';

/*
 * Tool
 */

print_r("starting tool to import gocardless transactions...\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

$dateFormat = "Ymd";

$from = NULL;
$fromStr = NULL;

if(isset($_GET["-from"])) {
	$fromStr = $_GET["-from"];
	$from = DateTime::createFromFormat($dateFormat, $fromStr);
	$from->setTimezone(new DateTimeZone(ScriptsConfig::$timezone));
}

print_r("using from=".$fromStr."\n");

$to = NULL;
$toStr = NULL;

if(isset($_GET["-to"])) {
	$toStr = $_GET["-to"];
	$to = DateTime::createFromFormat($dateFormat, $toStr);
	$to->setTimezone(new DateTimeZone(ScriptsConfig::$timezone));
}

print_r("using to=".$toStr."\n");

$provider = NULL;
$providerUuid = NULL;

if(isset($_GET["-providerUuid"])) {
	$providerUuid = $_GET["-providerUuid"];
	$provider = ProviderDAO::getProviderByUuid($providerUuid);
} else {
	$msg = "-providerUuid field is missing\n";
	die($msg);
}

if($provider == NULL) {
	$msg = "provider with uuid=".$providerUuid." not found\n";
	die($msg);
}

if($provider->getName() != 'gocardless') {
	$msg = "provider with uuid=".$providerUuid." is not connected to gocardless\n";
	die($msg);
}

print_r("processing...\n");

$billingsImportGocardlessTransactions = new BillingsImportGocardlessTransactions($provider);
$billingsImportGocardlessTransactions->doImportTransactions($from, $to);

print_r("processing done\n");

?>