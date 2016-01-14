<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../subscriptions/RecurlySubscriptionsHandler.php';

class RecurlyWebHooksHandler {
	
	public function __construct() {
	}
		
	public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing recurly webHook with id=".$billingsWebHook->getId()."...");
			$notification = new Recurly_PushNotification($billingsWebHook->getPostData());
			$this->doProcessNotification($notification, $update_type, $billingsWebHook->getId());
			config::getLogger()->addInfo("processing recurly webHook with id=".$billingsWebHook->getId()." done successully");
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
				$this->doProcessSubscription($notification, $update_type, $updateId);
				break;
			case "updated_subscription_notification" :
				$this->doProcessSubscription($notification, $update_type, $updateId);
				break;
			case "canceled_subscription_notification":
				$this->doProcessSubscription($notification, $update_type, $updateId);
				break;
			case "expired_subscription_notification":
				$this->doProcessSubscription($notification, $update_type, $updateId);
				break;
			case "renewed_subscription_notification":
				$this->doProcessSubscription($notification, $update_type, $updateId);
				break;
			case "reactivated_account_notification":
				$this->doProcessSubscription($notification, $update_type, $updateId);
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
			throw new Exception($msg);
		}
		//plan
		$plan_uuid = $api_subscription->plan->plan_code;
		if($plan_uuid == NULL) {
			$msg = "plan uuid not found";
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		$plan = PlanDAO::getPlanByUuid($provider->getId(), $plan_uuid);
		if($plan == NULL) {
			$msg = "plan with uuid=".$plan_uuid." not found";
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
		//$account_code
		$account_code = $api_subscription->account->get()->account_code;
		if($account_code == NULL) {
			$msg = "account_code not found";
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($plan->getId()));
		$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
		config::getLogger()->addInfo('searching user with account_code='.$account_code.'...');
		$user = UserDAO::getUserByUserProviderUuid($provider->getId(), $account_code);
		if($user == NULL) {
			$msg = 'searching user with account_code='.$account_code.' failed, no user found';
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		$db_subscription = $this->getDbSubscriptionByUuid($db_subscriptions, $subscription_provider_uuid);
		$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
		//ADD OR UPDATE
		if($db_subscription == NULL) {
			$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." not found for user with provider_user_uuid=".$user->getUserProviderUuid();
			config::getLogger()->addError($msg);
			throw new Exception($msg);
			//DO NOT CREATE ANYMORE : race condition when creating from API + from the webhook
			//WAS :
			//CREATE
			//$db_subscription = $recurlySubscriptionsHandler->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $update_type, $updateId);
		} else {
			//UPDATE
			$db_subscription = $recurlySubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, $update_type, $updateId);
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
}

?>