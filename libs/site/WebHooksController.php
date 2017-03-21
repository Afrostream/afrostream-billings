<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../webhooks/WebHooksHandler.php';
require_once __DIR__ . '/../providers/cashway/client/cashway_lib.php';
require_once __DIR__ . '/../providers/cashway/client/compat.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

use CashWay\API;

class WebHooksController extends BillingsController {
	
	public function providerWebHooksPosting(Request $request, Response $response, array $args) {
		try {
			$providerName = NULL;
			if(!isset($args['providerName'])) {
				//exception
				$msg = "field 'providerName' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerName = $args['providerName'];
			$providerBillingUuid = NULL;
			if(!isset($args['providerBillingUuid'])) {
				//LATER : exception
				$msg = "field 'providerBillingUuid' is missing, using default";
				config::getLogger()->addError($msg);
			} else {
				$providerBillingUuid = $args['providerBillingUuid'];
			}
			$provider = NULL;
			if($providerBillingUuid == NULL) {
				$platformId = 1;/* 1 = www.afrostream.tv */
				$provider = ProviderDAO::getProviderByName($providerName, $platformId);
				if($provider == NULL) {
					$msg = "provider with name=".$providerName.", for platformId=".$platformId." is unknown";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
			} else {
				$provider = ProviderDAO::getProviderByUuid($providerBillingUuid);
				if($provider == NULL) {
					$msg = "provider with uuid=".$providerBillingUuid." is unknown";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($provider->getName() != $providerName) {
					$msg = "provider with uuid=".$providerBillingUuid.", expecting providerName=".$providerName.", found providerName=".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);					
				}
			}
			switch($providerName) {
				case 'recurly' :
					return($this->recurlyWebHooksPosting($request, $response, $args, $provider));
				case 'stripe' :
					return($this->stripeWebHooksPosting($request, $response, $args, $provider));
				case 'gocardless' :
					return($this->gocardlessWebHooksPosting($request, $response, $args, $provider));
				case 'bachat' :
					return($this->bachatWebHooksPosting($request, $response, $args, $provider));
				case 'cashway' :
					return($this->cashwayWebHooksPosting($request, $response, $args, $provider));
				case 'braintree' :
					return($this->braintreeWebHooksPosting($request, $response, $args, $provider));
				case 'netsize' :
					return($this->netsizeWebHooksPosting($request, $response, $args, $provider));
				case 'wecashup' :
					return($this->wecashupWebHooksPosting($request, $response, $args, $provider));
				default :
					$msg = "providerName : ".$providerName." is unknown";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while treating a webhook, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnBillingsExceptionAsJson($response, $e, 500));
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while treating a webhook, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnExceptionAsJson($response, $e, 500));
		}
	}
	
	protected function recurlyWebHooksPosting(Request $request, Response $response, array $args, Provider $provider) {
		config::getLogger()->addInfo('Receiving recurly webhook...');
		
		$valid_passwords = array ($provider->getWebhookKey() => $provider->getWebhookSecret());
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
			
			$webHooksHander = new WebHooksHander();
			
			config::getLogger()->addInfo('Saving recurly webhook...');
			$billingsWebHook = $webHooksHander->doSaveWebHook($provider, $post_data);
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

	protected function stripeWebHooksPosting(Request $request, Response $response, array $args, Provider $provider) {
		config::getLogger()->addInfo('Receiving stripe webhook...');

		$valid_passwords = array ($provider->getWebhookKey() => $provider->getWebhookSecret());
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

			$billingsWebHook = $webHooksHander->doSaveWebHook($provider, $post_data);

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
	
	protected function gocardlessWebHooksPosting(Request $request, Response $response, array $args, Provider $provider) {
		config::getLogger()->addInfo('Receiving gocardless webhook...');
		
		$gocardless_secret = $provider->getWebhookSecret();
		
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
			$billingsWebHook = $webHooksHander->doSaveWebHook($provider, $post_data);
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
	
	protected function bachatWebHooksPosting(Request $request, Response $response, array $args, Provider $provider) {
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
			$billingsWebHook = $webHooksHander->doSaveWebHook($provider, $post_data);
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
	
	protected function cashwayWebHooksPosting(Request $request, Response $response, array $args, Provider $provider) {
		config::getLogger()->addInfo('Receiving cashway webhook...');
		$result = API::receiveNotification($request->getBody(), getallheaders(), $provider->getWebhookSecret());
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
			$billingsWebHook = $webHooksHander->doSaveWebHook($provider, $post_data);
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

	protected function braintreeWebHooksPosting(Request $request, Response $response, array $args, Provider $provider) {
		config::getLogger()->addInfo('Receiving braintree webhook...');
		//
		Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
		Braintree_Configuration::merchantId($provider->getMerchantId());
		Braintree_Configuration::publicKey($provider->getApiKey());
		Braintree_Configuration::privateKey($provider->getApiSecret());
		//
		$bt_signature = $request->getParsedBodyParam('bt_signature');
		$bt_payload = $request->getParsedBodyParam('bt_payload');
		//
		$validated = false;
		if(isset($bt_signature) && isset($bt_payload)) {
			$webhookNotification = Braintree\WebhookNotification::parse($bt_signature, $bt_payload);
			$validated = true;
		}
		
		if (!$validated) {
			config::getLogger()->addError('Receiving braintree webhook failed, Unauthorized access');
			header('WWW-Authenticate: Basic realm="My Realm"');
			header('HTTP/1.0 401 Unauthorized');
			die ("Not authorized");
		}
		
		try {
			config::getLogger()->addInfo('Treating braintree webhook...');
	
			$webHooksHander = new WebHooksHander();
			
			$bt_signature = $request->getParsedBodyParam('bt_signature');
			$bt_payload = $request->getParsedBodyParam('bt_payload');
			
			$post_data_as_array = array();
			
			$post_data_as_array['bt_signature'] = $bt_signature;
			$post_data_as_array['bt_payload'] = $bt_payload;
			
			$post_data_as_json = json_encode($post_data_as_array);
			
			config::getLogger()->addInfo('Saving braintree webhook...');
			$billingsWebHook = $webHooksHander->doSaveWebHook($provider, $post_data_as_json);
			config::getLogger()->addInfo('Saving braintree webhook done successfully');
	
			config::getLogger()->addInfo('Processing braintree webhook, id='.$billingsWebHook->getId().'...');
			$webHooksHander->doProcessWebHook($billingsWebHook->getId());
			config::getLogger()->addInfo('Processing braintree webhook done successfully, id='.$billingsWebHook->getId());
	
			config::getLogger()->addInfo('Treating braintree webhook done successfully, id='.$billingsWebHook->getId());
		} catch(BillingsException $e) {
			$msg = "an exception occurred while treating a braintree webhook, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnBillingsExceptionAsJson($response, $e, 500));
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while treating a braintree webhook, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnExceptionAsJson($response, $e, 500));
		}
		config::getLogger()->addInfo('Receiving braintree webhook done successfully');
	}
					
	protected function netsizeWebHooksPosting(Request $request, Response $response, array $args, Provider $provider) {
		config::getLogger()->addInfo('Receiving netsize webhook...');
		$post_data = file_get_contents('php://input');
		try {
			config::getLogger()->addInfo('Treating netsize webhook...');
		
			$webHooksHander = new WebHooksHander();
		
			config::getLogger()->addInfo('Saving netsize webhook...');
			$billingsWebHook = $webHooksHander->doSaveWebHook($provider, $post_data);
			config::getLogger()->addInfo('Saving netsize webhook done successfully');
		
			config::getLogger()->addInfo('Processing netsize webhook, id='.$billingsWebHook->getId().'...');
			$webHooksHander->doProcessWebHook($billingsWebHook->getId());
			config::getLogger()->addInfo('Processing netsize webhook done successfully, id='.$billingsWebHook->getId());
		
			config::getLogger()->addInfo('Treating netsize webhook done successfully, id='.$billingsWebHook->getId());
			
			$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><notification-response version="1.2" xmlns="http://www.netsize.com/ns/pay/event"/>');
		
			$response = $response->withStatus(200);
			$response->getBody()->write($xml->asXML());
			return($response);
		} catch(BillingsException $e) {
			$msg = "an exception occurred while treating a netsize webhook, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnBillingsExceptionAsJson($response, $e, 500));
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while treating a netsize webhook, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnExceptionAsJson($response, $e, 500));
		}
		config::getLogger()->addInfo('Receiving netsize webhook done successfully');
	}
	
	protected function wecashupWebHooksPosting(Request $request, Response $response, array $args, Provider $provider) {

		config::getLogger()->addInfo('Receiving wecashup webhook...');
		
		$validated = false;
		
		$post_data = file_get_contents('php://input');
		parse_str($post_data, $post_data_as_array);
		
		$received_transaction_merchant_secret = NULL;
		if(array_key_exists('merchant_secret', $post_data_as_array)) {
			$received_transaction_merchant_secret = $post_data_as_array['merchant_secret'];
		}
		if($provider->getApiSecret() === $received_transaction_merchant_secret) {
			$validated = true;
		}
		
		if (!$validated) {
			config::getLogger()->addError('Receiving wecashup webhook failed, Unauthorized access');
			header('WWW-Authenticate: Basic realm="My Realm"');
			header('HTTP/1.0 401 Unauthorized');
			die ("Not authorized");
		}
		
		try {
			config::getLogger()->addInfo('Treating wecashup webhook...');
				
			$webHooksHander = new WebHooksHander();
				
			config::getLogger()->addInfo('Saving wecashup webhook...');
			$billingsWebHook = $webHooksHander->doSaveWebHook($provider, $post_data);
			config::getLogger()->addInfo('Saving wecashup webhook done successfully');
		
			config::getLogger()->addInfo('Processing wecashup webhook, id='.$billingsWebHook->getId().'...');
			$webHooksHander->doProcessWebHook($billingsWebHook->getId());
			config::getLogger()->addInfo('Processing wecashup webhook done successfully, id='.$billingsWebHook->getId());
		
			config::getLogger()->addInfo('Treating wecashup webhook done successfully, id='.$billingsWebHook->getId());
		} catch(BillingsException $e) {
			$msg = "an exception occurred while treating a wecashup webhook, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnBillingsExceptionAsJson($response, $e, 500));
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while treating a wecashup webhook, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			return($this->returnExceptionAsJson($response, $e, 500));
		}
		config::getLogger()->addInfo('Receiving wecashup webhook done successfully');
	}
	
}

?>