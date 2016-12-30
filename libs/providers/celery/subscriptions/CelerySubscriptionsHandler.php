<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../global/subscriptions/ProviderSubscriptionsHandler.php';
require_once __DIR__ . '/../../global/requests/ExpireSubscriptionRequest.php';

class CelerySubscriptionsHandler extends ProviderSubscriptionsHandler {
	
	public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
		
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, $internalPlan, $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, $sub_uuid, $update_type, $updateId) {}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, Celery_Subscription $api_subscription, $update_type, $updateId) {}
	
	public function updateDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, Celery_Subscription $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {}
	
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

	public function doExpireSubscription(BillingsSubscription $subscription, ExpireSubscriptionRequest $expireSubscriptionRequest) {
		try {
			config::getLogger()->addInfo("celery subscription expiring...");
			if(
				$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				//
				$expiresDate = $expireSubscriptionRequest->getExpiresDate();
				//
				if($subscription->getSubPeriodEndsDate() > $expiresDate) {
					if($expireSubscriptionRequest->getIsForced() == false) {
						$msg = "cannot expire a subscription that has not ended yet";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
				}
				$subscription->setSubExpiresDate($expiresDate);
				$subscription->setSubStatus("expired");
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					BillingsSubscriptionDAO::updateSubExpiresDate($subscription);
					BillingsSubscriptionDAO::updateSubStatus($subscription);
					//COMMIT
					pg_query("COMMIT");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			}
			//
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("celery subscription expiring done successfully for celery_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while expiring a celery subscription for celery_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("celery subscription expiring failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring a celery subscription for celery_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("celery subscription expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
	
	protected function doFillSubscription(BillingsSubscription $subscription = NULL) {
		$subscription = parent::doFillSubscription($subscription);
		if($subscription == NULL) {
			return NULL;
		}
		$is_active = NULL;
		switch($subscription->getSubStatus()) {
			case 'active' :
			case 'canceled' :
				$now = new DateTime();
				//check dates
				if(
						($now < $subscription->getSubPeriodEndsDate())
								&&
						($now >= $subscription->getSubPeriodStartedDate())
				) {
					//inside the period
					$is_active = 'yes';
				} else {
					//outside the period
					$is_active = 'no';
				}
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
		//done
		$subscription->setIsActive($is_active);
		$subscription->setIsCancelable(false);
		return($subscription);
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		parent::doSendSubscriptionEvent($subscription_before_update, $subscription_after_update);
	}
	
}

?>