<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../config/config.php';
require_once __DIR__ . '/../../../../../libs/providers/recurly/webhooks/WebHooksHandler.php';

config::getLogger()->addInfo('Receiving recurly webhook...');

$valid_passwords = array (getEnv('RECURLY_WH_HTTP_AUTH_USER') => getEnv('RECURLY_WH_HTTP_AUTH_PWD'));
$valid_users = array_keys($valid_passwords);

$user = NULL;
if(isset($_SERVER['PHP_AUTH_USER'])) {
	$user = $_SERVER['PHP_AUTH_USER'];
}
$pass = NULL;
if(isset($_SERVER['PHP_AUTH_PW'])) {
	$pass = $_SERVER['PHP_AUTH_PW'];
}

$validated = false;
if(isset($user) && isset($pass)) {
	$validated = (in_array($user, $valid_users)) && ($pass == $valid_passwords[$user]);
}

if (!$validated) {
	header('WWW-Authenticate: Basic realm="My Realm"');
	header('HTTP/1.0 401 Unauthorized');
	die ("Not authorized");
}

$webHooksHander = new WebHooksHander();

$post_data = file_get_contents('php://input');

config::getLogger()->addInfo('Saving recurly webhook...');

$billingRecurlyWebHook = $webHooksHander->doSaveWebHook($post_data);

config::getLogger()->addInfo('Saving recurly webhook done successfully');

config::getLogger()->addInfo('Processing recurly webhook...');

//--> For tests purpose Only
//$billingRecurlyWebHook->setId(106);
//<-- For tests purpose Only

$webHooksHander->doProcessWebHook($billingRecurlyWebHook->getId());

config::getLogger()->addInfo('Processing recurly webhook done successfully');

config::getLogger()->addInfo('Receiving recurly webhook done successfully');

?>