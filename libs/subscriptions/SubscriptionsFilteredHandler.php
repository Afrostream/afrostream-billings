<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/SubscriptionsHandler.php';

class SubscriptionsFilteredHandler extends SubscriptionsHandler {
	
	public function __construct() {
		parent::__construct();
	}
	
	public function doGetOrCreateSubscription($user_billing_uuid, $internal_plan_uuid, $subscription_provider_uuid, array $billing_info_array, array $sub_opts_array) {
		$user = UserDAO::getUserByUserBillingUuid($user_billing_uuid);
		if($user == NULL) {
			$msg = "unknown user_billing_uuid : ".$user_billing_uuid;
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$subscriptions = $this->doGetUserSubscriptionsByUserReferenceUuid($user->getUserReferenceUuid());
		if(count($subscriptions) > 0) {
			//HACK / FIX : Remove check because of CASHWAY
			/*if($this->haveSubscriptionsWithStatus($subscriptions, 'future')) {
				$msg = "you already have a future subscription";
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_FUTURE_ALREADY_EXISTS);				
			}*/
			$lastSubscription = $subscriptions[0];
			if($lastSubscription->getIsActive() == 'yes') {
				//NC : DO NOT BLOCK UNTIL WE REALLY KNOW THAT SUBSCRIPTIONS AUTO-RENEW OR NOT (VERY SOON !!!)
				/*$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($lastSubscription->getPlanId()));
				if($internalPlan->getCycle() == PlanCycle::auto) {
					$msg = "you already have a subscription that's auto renew, you can't take a new subscription";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_AUTO_ALREADY_EXISTS);
				}*/
			}
			$sub_opts_array['startsAt'] = dbGlobal::toISODate($lastSubscription->getSubPeriodEndsDate());
		}
		return(parent::doGetOrCreateSubscription($user_billing_uuid, $internal_plan_uuid, $subscription_provider_uuid, $billing_info_array, $sub_opts_array));
	}
	
	private function haveSubscriptionsWithStatus(array $subscriptions, $status) {
		foreach ($subscriptions as $subscription) {
			if($subscription->getSubStatus() == $status) {
				return(true);
			}
		}
		return(false);
	}
	
}
?>