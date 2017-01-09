<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../subscriptions/BraintreeSubscriptionsHandler.php';
require_once __DIR__ . '/../transactions/BraintreeTransactionsHandler.php';

class BraintreeWebHooksHandler {
	
	protected $provider = NULL;
	
	public function __construct() {
		$this->provider = ProviderDAO::getProviderByName('braintree');
	}
	
	public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing braintree webHook with id=".$billingsWebHook->getId()."...");
			//
			$post_data_as_json = $billingsWebHook->getPostData();
			$post_data_as_array = json_decode($post_data_as_json, true);
			//
			$notification = Braintree\WebhookNotification::parse($post_data_as_array['bt_signature'], $post_data_as_array['bt_payload']);
			$this->doProcessNotification($notification, $update_type, $billingsWebHook->getId());
			config::getLogger()->addInfo("processing braintree webHook with id=".$billingsWebHook->getId()." done successfully");
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing braintree webHook with id=".$billingsWebHook->getId().", message=".$e->getMessage();
			config::getLogger()->addError("processing braintree webHook with id=".$billingsWebHook->getId()." failed : ". $msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	private function doProcessNotification(Braintree\WebhookNotification $notification, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing braintree hook notification...');
		switch($notification->kind) {
			case Braintree\WebhookNotification::SUBSCRIPTION_CANCELED :
			case Braintree\WebhookNotification::SUBSCRIPTION_CHARGED_SUCCESSFULLY :
			case Braintree\WebhookNotification::SUBSCRIPTION_CHARGED_UNSUCCESSFULLY :
			case Braintree\WebhookNotification::SUBSCRIPTION_EXPIRED :
			case braintree\WebhookNotification::SUBSCRIPTION_TRIAL_ENDED :
			case Braintree\WebhookNotification::SUBSCRIPTION_WENT_ACTIVE :
			case Braintree\WebhookNotification::SUBSCRIPTION_WENT_PAST_DUE :
				$this->doProcessSubscription($notification, $update_type, $updateId);
				break;
			default :
				config::getLogger()->addWarning('notification kind : '. $notification->kind. ' is not yet implemented');
				break;
		}
		switch($notification->kind) {
			case Braintree\WebhookNotification::SUBSCRIPTION_CHARGED_SUCCESSFULLY :
			case Braintree\WebhookNotification::SUBSCRIPTION_CHARGED_UNSUCCESSFULLY :
				$this->doProcessCharge($notification, $update_type, $updateId);
				break;
		}
		config::getLogger()->addInfo('Processing braintree hook notification done successfully');
	}
	
	private function doProcessSubscription(Braintree\WebhookNotification $notification, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing braintree hook subscription, notification_kind='.$notification->kind.'...');
		//
		Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
		Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
		Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
		Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
		//
		$subscription_provider_uuid = $notification->subscription->id;
		config::getLogger()->addInfo('Processing braintree hook subscription, subscription_provider_uuid='.$subscription_provider_uuid);
		//
		try {
			//
			$api_subscription = Braintree\Subscription::find($subscription_provider_uuid);
			//
		} catch (Braintree\Exception\NotFound $e) {
			config::getLogger()->addError("a not found exception occurred while getting braintree subscription with subscription_provider_uuid=".$subscription_provider_uuid." from api, message=".$e->getMessage());
			throw $e;
		} catch (Exception $e) {
			config::getLogger()->addError("an unknown exception occurred while getting braintree subscription with subscription_provider_uuid=".$subscription_provider_uuid." from api, message=".$e->getMessage());
			throw $e;
		}
		//plan
		$plan_uuid = $api_subscription->planId;
		if($plan_uuid == NULL) {
			$msg = "plan uuid not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$plan = PlanDAO::getPlanByUuid($this->provider->getId(), $plan_uuid);
		if($plan == NULL) {
			$msg = "plan with uuid=".$plan_uuid." not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
		//internalPlan
		$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($plan->getId()));
		if($internalPlan == NULL) {
			$msg = "plan with uuid=".$plan_uuid." for provider ".$this->provider->getName()." is not linked to an internal plan";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
		//user
		$paymentMethod = Braintree\PaymentMethod::find($api_subscription->paymentMethodToken);
		$customerId = $paymentMethod->customerId;
		config::getLogger()->addInfo('searching user with userProviderUuid='.$customerId.'...');
		$user = UserDAO::getUserByUserProviderUuid($this->provider->getId(), $customerId);
		if($user == NULL) {
			$msg = 'searching user with userProviderUuid='.$customerId.' failed, no user found';
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		$db_subscription = $this->getDbSubscriptionByUuid($db_subscriptions, $subscription_provider_uuid);
		$braintreeSubscriptionsHandler = new BraintreeSubscriptionsHandler($this->provider);
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
				//$db_subscription = $braintreeSubscriptionsHandler->createDbSubscriptionFromApiSubscription($user, $userOpts, $this->provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $update_type, $updateId);
			} else {
				//UPDATE
				$db_subscription = $braintreeSubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, $userOpts, $this->provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, $update_type, $updateId);
			}
			//COMMIT
			pg_query("COMMIT");
		} catch(Exception $e) {
			pg_query("ROLLBACK");
			throw $e;
		}
		//
		config::getLogger()->addInfo('Processing braintree hook subscription, notification_kind='.$notification->kind.' done successfully');
	}
	
	private function getDbSubscriptionByUuid(array $db_subscriptions, $subUuid) {
		foreach ($db_subscriptions as $db_subscription) {
			if($db_subscription->getSubUid() == $subUuid) {
				return($db_subscription);
			}
		}
		return(NULL);
	}
	
	private function doProcessCharge(Braintree\WebhookNotification $notification, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing braintree hook charge, notification_kind='.$notification->kind.'...');
		//
		Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
		Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
		Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
		Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
		//
		$subscription_provider_uuid = $notification->subscription->id;
		config::getLogger()->addInfo('Processing braintree hook charge, subscription_provider_uuid='.$subscription_provider_uuid);
		//
		$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($this->provider->getId(), $subscription_provider_uuid);
		if($db_subscription == NULL) {
			$msg = 'searching subscription with subscription_provider_uuid='.$subscription_provider_uuid.' failed, no subscription found';
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
		try {
			//
			$api_subscription = Braintree\Subscription::find($subscription_provider_uuid);
			//
		} catch (Braintree\Exception\NotFound $e) {
			config::getLogger()->addError("a not found exception occurred while getting braintree subscription with subscription_provider_uuid=".$subscription_provider_uuid." from api, message=".$e->getMessage());
			throw $e;
		} catch (Exception $e) {
			config::getLogger()->addError("an unknown exception occurred while getting braintree subscription with subscription_provider_uuid=".$subscription_provider_uuid." from api, message=".$e->getMessage());
			throw $e;
		}
		if(count($api_subscription->transactions) > 0) {
			$moreRecentTransaction = $api_subscription->transactions[0];
			$braintreeTransactionsHandler = new BraintreeTransactionsHandler($this->provider);
			$db_transaction = $braintreeTransactionsHandler->createOrUpdateChargeFromProvider($user, $userOpts, $moreRecentTransaction, $update_type);
		}
		//
		config::getLogger()->addInfo('Processing braintree hook charge, notification_kind='.$notification->kind.' done successfully');
	}
	
}

?>