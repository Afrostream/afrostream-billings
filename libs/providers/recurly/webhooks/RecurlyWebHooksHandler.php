<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../subscriptions/RecurlySubscriptionsHandler.php';
require_once __DIR__ . '/../transactions/RecurlyTransactionsHandler.php';

class RecurlyWebHooksHandler {
	
	public function __construct() {
	}
		
	public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing recurly webHook with id=".$billingsWebHook->getId()."...");
			$notification = new Recurly_PushNotification($billingsWebHook->getPostData());
			$this->doProcessNotification($notification, $update_type, $billingsWebHook->getId());
			config::getLogger()->addInfo("processing recurly webHook with id=".$billingsWebHook->getId()." done successfully");
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing recurly webHook with id=".$billingsWebHook->getId().", message=".$e->getMessage();
			config::getLogger()->addError("processing recurly webHook with id=".$billingsWebHook->getId()." failed : ". $msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	private function doProcessNotification(Recurly_PushNotification $notification, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing recurly hook notification...');
		switch ($notification->type) {
			case "new_subscription_notification" :
				//NOW IGNORED : since subscription is created through API, just log for information
				config::getLogger()->addInfo('notification type : '. $notification->type. ' is ignored');
				//WAS :
				//$this->doProcessSubscription($notification, $update_type, $updateId);
				break;
			case "updated_subscription_notification" :
				$this->doProcessSubscription($notification, $update_type, $updateId);
				break;
			case "canceled_subscription_notification" :
				$this->doProcessSubscription($notification, $update_type, $updateId);
				break;
			case "expired_subscription_notification" :
				$this->doProcessSubscription($notification, $update_type, $updateId);
				break;
			case "renewed_subscription_notification" :
				$this->doProcessSubscription($notification, $update_type, $updateId);
				break;
			case "reactivated_account_notification" :
				$this->doProcessSubscription($notification, $update_type, $updateId);
				break;
			case "scheduled_payment_notification" :
			case "processing_payment_notification" :
			case "successful_payment_notification" :
			case "failed_payment_notification" :
			case "void_payment_notification" :
				$this->doProcessPayment($notification, $update_type, $updateId);
				break;
			case "successful_refund_notification" :
				$this->doProcessRefund($notification, $update_type, $updateId);
				break;
			default :
				config::getLogger()->addWarning('notification type : '. $notification->type. ' is not yet implemented');
				break;
		}
		config::getLogger()->addInfo('Processing recurly hook notification done successfully');
	}
	
	private function doProcessSubscription(Recurly_PushNotification $notification, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing recurly hook subscription, notification_type='.$notification->type.'...');
		//
		Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
		Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
		//
		$subscription_provider_uuid = self::getNodeByName($notification->subscription, 'uuid');
		config::getLogger()->addInfo('Processing recurly hook subscription, subscription_provider_uuid='.$subscription_provider_uuid);
		//
		try {
			//
			$api_subscription = Recurly_Subscription::get($subscription_provider_uuid);
			//
		} catch (Recurly_NotFoundError $e) {
			config::getLogger()->addError("a not found exception occurred while getting recurly subscription with subscription_provider_uuid=".$subscription_provider_uuid." from api, message=".$e->getMessage());
			throw $e;
		} catch (Exception $e) {
			config::getLogger()->addError("an unknown exception occurred while getting recurly subscription with subscription_provider_uuid=".$subscription_provider_uuid." from api, message=".$e->getMessage());
			throw $e;
		}
		//provider
		$provider = ProviderDAO::getProviderByName('recurly');
		if($provider == NULL) {
			$msg = "provider named 'recurly' not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//plan
		$plan_uuid = $api_subscription->plan->plan_code;
		if($plan_uuid == NULL) {
			$msg = "plan uuid not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$plan = PlanDAO::getPlanByUuid($provider->getId(), $plan_uuid);
		if($plan == NULL) {
			$msg = "plan with uuid=".$plan_uuid." not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
		//$account_code
		$account_code = $api_subscription->account->get()->account_code;
		if($account_code == NULL) {
			$msg = "account_code not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($plan->getId()));
		if($internalPlan == NULL) {
			$msg = "plan with uuid=".$plan_uuid." for provider ".$provider->getName()." is not linked to an internal plan";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
		config::getLogger()->addInfo('searching user with account_code='.$account_code.'...');
		$user = UserDAO::getUserByUserProviderUuid($provider->getId(), $account_code);
		if($user == NULL) {
			$msg = 'searching user with account_code='.$account_code.' failed, no user found';
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		$db_subscription = $this->getDbSubscriptionByUuid($db_subscriptions, $subscription_provider_uuid);
		$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
		try {
			//START TRANSACTION
			pg_query("BEGIN");
			//ADD OR UPDATE
			if($db_subscription == NULL) {
				$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." not found for user with provider_user_uuid=".$user->getUserProviderUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//DO NOT CREATE ANYMORE : race condition when creating from API + from the webhook
				//WAS :
				//CREATE
				//$db_subscription = $recurlySubscriptionsHandler->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $update_type, $updateId);
			} else {
				//UPDATE
				$db_subscription = $recurlySubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, $update_type, $updateId);
			}
			//COMMIT
			pg_query("COMMIT");
		} catch(Exception $e) {
			pg_query("ROLLBACK");
			throw $e;
		}
		//
		config::getLogger()->addInfo('Processing recurly hook subscription, notification_type='.$notification->type.' done successfully');
	}
	
	private function getDbSubscriptionByUuid(array $db_subscriptions, $subUuid) {
		foreach ($db_subscriptions as $db_subscription) {
			if($db_subscription->getSubUid() == $subUuid) {
				return($db_subscription);
			}
		}
		return(NULL);
	}
	
	private static function getNodeByName(SimpleXMLElement $node, $name) {
		$out = null;
		foreach ($node->children() as $children) {
			if($children->getName() == $name) {
				$out = $children;
				break;
			}
		}
		return($out);
	}
	
	private function doProcessPayment(Recurly_PushNotification $notification, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing recurly hook payment, notification_type='.$notification->type.'...');
		//
		Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
		Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
		//
		$customer_provider_uuid = self::getNodeByName($notification->account, 'account_code');
		$payment_provider_uuid = self::getNodeByName($notification->transaction, 'id');
		config::getLogger()->addInfo('Processing recurly hook payment, payment_provider_uuid='.$payment_provider_uuid);
		$api_customer = NULL;
		$api_payment = NULL;
		try {
			//
			$api_customer = Recurly_Account::get($customer_provider_uuid);
			$api_payment = Recurly_Transaction::get($payment_provider_uuid);
			//
		} catch (Recurly_NotFoundError $e) {
			config::getLogger()->addError("a not found exception occurred while getting recurly informations from api, message=".$e->getMessage());
			throw $e;
		} catch (Exception $e) {
			config::getLogger()->addError("an unknown exception occurred while getting recurly informations from api, message=".$e->getMessage());
			throw $e;
		}
		//provider
		$provider = ProviderDAO::getProviderByName('recurly');
		if($provider == NULL) {
			$msg = "provider named 'recurly' not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$user = UserDAO::getUserByUserProviderUuid($provider->getId(), $customer_provider_uuid);
		if($user == NULL) {
			$msg = 'searching user with customer_provider_uuid='.$customer_provider_uuid.' failed, no user found';
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);			
		}
		$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
		$recurlyTransactionsHandler = new RecurlyTransactionsHandler();
		$recurlyTransactionsHandler->createOrUpdateChargeFromProvider($user, $userOpts, $api_customer, $api_payment, 'hook');
		config::getLogger()->addInfo('Processing recurly hook payment, notification_type='.$notification->type.' done successfully');
	}
	
	private function doProcessRefund(Recurly_PushNotification $notification, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing recurly hook refund, notification_type='.$notification->type.'...');
		//
		Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
		Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
		//
		$customer_provider_uuid = self::getNodeByName($notification->account, 'account_code');
		$refund_provider_uuid = self::getNodeByName($notification->transaction, 'id');
		config::getLogger()->addInfo('Processing recurly hook refund, refund_provider_uuid='.$refund_provider_uuid);
		$api_customer = NULL;
		$api_refund = NULL;
		try {
			//
			$api_customer = Recurly_Account::get($customer_provider_uuid);
			$api_refund = Recurly_Transaction::get($refund_provider_uuid);
			//
		} catch (Recurly_NotFoundError $e) {
			config::getLogger()->addError("a not found exception occurred while getting recurly informations from api, message=".$e->getMessage());
			throw $e;
		} catch (Exception $e) {
			config::getLogger()->addError("an unknown exception occurred while getting recurly informations from api, message=".$e->getMessage());
			throw $e;
		}
		//provider
		$provider = ProviderDAO::getProviderByName('recurly');
		if($provider == NULL) {
			$msg = "provider named 'recurly' not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$user = UserDAO::getUserByUserProviderUuid($provider->getId(), $customer_provider_uuid);
		if($user == NULL) {
			$msg = 'searching user with customer_provider_uuid='.$customer_provider_uuid.' failed, no user found';
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
		$recurlyTransactionsHandler = new RecurlyTransactionsHandler();
		if(isset($api_refund->original_transaction)) {
			$recurlyTransactionsHandler->createOrUpdateChargeFromProvider($user, $userOpts, $api_customer, $api_refund->original_transaction->get(), 'hook');
		} else {
			$recurlyTransactionsHandler->createOrUpdateRefundFromProvider($user, $userOpts, $api_customer, $api_refund, NULL, 'hook');
		}
		
		
		config::getLogger()->addInfo('Processing recurly hook refund, notification_type='.$notification->type.' done successfully');
	}
}

?>