<?php

require_once __DIR__ . '/../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../libs/global/BillingStatsWorkers.php';
/*
 * Tool
 */

print_r("starting tool...\n");

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

if(is_null($from)) {
	die("field 'from' is empty\n");
}

if(is_null($to)) {
	die("field 'to' is empty\n");
}

print_r("using from=".$fromStr."\n");
print_r("using to=".$toStr."\n");

$firstId = NULL;

if(isset($_GET["-firstId"])) {
	$firstId = $_GET["-firstId"];
}

print_r("using firstId=".$firstId."\n");

$offset = 0;

if(isset($_GET["-offset"])) {
	$offset = $_GET["-offset"];
}

print_r("using offset=".$offset."\n");

$limit = 100;

if(isset($_GET["-limit"])) {
	$limit = $_GET["-limit"];
}

print_r("using limit=".$limit."\n");

print_r("processing...\n");

$platforms = BillingPlatformDAO::getPlatforms();

foreach ($platforms as $platform) {
	$billingStatsWorkers = new BillingStatsWorkers($platform);
	$billingStatsWorkers->doGenerateStats($from, $to);
}

print_r("processing done");

?>