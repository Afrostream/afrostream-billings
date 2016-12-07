<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../subscriptions/WecashupSubscriptionsHandler.php';
require_once __DIR__ . '/../client/WecashupClient.php';

class WecashupWebHooksHandler {
	
	private $provider = NULL;
	
	public function __construct() {
		$this->provider = ProviderDAO::getProviderByName('wecashup');
	}
	
	public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing wecashup webHook with id=".$billingsWebHook->getId()."...");
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
			$wecashupClient = new WecashupClient();
			$wecashupTransactionRequest = new WecashupTransactionRequest();
			$wecashupTransactionRequest->setTransactionUid($received_transaction_uid);
			$wecashupTransactionsResponse = $wecashupClient->getTransaction($wecashupTransactionRequest);
			$wecashupTransactionsResponseArray = $wecashupTransactionsResponse->getWecashupTransactionsResponseArray();
			if(count($wecashupTransactionsResponseArray) != 1) {
				//Exception
				$msg = "transaction with transactionUid=".$received_transaction_uid." was not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$wecashupTransactionResponse = $wecashupTransactionsResponseArray[0];
			switch($wecashupTransactionResponse->getTransactionType()) {
				case 'payment' :
					$this->doProcessPayment($wecashupTransactionResponse, $received_transaction_token, $update_type, $billingsWebHook->getId());					
					break;
				case 'refund' :
					$this->doProcessRefund($wecashupTransactionResponse, $received_transaction_token, $update_type, $billingsWebHook->getId());
					break;
				default :
					config::getLogger()->addWarning('transactionType : '.$wecashupTransactionResponse->getTransactionType().' is not yet implemented');
					break;
			}
			config::getLogger()->addInfo("processing wecashup webHook with id=".$billingsWebHook->getId()." done successfully");
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing wecashup webHook with id=".$billingsWebHook->getId().", message=".$e->getMessage();
			config::getLogger()->addError("processing wecashup webHook with id=".$billingsWebHook->getId()." failed : ". $msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	private function doProcessPayment(WecashupTransactionResponse $paymentTransaction, $received_transaction_token, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing wecashup hook payment...');
		//check transaction
		$billingsTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($this->provider->getId(), $paymentTransaction->getTransactionUid());
		if($billingsTransaction == NULL) {
			if($paymentTransaction->getTransactionStatus() != 'FAILED') {
				$msg = "no transaction with transaction_uid=".$paymentTransaction->getTransactionUid()." found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			} else {
				$msg = "no transaction with transaction_uid=".$paymentTransaction->getTransactionUid()." found but has been ignored";
				config::getLogger()->addInfo($msg);
			}
		} else {
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
				$msg = "transaction with transaction_uuid=".$billingsTransaction->getTransactionProviderUuid()." is not linked to any subscription";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($billingsTransaction->getSubId());
			if($db_subscription == NULL) {
				$msg = "unknown subscription with id=".$billingsTransaction->getSubId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$user = UserDAO::getUserById($db_subscription->getUserId());
			if($user == NULL) {
				$msg = "unknown user with id : ".$db_subscription->getUserId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			$plan = PlanDAO::getPlanById($db_subscription->getPlanId());
			if($plan == NULL) {
				$msg = "unknown plan with id : ".$db_subscription->getPlanId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
			$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($plan->getId()));
			if($internalPlan == NULL) {
				$msg = "plan with uuid=".$plan->getPlanUuid()." for provider wecashup is not linked to an internal plan";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
			$api_subscription = clone $db_subscription;
			$now = new DateTime();
			switch ($paymentTransaction->getTransactionStatus()) {
				case 'TOVALIDATE' :
					$api_subscription->setSubStatus('future');
					$billingsTransaction->setTransactionStatus(BillingsTransactionStatus::waiting);
					$billingsTransaction->setUpdateType($update_type);
					if($paymentTransaction->getTransactionSenderCountryCodeIso2() != NULL) {
						$billingsTransaction->setCountry($paymentTransaction->getTransactionSenderCountryCodeIso2());
					}
					break;
				case 'PENDING' :
					$api_subscription->setSubStatus('future');
					$billingsTransaction->setTransactionStatus(BillingsTransactionStatus::waiting);
					$billingsTransaction->setUpdateType($update_type);
					if($paymentTransaction->getTransactionSenderCountryCodeIso2() != NULL) {
						$billingsTransaction->setCountry($paymentTransaction->getTransactionSenderCountryCodeIso2());
					}
					break;
				case 'PAID' :
					$api_subscription->setSubStatus('active');
					$api_subscription->setSubActivatedDate($now);
					$api_subscription->setSubPeriodStartedDate($now);
					$billingsTransaction->setTransactionStatus(BillingsTransactionStatus::success);
					$billingsTransaction->setUpdateType($update_type);
					if($paymentTransaction->getTransactionSenderCountryCodeIso2() != NULL) {
						$billingsTransaction->setCountry($paymentTransaction->getTransactionSenderCountryCodeIso2());
					}
					break;
				case 'FAILED' :
					$api_subscription->setSubStatus('expired');
					$api_subscription->setSubExpiresDate($now);
					$billingsTransaction->setTransactionStatus(BillingsTransactionStatus::failed);
					$billingsTransaction->setUpdateType($update_type);
					if($paymentTransaction->getTransactionSenderCountryCodeIso2() != NULL) {
						$billingsTransaction->setCountry($paymentTransaction->getTransactionSenderCountryCodeIso2());
					}
					break;
				default :
					//Exception
					$msg = "unknown transaction_status=".$paymentTransaction->getTransactionStatus();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				//SUBSCRIPTION UPDATE
				$wecashupSubscriptionsHandler = new WecashupSubscriptionsHandler();
				$db_subscription = $wecashupSubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, $userOpts, $this->provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, $update_type, $billingsWebHook->getId());
				//TRANSACTION UPDATE
				$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
		}
		//DONE
		config::getLogger()->addInfo('Processing wecashup hook payment done successfully');
	}
	
	private function doProcessRefund(WecashupTransactionResponse $refundTransaction, $received_transaction_token, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing wecashup hook refund...');
		//TODO
		//DONE
		config::getLogger()->addInfo('Processing wecashup hook refund done successfully');
	}
	
}

?>