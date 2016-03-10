<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db/dbGlobal.php';
require_once __DIR__ . '/libs/BillingsUpdateUsers.php';
/*
 * Tool to update users
 */

print_r("starting update users tool\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

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

$billingsUpdateUsers = new BillingsUpdateUsers();
$billingsUpdateUsers->doUpdateUsers($firstId, $limit, $offset);

print_r("processing done");
	
?>