<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../config/config.php';
require_once __DIR__ . '/../../../../../libs/webhooks/WebHooksHandler.php';

//TODO : REQUEST

config::getLogger()->addInfo('Receiving gocardless webhook...');

$gocardless_secret = getEnv('GOCARDLESS_WH_SECRET');

$validated = false;

$post_data = file_get_contents('php://input');

$calculated_signature = hash_hmac('sha256', $post_data, $gocardless_secret);

if($calculated_signature === $_SERVER['HTTP_WEBHOOK_SIGNATURE']) {
	$validated = true;
}

if (!$validated) {
	config::getLogger()->addError('Receiving gocardless webhook failed, Invalid valid');
	header('HTTP/1.0 498 Invalid token');
	die ("Not authorized");
}

try {
	config::getLogger()->addInfo('Treating gocardless webhook...');
	
	$webHooksHander = new WebHooksHander();
	
	config::getLogger()->addInfo('Saving gocardless webhook...');
	$billingsWebHook = $webHooksHander->doSaveWebHook('gocardless', $post_data);
	config::getLogger()->addInfo('Saving gocardless webhook done successfully');
	
	config::getLogger()->addInfo('Processing gocardless webhook, id='.$billingsWebHook->getId().'...');
	$webHooksHander->doProcessWebHook($billingsWebHook->getId());
	config::getLogger()->addInfo('Processing gocardless webhook done sucessfully, id='.$billingsWebHook->getId().'...');
	
	config::getLogger()->addInfo('Treating gocardless webhook done successfully, id='.$billingsWebHook->getId().'...');
} catch(BillingsException $e) {
	$msg = "an exception occurred while treating a gocardless webhook, error_type=".$e->getExceptionType().",error_code=".$e->getCode().", error_message=".$e->getMessage();
	config::getLogger()->addError($msg);
} catch(Exception $e) {
	$msg = "an unknown exception occurred while treating a gocardless webhook, error_code=".$e->getCode().", error_message=".$e->getMessage();
	config::getLogger()->addError($msg);
}

config::getLogger()->addInfo('Receiving gocardless webhook done successfully');

//TODO : RESPONSE

?>