<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../libs/recurly/db/dbRecurly.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';

class WebHooksHander {
	
	public function __construct() {
	}
	
	public function doSaveWebHook($post_data) {
		try {
			$billingRecurlyWebHook = BillingRecurlyWebHookDAO::addBillingRecurlyWebHook($post_data);
			config::getLogger()->addInfo("post_data saved successfully, id=".$billingRecurlyWebHook->getId());
			return($billingRecurlyWebHook);
		} catch (Exception $e) {
			config::getLogger()->addError("an error occurred while saving post_data, message=" . $e->getMessage());
		}
	}
	
	public function doProcessWebHook($id, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing WebHook with id=".$id."...");
			$billingRecurlyWebHook = BillingRecurlyWebHookDAO::getBillingRecurlyWebHookById($id);
			BillingRecurlyWebHookDAO::updateProcessingStatusById($id, 'running');
			$billingRecurlyWebHookLog = BillingRecurlyWebHookLogDAO::addBillingRecurlyWebHookLog($id);
			$notification = new Recurly_PushNotification($billingRecurlyWebHook->getPostData());
			$this->doProcessNotification($notification, $update_type, $id);
			//
			BillingRecurlyWebHookDAO::updateProcessingStatusById($id, 'done');
			//
			$billingRecurlyWebHookLog->setProcessingStatus('done');
			$billingRecurlyWebHookLog->setMessage('');
			$billingRecurlyWebHookLog = BillingRecurlyWebHookLogDAO::updateBillingRecurlyWebHookLogProcessingStatus($billingRecurlyWebHookLog);
			//
			config::getLogger()->addInfo("processing WebHook with id=".$id." ended successully");
		} catch(Exception $e) {
			config::getLogger()->addError("an exception occurred while processing WebHook with id=".$id.", message=".$e->getMessage());
			BillingRecurlyWebHookDAO::updateProcessingStatusById($id, 'error');
			$billingRecurlyWebHookLog->setProcessingStatus('error');
			$billingRecurlyWebHookLog->setMessage($e->getMessage());
			$billingRecurlyWebHookLog = BillingRecurlyWebHookLogDAO::updateBillingRecurlyWebHookLogProcessingStatus($billingRecurlyWebHookLog);
		}
	}
	
	private function doProcessNotification(Recurly_PushNotification $notification, $update_type, $updateId) {
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
				config::getLogger()->addWarning('notification type : '. $notification->type. ' is not yet implemented.');
				break;
		}
	}
	
	private function doProcessSubscription(Recurly_PushNotification $notification, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing notification type '.$notification->type.'...');
		//
		Recurly_Client::$subdomain = RECURLY_API_SUBDOMAIN;
		Recurly_Client::$apiKey = RECURLY_API_KEY;
		//
		$subscription_uuid = self::getNodeByName($notification->subscription, 'uuid');
		//
		try {
			//
			$api_subscription = Recurly_Subscription::get($subscription_uuid);
			//
		} catch (Exception $e) {
			config::getLogger()->addError("an error occurred while getting subscription with uuid=".$subscription_uuid." from api, message=".$e->getMessage());
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
		//$account_code
		$account_code = $api_subscription->account->get()->account_code;
		if($account_code == NULL) {
			$msg = "account_code not found";
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		config::getLogger()->addInfo('searching user with account_code='.$account_code.'...');
		$user = UserDAO::getUserByAccountCode($account_code);
		if($user == NULL) {
			$msg = 'searching user with account_code='.$account_code.' failed, no user found';
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		//
		$subscription = SubscriptionDAO::getSubscriptionBySubUuid($provider->getId(), $account_code);
		if($subscription == NULL) {
			//CREATE
			$subscription = new Subscription();
			$subscription->setProviderId($provider->getId());
			$subscription->setUserId($user->getId());
			$subscription->setPlanId($plan->getId());
			$subscription->setSubUid($account_code);
			switch ($api_subscription->state) {
				case active :
					$subscription->setSubStatus('active');
					break;
				case canceled :
					$subscription->setSubStatus('canceled');
					break;
				case future :
					$subscription->setSubStatus('future');
					break;
				case expired :
					$subscription->setSubStatus('expired');
					break;
				default :
					$msg = "unknown subscription state : ".$api_subscription->state;
					config::getLogger()->addError($msg);
					throw new Exception($msg);
					//break;
			}
			$subscription->setSubActivatedDate($api_subscription->activated_at);
			$subscription->setSubCanceledDate($api_subscription->canceled_at);
			$subscription->setSubExpiresDate($api_subscription->expires_at);
			$subscription->setSubPeriodStartedDate($api_subscription->current_period_started_at);
			$subscription->setSubPeriodEndsDate($api_subscription->current_period_ends_at);
			switch ($api_subscription->collection_mode) {
				case 'automatic' :
					$subscription->setSubCollectionMode('automatic');
					break;
				case 'manual' :
					$subscription->setSubCollectionMode('manual');
					break;
				default :
					$subscription->setSubCollectionMode('manual');//it is the default says recurly
					break;
			}
			$subscription->setUpdateType($update_type);
			//
			$subscription->setUpdateId($updateId);
			$subscription->setDeleted('false');
			//
			$subscription = SubscriptionDAO::addSubscription($subscription);
		} else {
			//UPDATE
			//$subscription->setProviderId($provider->getId());
			//$subscription->setUserId($user->getId());
			$subscription->setPlanId($plan->getId());
			//$subscription->setSubUid($account_code);
			switch ($api_subscription->state) {
				case 'active' :
					$subscription->setSubStatus('active');
					break;
				case 'canceled' :
					$subscription->setSubStatus('canceled');
					break;
				case 'future' :
					$subscription->setSubStatus('future');
					break;
				case 'expired' :
					$subscription->setSubStatus('expired');
					break;
				default :
					$msg = "unknown subscription state : ".$api_subscription->state;
					config::getLogger()->addError($msg);
					throw new Exception($msg);
					//break;
			}
			$subscription->setSubActivatedDate($api_subscription->activated_at);
			$subscription->setSubCanceledDate($api_subscription->canceled_at);
			$subscription->setSubExpiresDate($api_subscription->expires_at);
			$subscription->setSubPeriodStartedDate($api_subscription->current_period_started_at);
			$subscription->setSubPeriodEndsDate($api_subscription->current_period_ends_at);
			switch ($api_subscription->collection_mode) {
				case 'automatic' :
					$subscription->setSubCollectionMode('automatic');
					break;
				case 'manual' :
					$subscription->setSubCollectionMode('manual');
					break;
				default :
					$subscription->setSubCollectionMode('manual');//it is the default says recurly
					break;
			}
			$subscription->setUpdateType($update_type);
			//
			$subscription->setUpdateId($updateId);
			//$subscription->setDeleted('false');
			//
			$subscription = SubscriptionDAO::updateSubscription($subscription);
		}
		//
		config::getLogger()->addInfo('Processing notification type '.$notification->type.' ended successfully');
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