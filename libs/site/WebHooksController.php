<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ .'/BillingsController.php';
require_once __DIR__ . '/../../libs/webhooks/WebHooksHandler.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class WebHooksController extends BillingsController {
	
	public function recurlyWebHooksPosting(Request $request, Response $response, array $args) {
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
			config::getLogger()->addError('Receiving gocardless webhook failed, Unauthorized access');
			header('WWW-Authenticate: Basic realm="My Realm"');
			header('HTTP/1.0 401 Unauthorized');
			die ("Not authorized");
		}
		
		try {
			config::getLogger()->addInfo('Treating recurly webhook...');
		
			$post_data = file_get_contents('php://input');
		
			$webHooksHander = new WebHooksHander();
		
			config::getLogger()->addInfo('Saving recurly webhook...');
			$billingsWebHook = $webHooksHander->doSaveWebHook('recurly', $post_data);
			config::getLogger()->addInfo('Saving recurly webhook done successfully');
		
			config::getLogger()->addInfo('Processing recurly webhook, id='.$billingsWebHook->getId().'...');
			$webHooksHander->doProcessWebHook($billingsWebHook->getId());
			config::getLogger()->addInfo('Processing recurly webhook done sucessfully, id='.$billingsWebHook->getId().'...');
		
			config::getLogger()->addInfo('Treating recurly webhook done successfully, id='.$billingsWebHook->getId().'...');
		} catch(BillingsException $e) {
			$msg = "an exception occurred while treating a recurly webhook, error_type=".$e->getExceptionType().",error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while treating a recurly webhook, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
		}
		config::getLogger()->addInfo('Receiving recurly webhook done successfully');
	}
	
	public function gocardlessWebHooksPosting(Request $request, Response $response, array $args) {
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
	}
	
}