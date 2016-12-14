<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../providers/recurly/webhooks/RecurlyWebHooksHandler.php';
require_once __DIR__ . '/../providers/gocardless/webhooks/GocardlessWebHooksHandler.php';
require_once __DIR__ . '/../providers/cashway/webhooks/CashwayWebHooksHandler.php';
require_once __DIR__ . '/../providers/stripe/webhooks/StripeWebHooksHandler.php';
require_once __DIR__ . '/../providers/braintree/webhooks/BraintreeWebHooksHandler.php';
require_once __DIR__ . '/../providers/netsize/webhooks/NetsizeWebHooksHandler.php';

class WebHooksHander {
	
	public function __construct() {
	}
	
	public function doSaveWebHook($provider_name, $post_data) {
		try {
			config::getLogger()->addInfo("post_data saving...");
			$provider = ProviderDAO::getProviderByName($provider_name);
				
			if($provider == NULL) {
				$msg = "unknown provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$billingsWebHook = BillingsWebHookDAO::addBillingsWebHook($provider->getId(), $post_data);
			config::getLogger()->addInfo("post_data saving done successfully, id=".$billingsWebHook->getId());
			return($billingsWebHook);
		} catch (Exception $e) {
			$msg = "an unknown error occurred while saving post_data, message=" . $e->getMessage();
			config::getLogger()->addError("post_data saving failed : ". $msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	public function doProcessWebHook($id, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing webHook with id=".$id."...");
			$billingsWebHook = BillingsWebHookDAO::getBillingsWebHookById($id);
			BillingsWebHookDAO::updateProcessingStatusById($id, 'running');
			$billingsWebHookLog = BillingsWebHookLogDAO::addBillingsWebHookLog($id);
			
			$provider = ProviderDAO::getProviderById($billingsWebHook->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider with id : ".$user->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			switch($provider->getName()) {
				case 'recurly' :
					$recurlyWebHooksHandler = new RecurlyWebHooksHandler();
					$recurlyWebHooksHandler->doProcessWebHook($billingsWebHook, $update_type);
					break;
				case 'gocardless' :
					$gocardlessWebHooksHandler = new GocardlessWebHooksHandler();
					$gocardlessWebHooksHandler->doProcessWebHook($billingsWebHook, $update_type);
					break;
				case 'cashway' :
					$cashwayWebHooksHandler = new CashwayWebHooksHandler();
					$cashwayWebHooksHandler->doProcessWebHook($billingsWebHook, $update_type);
					break;
				case 'stripe':
					$stripeWebHookHandler = new StripeWebHooksHandler();
					$stripeWebHookHandler->doProcessWebHook($billingsWebHook, $update_type);
					break;
				case 'braintree' :
					$braintreeWebHooksHandler = new BraintreeWebHooksHandler();
					$braintreeWebHooksHandler->doProcessWebHook($billingsWebHook, $update_type);
					break;
				case 'netsize' :
					$netsizeWebHooksHandler = new NetsizeWebHooksHandler();
					$netsizeWebHooksHandler->doProcessWebHook($billingsWebHook, $update_type);
					break;
				case 'wecashup' :
					$wecashupWebHooksHandler = new WecashupWebHooksHandler();
					$wecashupWebHooksHandler->doProcessWebHook($billingsWebHook, $update_type);
					break;
				default:
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			BillingsWebHookDAO::updateProcessingStatusById($id, 'done');
			//
			$billingsWebHookLog->setProcessingStatus('done');
			$billingsWebHookLog->setMessage('');
			$billingsWebHookLog = BillingsWebHookLogDAO::updateBillingsWebHookLogProcessingStatus($billingsWebHookLog);
			//
			config::getLogger()->addInfo("processing webHook with id=".$id." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while processing webHook with id=".$id.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("processing webHook failed : ".$msg);
			BillingsWebHookDAO::updateProcessingStatusById($id, 'error');
			$billingsWebHookLog->setProcessingStatus('error');
			$billingsWebHookLog->setMessage($e->getMessage());
			$billingsWebHookLog = BillingsWebHookLogDAO::updateBillingsWebHookLogProcessingStatus($billingsWebHookLog);
			throw $e;
		} catch(Exception $e) {
			config::getLogger()->addError("an unknown exception occurred while processing webHook with id=".$id.", message=".$e->getMessage());
			BillingsWebHookDAO::updateProcessingStatusById($id, 'error');
			$billingsWebHookLog->setProcessingStatus('error');
			$billingsWebHookLog->setMessage($e->getMessage());
			$billingsWebHookLog = BillingsWebHookLogDAO::updateBillingsWebHookLogProcessingStatus($billingsWebHookLog);
			throw $e;
		}
	}

}

?>