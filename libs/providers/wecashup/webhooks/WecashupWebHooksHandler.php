<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../subscriptions/WecashupSubscriptionsHandler.php';
require_once __DIR__ . '/../transactions/WecashupTransactionsHandler.php';
require_once __DIR__ . '/../client/WecashupClient.php';
require_once __DIR__ . '/../../global/webhooks/ProviderWebHooksHandler.php';

class WecashupWebHooksHandler extends ProviderWebHooksHandler {
	
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
			if(array_key_exists('merchant_secret', $post_data_as_array)) {
				$received_transaction_merchant_secret = $post_data_as_array['merchant_secret'];
			}
			if(array_key_exists('transaction_uid', $post_data_as_array)) {
				$received_transaction_uid = $post_data_as_array['transaction_uid'];
			}			
			if(array_key_exists('transaction_status', $post_data_as_array)) {
				$received_transaction_status = $post_data_as_array['transaction_status'];
			}
			if(array_key_exists('transaction_details', $post_data_as_array)) {
				$received_transaction_details = $post_data_as_array['transaction_details'];
			}
			if(array_key_exists('transaction_token', $post_data_as_array)) {
				$received_transaction_token = $post_data_as_array['transaction_token'];
			}
			//check merchant
			if($this->provider->getApiSecret() != $received_transaction_merchant_secret) {
				$msg = "merchant secret given does not match";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$wecashupClient = new WecashupClient($this->provider->getMerchantId(), $this->provider->getApiKey(), $this->provider->getApiSecret());
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
			if(!array_key_exists('transaction_token', $billingsTransactionOpts->getOpts())) {
				$msg = "no transaction_token linked to the transaction";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$transactionToken = $billingsTransactionOpts->getOpt('transaction_token');
			if($transactionToken != $received_transaction_token) {
				$msg = "transaction_token given does not match";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//OK
			if($billingsTransaction->getSubId() == NULL) {
				$msg = "transaction with transaction_uid=".$billingsTransaction->getTransactionProviderUuid()." is not linked to any subscription";
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
			$internalPlan = InternalPlanDAO::getInternalPlanById($plan->getInternalPlanId());
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
					//<-- HACK : IMMEDIATELY ACTIVE : DON'T TOUCH THE CURRENT STATUS
					//$api_subscription->setSubStatus('future');
					//--> HACK : IMMEDIATELY ACTIVE
					$billingsTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::waiting));
					$billingsTransaction->setUpdateType($update_type);
					if($paymentTransaction->getTransactionSenderCountryCodeIso2() != NULL) {
						$billingsTransaction->setCountry($paymentTransaction->getTransactionSenderCountryCodeIso2());
					}
					break;
				case 'PENDING' :
					//<-- HACK : IMMEDIATELY ACTIVE : DON'T TOUCH THE CURRENT STATUS
					//$api_subscription->setSubStatus('future');
					//--> HACK : IMMEDIATELY ACTIVE
					$billingsTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::waiting));
					$billingsTransaction->setUpdateType($update_type);
					if($paymentTransaction->getTransactionSenderCountryCodeIso2() != NULL) {
						$billingsTransaction->setCountry($paymentTransaction->getTransactionSenderCountryCodeIso2());
					}
					break;
				case 'PAID' :
					//<-- HACK : IMMEDIATELY ACTIVE : DON'T TOUCH THE CURRENT STATUS
					//$api_subscription->setSubStatus('active');
					//$api_subscription->setSubActivatedDate($now);
					//$api_subscription->setSubPeriodStartedDate($now);
					//--> HACK : IMMEDIATELY ACTIVE
					$billingsTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::success));
					$billingsTransaction->setUpdateType($update_type);
					if($paymentTransaction->getTransactionSenderCountryCodeIso2() != NULL) {
						$billingsTransaction->setCountry($paymentTransaction->getTransactionSenderCountryCodeIso2());
					}
					break;
				case 'FAILED' :
					$api_subscription->setSubStatus('expired');
					$api_subscription->setSubExpiresDate($now);
					if($api_subscription->getSubCanceledDate() == NULL) {
						$api_subscription->setSubCanceledDate($now);
					}
					$billingsTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::failed));
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
			$billingsTransaction->setPaymentMethodType(new BillingPaymentMethodType(BillingPaymentMethodType::mobile_money));
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				//SUBSCRIPTION UPDATE
				$wecashupSubscriptionsHandler = new WecashupSubscriptionsHandler($this->provider);
				$db_subscription = $wecashupSubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, $userOpts, $this->provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, $update_type, $updateId);
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
		//Search for payment : bdd Side
		$billingsPaymentTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($this->provider->getId(), $refundTransaction->getTransactionParentUid());
		if($billingsPaymentTransaction == NULL) {
			$msg = "refund transaction with id=".$refundTransaction->getTransactionUid()." is related to an unkown payment id=".$refundTransaction->getTransactionParentUid();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$user = UserDAO::getUserById($billingsPaymentTransaction->getUserId());
		if($user == NULL) {
			$msg = "unknown user with id : ".$billingsPaymentTransaction->getUserId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
		$wecashupTransactionsHandler = new WecashupTransactionsHandler($this->provider);
		$wecashupTransactionsHandler->createOrUpdateRefundFromProvider($user, $userOpts, NULL, $refundTransaction, $billingsPaymentTransaction, $update_type);
		//DONE
		config::getLogger()->addInfo('Processing wecashup hook refund done successfully');
	}
	
}

?>