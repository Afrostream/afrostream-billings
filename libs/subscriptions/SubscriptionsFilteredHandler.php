<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/SubscriptionsHandler.php';

class SubscriptionsFilteredHandler extends SubscriptionsHandler {
	
	public function __construct() {
		parent::__construct();
	}
	
	public function doGetOrCreateSubscription(GetOrCreateSubscriptionRequest $getOrCreateSubscriptionRequest) {
		$user = UserDAO::getUserByUserBillingUuid($getOrCreateSubscriptionRequest->getUserBillingUuid());
		if($user == NULL) {
			$msg = "unknown user_billing_uuid : ".$getOrCreateSubscriptionRequest->getUserBillingUuid();
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
				//NC : CAN BLOCK NOW  (WAS DO NOT BLOCK UNTIL WE REALLY KNOW THAT SUBSCRIPTIONS AUTO-RENEW OR NOT (VERY SOON !!!))
				$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($lastSubscription->getPlanId()));
				if($internalPlan->getCycle() == PlanCycle::auto) {
					$msg = "you already have an active subscription that's auto renew, you can't take a new subscription";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_AUTO_ALREADY_EXISTS);
				} else {
					$msg = "you already have an active subscription, you can't take a new subscription";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_ALREADY_EXISTS);					
				}
			}
			$now = new DateTime();
			$lastDate = NULL;
			if($lastSubscription->getSubStatus() == 'expired') {
				$lastDate = $lastSubscription->getSubExpiresDate();
			} else {
				$lastDate = $lastSubscription->getSubPeriodEndsDate();
			}
			if(isset($lastDate)) {
				if($lastDate < $now) {
					$lastDate = NULL;//lastDate is in the PAST => NOT NEEDED
				}
			}
			if(isset($lastDate)) {
				$sub_opts_array['startsAt'] = dbGlobal::toISODate($lastDate);
			}
		}
		return(parent::doGetOrCreateSubscription($getOrCreateSubscriptionRequest));
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