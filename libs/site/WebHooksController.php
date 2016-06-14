<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ .'/BillingsController.php';
require_once __DIR__ . '/../webhooks/WebHooksHandler.php';
require_once __DIR__ . '/../providers/cashway/client/cashway_lib.php';
require_once __DIR__ . '/../providers/cashway/client/compat.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

use CashWay\API;

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
			config::getLogger()->addError('Receiving recurly webhook failed, Unauthorized access');
			header('WWW-Authenticate: Basic realm="My Realm"');
			header('HTTP/1.0 401 Unauthorized');
			die ("Not authorized");
		}
		
		try {
			config::getLogger()->addInfo('Treating recurly webhook...');
			
			$post_data = file_get_contents('php://input');
			
			//Have to send it to other services (exception has to be catched)
			try {
				$urls = getenv('RECURLY_WH_REPOST_URLS');
				$tok = strtok($urls, ";");
				while($tok !== false) {
					//
					$url = $tok;
					config::getLogger()->addInfo('Reposting the webhook to url='.$url.'...');
					$curl_options = array(
							CURLOPT_URL => $url,
							CURLOPT_CUSTOMREQUEST => 'POST',
							CURLOPT_POSTFIELDS => $post_data,
							CURLOPT_HTTPHEADER => array(
									'Content-Length: ' . strlen($post_data)
							),
							CURLOPT_RETURNTRANSFER => true,
							CURLOPT_HEADER  => false
					);
					$CURL = curl_init();
					curl_setopt_array($CURL, $curl_options);
					$content = curl_exec($CURL);
					$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
					curl_close($CURL);
					config::getLogger()->addInfo('Reposting the webhook to url='.$url.' done, httpCode='.$httpCode);
					//done
					$tok = strtok(";");
				}
			} catch(Exception $e) {
				config::getLogger()->addError('Reposting the webhook to url='.$url.' failed, but continuing anyway, message='.$e->getMessage());
			}
			
			$webHooksHander = new WebHooksHander();
			
			config::getLogger()->addInfo('Saving recurly webhook...');
			$billingsWebHook = $webHooksHander->doSaveWebHook('recurly', $post_data);
			config::getLogger()->addInfo('Saving recurly webhook done successfully');
		
			config::getLogger()->addInfo('Processing recurly webhook, id='.$billingsWebHook->getId().'...');
			$webHooksHander->doProcessWebHook($billingsWebHook->getId());
			config::getLogger()->addInfo('Processing recurly webhook done successfully, id='.$billingsWebHook->getId());
		
			config::getLogger()->addInfo('Treating recurly webhook done successfully, id='.$billingsWebHook->getId());
		} catch(BillingsException $e) {
			$msg = "an exception occurred while treating a recurly webhook, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnBillingsExceptionAsJson($response, $e, 500));
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while treating a recurly webhook, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnExceptionAsJson($response, $e, 500));
		}
		config::getLogger()->addInfo('Receiving recurly webhook done successfully');
	}

	public function stripeWebHooksPosting(Request $request, Response $response, array $args) {
		config::getLogger()->addInfo('Receiving stripe webhook...');

		$valid_passwords = array (getEnv('STRIPE_WH_HTTP_AUTH_USER') => getEnv('STRIPE_WH_HTTP_AUTH_PWD'));
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
			config::getLogger()->addError('Receiving stripe webhook failed, Unauthorized access');
			header('WWW-Authenticate: Basic realm="My Realm"');
			header('HTTP/1.0 401 Unauthorized');
			die ("Not authorized");
		}

		try {
			$post_data = file_get_contents('php://input');
			$webHooksHander = new WebHooksHander();

			config::getLogger()->addInfo('Saving stripe webhook...');

			$billingsWebHook = $webHooksHander->doSaveWebHook('stripe', $post_data);

			config::getLogger()->addInfo('Saving stripe webhook done successfully');
			config::getLogger()->addInfo('Processing stripe webhook, id='.$billingsWebHook->getId().'...');

			$webHooksHander->doProcessWebHook($billingsWebHook->getId());

			config::getLogger()->addInfo('Processing stripe webhook done successfully, id='.$billingsWebHook->getId());

			config::getLogger()->addInfo('Treating stripe webhook done successfully, id='.$billingsWebHook->getId());
		} catch(BillingsException $e) {
			$msg = "an exception occurred while treating a stripe webhook, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnBillingsExceptionAsJson($response, $e, 500));
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while treating a recurly webhook, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnExceptionAsJson($response, $e, 500));
		}
		config::getLogger()->addInfo('Receiving stripe webhook done successfully');
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
			config::getLogger()->addError('Receiving gocardless webhook failed, Invalid token');
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
			config::getLogger()->addInfo('Processing gocardless webhook done successfully, id='.$billingsWebHook->getId());
		
			config::getLogger()->addInfo('Treating gocardless webhook done successfully, id='.$billingsWebHook->getId());
		} catch(BillingsException $e) {
			$msg = "an exception occurred while treating a gocardless webhook, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnBillingsExceptionAsJson($response, $e, 500));
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while treating a gocardless webhook, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnExceptionAsJson($response, $e, 500));
		}
		config::getLogger()->addInfo('Receiving gocardless webhook done successfully');
	}
	
	public function bachatWebHooksPosting(Request $request, Response $response, array $args) {
		config::getLogger()->addInfo('Receiving bachat webhook...');
	
		$valid_passwords = array (getEnv('BACHAT_WH_HTTP_AUTH_USER') => getEnv('BACHAT_WH_HTTP_AUTH_PWD'));
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
			config::getLogger()->addError('Receiving bachat webhook failed, Unauthorized access');
			header('WWW-Authenticate: Basic realm="My Realm"');
			header('HTTP/1.0 401 Unauthorized');
			die ("Not authorized");
		}
	
		try {
			config::getLogger()->addInfo('Treating bachat webhook...');
				
			$post_data = file_get_contents('php://input');
			
			$webHooksHander = new WebHooksHander();
				
			config::getLogger()->addInfo('Saving bachat webhook...');
			$billingsWebHook = $webHooksHander->doSaveWebHook('bachat', $post_data);
			config::getLogger()->addInfo('Saving bachat webhook done successfully');
	
			config::getLogger()->addInfo('Processing bachat webhook, id='.$billingsWebHook->getId().'...');
			$webHooksHander->doProcessWebHook($billingsWebHook->getId());
			config::getLogger()->addInfo('Processing bachat webhook done successfully, id='.$billingsWebHook->getId());
	
			config::getLogger()->addInfo('Treating bachat webhook done successfully, id='.$billingsWebHook->getId());
		} catch(BillingsException $e) {
			$msg = "an exception occurred while treating a bachat webhook, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnBillingsExceptionAsJson($response, $e, 500));
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while treating a bachat webhook, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnExceptionAsJson($response, $e, 500));
		}
		config::getLogger()->addInfo('Receiving bachat webhook done successfully');
	}
	
	public function cashwayWebHooksPosting(Request $request, Response $response, array $args) {
		config::getLogger()->addInfo('Receiving cashway webhook...');
		$result = API::receiveNotification($request->getBody(), getallheaders(), getEnv('CASHWAY_WH_SECRET'));
		if ($result[0] === false) {
			config::getLogger()->addError('Receiving cashway webhook failed, message='.$result[1]);
			header('HTTP/1.0 400 '.$result[1]);
			header('User-Agent: '.getEnv('CASHWAY_USER_AGENT'));
			die ("Bad Request");
		}
		$post_data = file_get_contents('php://input');
		try {
			config::getLogger()->addInfo('Treating cashway webhook...');
		
			$webHooksHander = new WebHooksHander();
		
			config::getLogger()->addInfo('Saving cashway webhook...');
			$billingsWebHook = $webHooksHander->doSaveWebHook('cashway', $post_data);
			config::getLogger()->addInfo('Saving cashway webhook done successfully');
		
			config::getLogger()->addInfo('Processing cashway webhook, id='.$billingsWebHook->getId().'...');
			$webHooksHander->doProcessWebHook($billingsWebHook->getId());
			config::getLogger()->addInfo('Processing cashway webhook done successfully, id='.$billingsWebHook->getId());
		
			config::getLogger()->addInfo('Treating cashway webhook done successfully, id='.$billingsWebHook->getId());
			//Asked By Romain d'Alverny From CASHWAY, should be only for 'status_check' event but I do prefer not do specific coding : so same for all
			$json_as_array = array();
			$json_as_array['status'] = 'ok';
			$json_as_array['message'] = '';
			$json_as_array['agent'] = getEnv('CASHWAY_USER_AGENT');
			$json = json_encode($json_as_array);
			$response = $response->withHeader('User-Agent', getEnv('CASHWAY_USER_AGENT'));
			$response = $response->withStatus(200);
			$response = $response->withHeader('Content-Type', 'application/json');
			$response->getBody()->write($json);
			return($response);
		} catch(BillingsException $e) {
			$msg = "an exception occurred while treating a cashway webhook, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnBillingsExceptionAsJson($response, $e, 500));
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while treating a cashway webhook, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnExceptionAsJson($response, $e, 500));
		}
		config::getLogger()->addInfo('Receiving cashway webhook done successfully');
	}
	
}

?>