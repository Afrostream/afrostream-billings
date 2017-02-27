<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';

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
				$msg = "unknown provider with id : ".$billingsWebHook->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerWebHooksHandler = ProviderHandlersBuilder::getProviderWebHooksHandlerInstance($provider);
			$providerWebHooksHandler->doProcessWebHook($billingsWebHook, $update_type);
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