<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db/dbGlobal.php';
require_once __DIR__ . '/libs/BillingsUpdateSubscriptions.php';
/*
 * Tool to update subscriptions
 */

print_r("starting update subscriptions tool\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

$possible_providers = array('all', 'celery', 'recurly', 'gocardless');

$current_providers = NULL;

$providerName = 'all';

if(isset($_GET["-providerName"])) {
	$providerName = $_GET["-providerName"];
	if(!in_array($providerName, $possible_providers)) {
		$msg = "-providerName must be one of follows : ";
		$firstLoop = true;
		foreach ($possible_providers as $val) {
			if($firstLoop) {
				$firstLoop = false;
				$msg.= $val;
			}
			else {
				$msg.= ", ".$val;
			}
		}
		die($msg."\n");
	}
}

if($providerName == 'all') {
	$current_providers = $possible_providers;
} else {
	$current_providers = array();
	$current_providers[] = $providerName;
}

print_r("using providerName=".$providerName."\n");

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

$todo_count = 0;
$done_count = 0;
$error_count = 0;

$last_id = NULL;

try {
	$billingsUpdateSubscriptions = new BillingsUpdateSubscriptions();
	while(count($billingsUsers = UserDAO::getUsers($firstId, $limit, $offset)) > 0) {
		print_r("processing...current offset=".$offset." \n");
		$offset = $offset + $limit;
		//
		foreach ($billingsUsers as $billingsUser) {
			$last_id = $billingsUser->getId();
			try {
				$todo_count++;
				$billingsUpdateSubscriptions->doUpdateSubscriptions($billingsUser);
				$done_count++;
			} catch(Exception $e) {
				$error_count++;
				ScriptsConfig::getLogger()->addError("an error occurred while updating subscriptions, message=".$e->getMessage());
			}
		}
	}
} catch(Exception $e) {
	ScriptsConfig::getLogger()->addError("unexpected exception, message=".$e->getMessage());
}
	
print_r("processing done	:	last_id=".$last_id.", last offset=".$offset." \n"
		."status	:	(".$done_count."/".$todo_count.")	(".$error_count." errors) \n");
	
?>