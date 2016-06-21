<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';

class AfrSubscriptionsHandler extends SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_provider_uuid, BillingInfoOpts $billingInfoOpts, BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("afr subscription creation...");
			//pre-requisite
			checkSubOptsArray($subOpts->getOpts(), 'afr');
			if(isset($subscription_provider_uuid)) {
				$msg = "unsupported feature for provider named afr, subscriptionProviderUuid has NOT to be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$couponCode = $subOpts->getOpts()['couponCode'];
			$coupon = CouponDAO::getCoupon($provider->getId(), $couponCode);
			if($coupon == NULL) {
				$msg = "coupon : code=".$couponCode." NOT FOUND";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_CODE_NOT_FOUND);				
			}
			$couponProviderPlan = PlanDAO::getPlanById($coupon->getProviderPlanId());
			if($couponProviderPlan == NULL) {
				$msg = "unknown coupon plan with id : ".$coupon->getProviderPlanId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);				
			}
			$couponInternalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($couponProviderPlan->getId()));
			if($couponInternalPlan == NULL) {
				$msg = "coupon plan with uuid=".$couponProviderPlan->getPlanUuid()." for provider afr is not linked to an internal plan";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);		
			}
			if($internalPlan->getId() != $couponInternalPlan->getId()) {
				$msg = "coupon : code=".$couponCode." cannot be used with internalPlan with uuid=".$internalPlan->getInternalPlanUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);						
			}
			if($coupon->getStatus() == 'redeemed') {
				$msg = "coupon : code=".$couponCode." already redeemed";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);			
			}
			if($coupon->getStatus() == 'expired') {
				$msg = "coupon : code=".$couponCode." expired";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($coupon->getStatus() != 'waiting') {
				$msg = "coupon : code=".$couponCode." cannot be used";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//OK
			$sub_uuid = guid();
			config::getLogger()->addInfo("afr subscription creation done successfully, afr_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a afr subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr subscription creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a afr subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($sub_uuid);
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, $sub_uuid, $update_type, $updateId) {
		$api_subscription = new BillingsSubscription();
		$api_subscription->setSubUid($sub_uuid);
		$api_subscription->setSubStatus('active');
		$start_date = new DateTime();
		$start_date->setTimezone(new DateTimeZone(config::$timezone));
		$api_subscription->setSubActivatedDate($start_date);
		$api_subscription->setSubPeriodStartedDate($start_date);
		$end_date = NULL;
		switch($internalPlan->getPeriodUnit()) {
			case PlanPeriodUnit::day :
				$end_date = clone $start_date;
				$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."D"));
				$end_date->setTime(23, 59, 59);//force the time to the end of the day
				break;
			case PlanPeriodUnit::month :
				$end_date = clone $start_date;
				$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."M"));
				$end_date->setTime(23, 59, 59);//force the time to the end of the day
				break;
			case PlanPeriodUnit::year :
				$end_date = clone $start_date;
				$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."Y"));
				$end_date->setTime(23, 59, 59);//force the time to the end of the day
				break;
			default :
				$msg = "unsupported periodUnit : ".$internalPlan->getPeriodUnit()->getValue();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
		$api_subscription->setSubPeriodEndsDate($end_date);
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingsSubscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("afr dbsubscription creation for userid=".$user->getId().", afr_subscription_uuid=".$api_subscription->getSubUid()."...");
		//CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid(guid());
		$db_subscription->setProviderId($provider->getId());
		$db_subscription->setUserId($user->getId());
		$db_subscription->setPlanId($plan->getId());
		$db_subscription->setSubUid($api_subscription->getSubUid());
		switch ($api_subscription->getSubStatus()) {
			case 'active' :
				$db_subscription->setSubStatus('active');
				break;
			case 'canceled' :
				$db_subscription->setSubStatus('canceled');
				break;
			case 'future' :
				$db_subscription->setSubStatus('future');
				break;
			case 'expired' :
				$db_subscription->setSubStatus('expired');
				break;
			default :
				$msg = "unknown subscription state : ".$api_subscription->getSubStatus();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		$db_subscription->setSubActivatedDate($api_subscription->getSubActivatedDate());
		$db_subscription->setSubCanceledDate($api_subscription->getSubCanceledDate());
		$db_subscription->setSubExpiresDate($api_subscription->getSubExpiresDate());
		$db_subscription->setSubPeriodStartedDate($api_subscription->getSubPeriodStartedDate());
		$db_subscription->setSubPeriodEndsDate($api_subscription->getSubPeriodEndsDate());
		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription->setDeleted('false');
		//?COUPON? JUST TEST IF READY TO USE (all other case seen before)
		$coupon = NULL;
		if(isset($subOpts)) {
			$couponCode = $subOpts->getOpts()['couponCode'];			
			$coupon = CouponDAO::getCoupon($provider->getId(), $couponCode);
			if($coupon == NULL) {
				$msg = "coupon : code=".$couponCode." NOT FOUND";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($coupon->getStatus() != 'waiting') {
				$msg = "coupon : code=".$couponCode." cannot be used";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
		}
		//NO MORE TRANSACTION (DONE BY CALLER)
		//<-- DATABASE -->
		$db_subscription = BillingsSubscriptionDAO::addBillingsSubscription($db_subscription);
		//SUB_OPTS
		if(isset($subOpts)) {
			$subOpts->setSubId($db_subscription->getId());
			$subOpts = BillingsSubscriptionOptsDAO::addBillingsSubscriptionOpts($subOpts);
		}
		//COUPON
		if(isset($coupon)) {
			$coupon->setStatus("redeemed");
			$coupon = CouponDAO::updateStatus($coupon);
			$coupon->setRedeemedDate(new DateTime());
			$coupon = CouponDAO::updateRedeemedDate($coupon);
			$coupon->setSubId($db_subscription->getId());
			$coupon = CouponDAO::updateSubId($coupon);
			$coupon->setUserId($user->getId());
			$coupon = CouponDAO::updateUserId($coupon);
		}
		//<-- DATABASE -->
		config::getLogger()->addInfo("afr dbsubscription creation for userid=".$user->getId().", afr_subscription_uuid=".$api_subscription->getSubUid()." done successfully, id=".$db_subscription->getId());
		return($db_subscription);
	}
	
	protected function doFillSubscription(BillingsSubscription $subscription = NULL) {
		if($subscription == NULL) {
			return;
		}
		$is_active = NULL;
		switch($subscription->getSubStatus()) {
			case 'pending_active' :
			case 'active' :
			case 'requesting_canceled' :
			case 'pending_canceled' :
			case 'canceled' :
			case 'pending_expired' :
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
				config::getLogger()->addWarning("afr dbsubscription unknown subStatus=".$subscription->getSubStatus().", afr_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;
		}
		$subscription->setIsActive($is_active);
		$subscription->setIsCancelable(false);
	}
	
	public function doExpireSubscription(BillingsSubscription $subscription, DateTime $expires_date, $is_a_request = true) {
		try {
			config::getLogger()->addInfo("afr subscription expiring...");
			if(
					$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				//
				if($subscription->getSubPeriodEndsDate() < $expires_date) {
					$subscription->setSubExpiresDate($expires_date);
					$subscription->setSubStatus("expired");
				} else {
					$msg = "cannot expire a subscription that has not ended yet";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
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
			config::getLogger()->addInfo("afr subscription expiring done successfully for afr_subscription_uuid=".$subscription->getSubUid());
			return($subscription);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while expiring a afr subscription for afr_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr subscription expiring failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring a afr subscription for afr_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr subscription expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		parent::doSendSubscriptionEvent($subscription_before_update, $subscription_after_update);
	}
	
}

?>