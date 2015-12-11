<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../config/config.php';
//require_once __DIR__ . '/../../../../../libs/providers/gocardless/webhooks/WebHooksHandler.php';

config::getLogger()->addInfo('Receiving gocardless webhook...');

$gocardless_secret = getEnv('GOCARDLESS_WH_SECRET');

$validated = false;

$calculated_signature = openssl_encrypt($_POST, "sha256", $gocardless_secret);

if($calculated_signature == $_SERVER["webhook-signature"]) {
	$validated = true;
}

if (!$validated) {
	header('HTTP/1.0 498 Token Invalid');
	die ("Not authorized");
} else {
	config::getLogger()->addInfo('Token valid');
}

//$webHooksHander = new WebHooksHander();

$post_data = file_get_contents('php://input');

config::getLogger()->addInfo('Saving gocardless webhook...');

//$billingGocardlessWebHook = $webHooksHander->doSaveWebHook($post_data);

config::getLogger()->addInfo('Saving gocardless webhook done successfully');

config::getLogger()->addInfo('Processing gocardless webhook...');

//--> For tests purpose Only
//$billingGocardlessWebHook->setId(106);
//<-- For tests purpose Only

//$webHooksHander->doProcessWebHook($billingGocardlessWebHook->getId());

config::getLogger()->addInfo('Processing gocardless webhook done successfully');

config::getLogger()->addInfo('Receiving gocardless webhook done successfully');

?>