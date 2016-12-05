<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../subscriptions/WecashupSubscriptionsHandler.php';

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
			switch ($received_transaction_status) {
				case 'PAID' :
					$api_subscription->setSubStatus('active');
					$api_subscription->setSubActivatedDate($now);
					$api_subscription->setSubPeriodStartedDate($now);
					$billingsTransaction->setTransactionStatus(BillingsTransactionStatus::success);
					$billingsTransaction->setUpdateType($update_type);
					break;
				case 'FAILED' :
					$api_subscription->setSubStatus('expired');
					$api_subscription->setSubExpiresDate($now);
					$billingsTransaction->setTransactionStatus(BillingsTransactionStatus::failed);
					$billingsTransaction->setUpdateType($update_type);
					break;
				default :
					//Exception
					$msg = "unknown transaction_status=".$received_transaction_status;
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				//SUBSCRIPTION UPDATE
				$wecashupSubscriptionsHandler = new WecashupSubscriptionsHandler();
				$db_subscription = $wecashupSubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, $update_type, $billingsWebHook->getId());	
				//TRANSACTION UPDATE
				$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			config::getLogger()->addInfo("processing wecashup webHook with id=".$billingsWebHook->getId()." done successfully");
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing wecashup webHook with id=".$billingsWebHook->getId().", message=".$e->getMessage();
			config::getLogger()->addError("processing wecashup webHook with id=".$billingsWebHook->getId()." failed : ". $msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
}

?>