<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/BillingsException.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';

class CelerySubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, Plan $plan, PlanOpts $planOpts, $subscription_provider_uuid, BillingInfoOpts $billingInfoOpts) {
		
	}
	
	public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
		
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, Plan $plan, PlanOpts $planOpts, $sub_uuid, $update_type, $updateId) {}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, Plan $plan, PlanOpts $planOpts, Recurly_Subscription $api_subscription, $update_type, $updateId) {}
	
	public function updateDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, Plan $plan, PlanOpts $planOpts, Recurly_Subscription $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {}
	
	private function getDbSubscriptionByUuid(array $db_subscriptions, $subUuid) {
		/*foreach ($db_subscriptions as $db_subscription) {
			if($db_subscription->getSubUid() == $subUuid) {
				return($db_subscription);
			}
		}*/
	}
	
	private function getApiSubscriptionByUuid($api_subscriptions, $subUuid) {
		/*foreach ($api_subscriptions as $api_subscription) {
			if($api_subscription->uuid == $subUuid) {
				return($api_subscription);
			}
		}*/
	}
	
	public function doFillSubscription(BillingsSubscription $subscription) {
		//TODO : later, is_active will be changed when a subscription is postponed
		$is_active = NULL;
		switch($subscription->getSubStatus()) {
			case 'active' :
				$is_active = 'yes';
				break;
			case 'canceled' :
				$is_active = 'yes';
				break;
			case 'future' :
				$is_active = 'no';
				break;
			case 'expired' :
				$is_active = 'no';
				break;
			default :
				$is_active = 'no';
				config::getLogger()->addWarning("celery dbsubscription unknown subStatus=".$subscription->getSubStatus().", celery_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;		
		}
		//check dates
		//
		$subscription->setIsActive($is_active);
	}
	
}

?>