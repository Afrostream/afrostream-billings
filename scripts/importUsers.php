<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db/dbGlobal.php';
require_once __DIR__ . '/libs/BillingsImportUsers.php';

/*
 * Tool to import users from Afrostream DB
 */

print_r("starting import users tool from Afrostream DB to Billings DB\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

$possible_providers = array('all', 'recurly');

$current_providers = NULL;

$providerName = 'all';

if(isset($_GET["-providerName"])) {
	$providerName = $_GET["-providerName"];
	if(!in_array($providerName, $possible_providers)) {
		$msg = "-providerName must be one of follows : ".implode(', ', $possible_providers)."\n";
		die($msg);
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

$recurly_todo_count = 0;
$recurly_done_count = 0;
$recurly_error_count = 0;

$last_id = NULL;

try {
	$billingsImportUsers = new BillingsImportUsers();
	while(count($afrUsers = AfrUserDAO::getAfrUsers($firstId, $limit, $offset)) > 0) {
		print_r("processing...current offset=".$offset." \n");
		$offset = $offset + $limit;
		//
		foreach ($afrUsers as $afrUser) {
			$last_id = $afrUser->getId();
			$accountCode = $afrUser->getAccountCode();
			switch($afrUser->getBillingProvider()) {
				case 'recurly' :
					if(in_array('recurly', $current_providers)) {
						if(isset($accountCode)) {
							try {
								$recurly_todo_count++;
								$billingsImportUsers->doImportRecurlyUser($afrUser);
								$recurly_done_count++;
							} catch(Exception $e) {
								$recurly_error_count++;
								ScriptsConfig::getLogger()->addError("an error occurred while importing a recurly user, message=".$e->getMessage());
							}
						}
					}
					break;
				case '' :
					//same as recurly
					if(in_array('recurly', $current_providers)) {
						if(isset($accountCode)) {
							try {
								$recurly_todo_count++;
								$billingsImportUsers->doImportRecurlyUser($afrUser);
								$recurly_done_count++;
							} catch(Exception $e) {
								$recurly_error_count++;
								ScriptsConfig::getLogger()->addError("an error occurred while importing a recurly user, message=".$e->getMessage());
							}
						}
					}
					break;
				default :
					ScriptsConfig::getLogger()->addError("unknown BillingProvider : ".$afrUser->getBillingProvider()." for user which id=".$afrUser->getId());
					break;
			}
		}
	}
} catch(Exception $e) {
	ScriptsConfig::getLogger()->addError("unexpected exception, message=".$e->getMessage());
}

print_r("processing done	:	last_id=".$last_id.", last offset=".$offset." \n"
		."recurly	:	(".$recurly_done_count."/".$recurly_todo_count.")	(".$recurly_error_count." errors) \n");

?>