<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/providers/braintree/BillingsImportBraintreeTransactions.php';

/*
 * Tool : by default for all braintree providers : we need to import all transactions constantly
 */

print_r("starting tool to import braintree transactions...\n");

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
$to = NULL;
$toStr = NULL;

if(isset($_GET["-lastdays"])) {
	$lastdays = $_GET["-lastdays"];
	$minusOneDay = new DateInterval("P1D");
	$minusOneDay->invert = 1;
	$yesterday = new DateTime();
	$yesterday->setTimezone(new DateTimeZone(ScriptsConfig::$timezone));
	$yesterday->setTime(23, 59, 59);
	$yesterday->add($minusOneDay);
	$to = $yesterday;
	$toStr = $to->format($dateFormat);
	$intervalToRemove = new DateInterval("P".$lastdays."D");
	$intervalToRemove->invert = 1;
	$firstDay = new DateTime();
	$firstDay->setTimezone(new DateTimeZone(ScriptsConfig::$timezone));
	$firstDay->setTime(0, 0, 0);
	$firstDay->add($intervalToRemove);
	$from = $firstDay;
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

print_r("using from=".$fromStr."\n");
print_r("using to=".$toStr."\n");

$providers = array();

//Optionally we can specify an uuid of a given provider. 
//For information, a script is already running in PRODUCTION without this option. 

if(isset($_GET["-providerUuid"])) {
	$providerUuid = $_GET["-providerUuid"];
	$provider = ProviderDAO::getProviderByUuid($providerUuid);
	if($provider == NULL) {
		$msg = "provider with uuid=".$providerUuid." not found\n";
		die($msg);
	}
	if($provider->getName() != 'braintree') {
		$msg = "provider with uuid=".$providerUuid." is not connected to braintree\n";
		die($msg);
	}
	$providers[] = $provider;
} else {
	$providers = ProviderDAO::getProvidersByName('braintree');
}

print_r("processing...\n");

foreach ($providers as $provider) {
	$billingsImportBraintreeTransactions = new BillingsImportBraintreeTransactions($provider);
	$billingsImportBraintreeTransactions->doImportTransactions($from, $to);
}

print_r("processing done\n");

?>