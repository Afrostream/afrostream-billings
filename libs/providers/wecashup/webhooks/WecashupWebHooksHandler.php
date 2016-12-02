<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';

class WecashupWebHooksHandler {
	
	public function __construct() {
	}
	
	public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing wecashup webHook with id=".$billingsWebHook->getId()."...");
			//provider
			$provider = ProviderDAO::getProviderByName('wecashup');
			if($provider == NULL) {
				$msg = "provider named 'wecashup' not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$post_data = $billingsWebHook->getPostData();
			parse_str($post_data, $post_data_as_array);
			
			$received_transaction_merchant_secret = NULL;
			$received_transaction_uid = NULL;
			$received_transaction_status  = NULL;
			$received_transaction_details = NULL;
			$received_transaction_token = NULL;
			if(in_array('merchant_secret', $post_data_as_array)) {
				$received_transaction_merchant_secret = $post_data_as_array['merchant_secret'];
			}
			if(in_array('transaction_uid', $post_data_as_array)) {
				$received_transaction_uid = $post_data_as_array['transaction_uid'];
			}			
			if(in_array('transaction_status', $post_data_as_array)) {
				$received_transaction_status = $post_data_as_array['transaction_status'];
			}
			if(in_array('transaction_details', $post_data_as_array)) {
				$received_transaction_details = $post_data_as_array['transaction_details'];
			}
			if(in_array('transaction_token', $post_data_as_array)) {
				$received_transaction_token = $post_data_as_array['transaction_token'];
			}
			//check merchant
			if(getEnv('WECASHUP_MERCHANT_SECRET') != $received_transaction_merchant_secret) {
				$msg = "merchant secret given does not match";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//check transaction
			$billingsTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($provider->getId(), $received_transaction_uid);
			if($billingsTransaction == NULL) {
				//Exception ? Maybe bypass only...
				$msg = "no transaction with transaction_uid=".$received_transaction_uid." found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$billingsTransactionOpts = BillingsTransactionOptsDAO::getBillingsTransactionOptByTransactionId($billingsTransaction->getId());
			if(!array_key_exists('transactionToken', $billingsTransactionOpts->getOpts())) {
				$msg = "no transactionToken linked to the transaction";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$transactionToken = $billingsTransactionOpts->getOpt('transactionToken');
			if($transactionToken != $received_transaction_token) {
				$msg = "transactionToken given does not match";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//OK
			if($billingsTransaction->getSubId() == NULL) {
				//Exception
			}
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($billingsTransaction->getSubId());
			if($db_subscription == NULL) {
				//Exception
			}
			switch ($received_transaction_status) {
				case 'PAID' :
					//future -> active (see cashway...)
					break;
				case 'FAILED' :
					//future -> ??? (delete ?)
					break;
				default :
					//Exception
					$msg = "unknown transaction_status=".$received_transaction_status;
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			//
			config::getLogger()->addInfo("processing wecashup webHook with id=".$billingsWebHook->getId()." done successfully");
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing wecashup webHook with id=".$billingsWebHook->getId().", message=".$e->getMessage();
			config::getLogger()->addError("processing wecashup webHook with id=".$billingsWebHook->getId()." failed : ". $msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
}

?>