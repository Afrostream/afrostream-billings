<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../global/subscriptions/ProviderSubscriptionsHandler.php';
require_once __DIR__ . '/../../global/requests/ExpireSubscriptionRequest.php';

class CashwaySubscriptionsHandler extends ProviderSubscriptionsHandler {
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_billing_uuid, $subscription_provider_uuid, BillingInfo $billingInfo, BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("cashway subscription creation...");
			//pre-requisite
			checkSubOptsArray($subOpts->getOpts(), 'cashway');
			if(isset($subscription_provider_uuid)) {
				$msg = "unsupported feature for provider named cashway, subscriptionProviderUuid has NOT to be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$couponCode = $subOpts->getOpts()['couponCode'];
			$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponByCode($couponCode, $this->provider->getPlatformId());
			if($internalCoupon == NULL) {
				$msg = "coupon : code=".$couponCode." NOT FOUND";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_CODE_NOT_FOUND);				
			}
			$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($internalCoupon->getInternalCouponsCampaignsId());
			if($internalCouponsCampaign == NULL) {
				$msg = "unknown internalCouponsCampaign with id : ".$internalCoupon->getInternalCouponsCampaignsId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//Check compatibility
			$isProviderCompatible = false;
			$providerCouponsCampaign = NULL;
			$providerCouponsCampaigns = BillingProviderCouponsCampaignDAO::getBillingProviderCouponsCampaignsByInternalCouponsCampaignsId($internalCouponsCampaign->getId());
			foreach ($providerCouponsCampaigns as $currentProviderCouponsCampaign) {
				if($currentProviderCouponsCampaign->getProviderId() == $this->provider->getId()) {
					$providerCouponsCampaign = $currentProviderCouponsCampaign;
					$isProviderCompatible = true;
					break;
				}
			}
			if($isProviderCompatible == false) {
				//Exception
				$msg = "internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid()." is not associated with provider : ".$this->provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_PROVIDER_INCOMPATIBLE);
			}
			$billingInternalCouponsCampaignInternalPlans = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlansByInternalCouponsCampaignsId($internalCouponsCampaign->getId());
			if(count($billingInternalCouponsCampaignInternalPlans) == 0) {
				//Exception
				$msg = "no internalPlan associated to internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			} else if(count($billingInternalCouponsCampaignInternalPlans) == 1) {
				$billingInternalCouponsCampaignInternalPlan = $billingInternalCouponsCampaignInternalPlans[0];
				if($internalPlan->getId() != $billingInternalCouponsCampaignInternalPlan->getInternalPlanId()) {
					//Exception
					$msg = "coupon : code=".$couponCode." cannot be used with internalPlan with uuid=".$internalPlan->getInternalPlanUuid();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_INTERNALPLAN_INCOMPATIBLE);
				}
			} else {
				//Exception
				$msg = "only one internalPlan can be associated to internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$userInternalCoupon = NULL;
			$userInternalCoupons = BillingUserInternalCouponDAO::getBillingUserInternalCouponsByUserId($user->getId(), $internalCoupon->getId());
			if(count($userInternalCoupons) == 0) {
				//exception
				$msg = "coupon : code=".$couponCode." NOT FOUND FOR YOU";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_CODE_NOT_FOUND);
			} else {
				//TAKING FIRST (EQUALS LAST GENERATED)
				$userInternalCoupon = $userInternalCoupons[0];
			}
			//
			if($userInternalCoupon->getStatus() == 'redeemed') {
				$msg = "coupon : code=".$couponCode." already redeemed";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_REDEEMED);				
			}
			if($userInternalCoupon->getStatus() == 'expired') {
				$msg = "coupon : code=".$couponCode." expired";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_EXPIRED);
			}
			if($userInternalCoupon->getStatus() == 'pending') {
				$msg = "coupon : code=".$couponCode." pending";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_PENDING);
			}
			if($userInternalCoupon->getStatus() != 'waiting') {
				$msg = "coupon : code=".$couponCode." cannot be used";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_NOT_READY);
			}
			if($userInternalCoupon->getSubId() != NULL) {
				$msg = "coupon : code=".$couponCode." is already linked to another subscription";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_ALREADY_LINKED);
			}
			//OK
			$sub_uuid = guid();
			config::getLogger()->addInfo("cashway subscription creation done successfully, cashway_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a cashway subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway subscription creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a cashway subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($sub_uuid);
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(
			User $user, 
			UserOpts $userOpts, 
			InternalPlan $internalPlan = NULL, 
			InternalPlanOpts $internalPlanOpts = NULL, 
			Plan $plan = NULL, 
			PlanOpts $planOpts = NULL, 
			BillingsSubscriptionOpts $subOpts = NULL, 
			BillingInfo $billingInfo = NULL, 
			$subscription_billing_uuid, 
			$sub_uuid, 
			$update_type, 
			$updateId) {
		$api_subscription = new BillingsSubscription();
		$api_subscription->setCreationDate(new DateTime());
		$api_subscription->setSubUid($sub_uuid);
		$api_subscription->setSubStatus('future');
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $billingInfo, $subscription_billing_uuid, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, BillingsSubscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("cashway dbsubscription creation for userid=".$user->getId().", cashway_subscription_uuid=".$api_subscription->getSubUid()."...");
		//CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid($subscription_billing_uuid);
		$db_subscription->setProviderId($this->provider->getId());
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
		$db_subscription->setDeleted(false);
		//?COUPON? JUST TEST IF READY TO USE (all other case seen before)
		$internalCoupon = NULL;
		$userInternalCoupon = NULL;
		if($subOpts == NULL) {
			//Exception
			$msg = "field 'subOpts' is missing";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$couponCode = $subOpts->getOpts()['couponCode'];
		$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponByCode($couponCode, $this->provider->getPlatformId());
		if($internalCoupon == NULL) {
			$msg = "coupon : code=".$couponCode." NOT FOUND";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$userInternalCoupons = BillingUserInternalCouponDAO::getBillingUserInternalCouponsByUserId($user->getId(), $internalCoupon->getId());
		if(count($userInternalCoupons) == 0) {
			//exception
			$msg = "coupon : code=".$couponCode." NOT FOUND FOR YOU";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		} else {
			//TAKING FIRST (EQUALS LAST GENERATED)
			$userInternalCoupon = $userInternalCoupons[0];
		}
		if($userInternalCoupon->getStatus() != 'waiting') {
			$msg = "coupon : code=".$couponCode." cannot be used";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//ADD CouponCode URL
		$subOpts->setOpt('couponCodeUrl', getEnv('CASHWAY_COUPON_URL').$couponCode.".h");
		//NO MORE TRANSACTION (DONE BY CALLER)
		//<-- DATABASE -->
		//BILLING_INFO (NOT MANDATORY)
		if(isset($billingInfo)) {
			$billingInfo = BillingInfoDAO::addBillingInfo($billingInfo);
			$db_subscription->setBillingInfoId($billingInfo->getId());
		}
		$db_subscription->setPlatformId($this->provider->getPlatformId());
		$db_subscription = BillingsSubscriptionDAO::addBillingsSubscription($db_subscription);
		//SUB_OPTS (MANDATORY)
		$subOpts->setSubId($db_subscription->getId());
		$subOpts = BillingsSubscriptionOptsDAO::addBillingsSubscriptionOpts($subOpts);
		//COUPON (MANDATORY)
		//userInternalCoupon
		$userInternalCoupon->setStatus("pending");
		$userInternalCoupon = BillingUserInternalCouponDAO::updateStatus($userInternalCoupon);
		$userInternalCoupon->setSubId($db_subscription->getId());
		$userInternalCoupon = BillingUserInternalCouponDAO::updateSubId($userInternalCoupon);
		//internalCoupon
		$internalCoupon->setStatus("pending");
		$internalCoupon = BillingInternalCouponDAO::updateStatus($internalCoupon);
		//<-- DATABASE -->
		config::getLogger()->addInfo("cashway dbsubscription creation for userid=".$user->getId().", cashway_subscription_uuid=".$api_subscription->getSubUid()." done successfully, id=".$db_subscription->getId());
		return($this->doFillSubscription($db_subscription));
	}
	
	public function doFillSubscription(BillingsSubscription $subscription = NULL) {
		$subscription = parent::doFillSubscription($subscription);
		if($subscription == NULL) {
			return NULL;
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
				config::getLogger()->addWarning("cashway dbsubscription unknown subStatus=".$subscription->getSubStatus().", cashway_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;
		}
		$subscription->setIsActive($is_active);
		$array_status_expirable = ['active', 'canceled'];
		if(in_array($subscription->getSubStatus(), $array_status_expirable)) {
			$subscription->setIsExpirable(true);
		} else {
			$subscription->setIsExpirable(false);
		}
		return($subscription);
	}
	
	public function updateDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscription $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("cashway dbsubscription update for userid=".$user->getId().", cashway_subscription_uuid=".$api_subscription->getSubUid().", id=".$db_subscription->getId()."...");
		//UPDATE
		$db_subscription_before_update = clone $db_subscription;
		//
		//$db_subscription->setProviderId($this->provider->getId());//STATIC
		//$db_subscription->setUserId($user->getId());//STATIC
		$db_subscription->setPlanId($plan->getId());
		$db_subscription = BillingsSubscriptionDAO::updatePlanId($db_subscription);
		//$db_subscription->setSubUid($subscription_uuid);//STATIC
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
		$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
		//
		$db_subscription->setSubActivatedDate($api_subscription->getSubActivatedDate());
		$db_subscription = BillingsSubscriptionDAO::updateSubActivatedDate($db_subscription);
		//
		$db_subscription->setSubCanceledDate($api_subscription->getSubCanceledDate());
		$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
		//
		$db_subscription->setSubExpiresDate($api_subscription->getSubExpiresDate());
		$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
		//
		$start_date = $api_subscription->getSubPeriodStartedDate();
		$db_subscription->setSubPeriodStartedDate($start_date);
		
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
		//
		$db_subscription->setSubPeriodStartedDate($api_subscription->getSubPeriodStartedDate());
		$db_subscription = BillingsSubscriptionDAO::updateSubStartedDate($db_subscription);
		//
		$db_subscription->setSubPeriodEndsDate($api_subscription->getSubPeriodEndsDate());
		$db_subscription = BillingsSubscriptionDAO::updateSubEndsDate($db_subscription);
		//
		$db_subscription->setUpdateType($update_type);
		$db_subscription = BillingsSubscriptionDAO::updateUpdateType($db_subscription);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription = BillingsSubscriptionDAO::updateUpdateId($db_subscription);
		//$db_subscription->setDeleted(false);//STATIC
		//
		$this->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
		//
		config::getLogger()->addInfo("cashway dbsubscription update for userid=".$user->getId().", cashway_subscription_uuid=".$api_subscription->getSubUid().", id=".$db_subscription->getId()." done successfully");
		return($this->doFillSubscription($db_subscription));
	}
	
	public function doExpireSubscription(BillingsSubscription $subscription, ExpireSubscriptionRequest $expireSubscriptionRequest) {
		try {
			config::getLogger()->addInfo("cashway subscription expiring...");
			if(
				$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				if($expireSubscriptionRequest->getIsRefundEnabled() == true) {
					$msg = "cannot expire and refund a ".$this->provider->getName()." subscription";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_EXP_REFUND_UNSUPPORTED);
				}
				//
				$expiresDate = $expireSubscriptionRequest->getExpiresDate();
				//
				if($expireSubscriptionRequest->getOrigin() == 'api') {
					if(!in_array($subscription->getSubStatus(), ['active', 'canceled'])) {
						//exception
						$msg = "cannot expire a ".$this->provider->getName()." subscription that has not started or has not been canceled";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
				}
				if(in_array($subscription->getSubStatus(), ['active', 'canceled'])) {
					if($subscription->getSubPeriodEndsDate() > $expiresDate) {
						if($expireSubscriptionRequest->getForceBeforeEndsDate() == false) {
							$msg = "cannot expire a ".$this->provider->getName()." subscription that has not ended yet";
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_EXP_BEFORE_ENDS_DATE_UNSUPPORTED);
						}
					}
				}
				if($expireSubscriptionRequest->getOrigin() == 'api') {
					if($subscription->getSubCanceledDate() == NULL) {
						$subscription->setSubCanceledDate($expiresDate);
					}
				}
				$subscription->setSubExpiresDate($expiresDate);
				$subscription->setSubStatus("expired");
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					BillingsSubscriptionDAO::updateSubCanceledDate($subscription);
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
			config::getLogger()->addInfo("cashway subscription expiring done successfully for cashway_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while expiring a cashway subscription for cashway_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway subscription expiring failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring a cashway subscription for cashway_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway subscription expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
	
	public function doDeleteSubscription(BillingsSubscription $subscription, DeleteSubscriptionRequest $deleteSubscriptionRequest) {
		try {
			config::getLogger()->addInfo("cashway subscription deleting...");
			if(
					$subscription->getDeleted() == true
			)
			{
				//nothing todo : already done or in process
			} else {
				//
				$subscription->setDeleted(true);
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					BillingsSubscriptionDAO::updateDeleted($subscription);
					//COMMIT
					pg_query("COMMIT");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			}
			//
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("cashway subscription deleting done successfully for cashway_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while deleting a cashway subscription for cashway_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway subscription deleting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while deleting a cashway subscription for cashway_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway subscription deleting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
		
	public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
		$msg = "unsupported feature - update user subscriptions - for provider named : ".$this->provider->getName();
		config::getLogger()->addWarning($msg);//Just warn for the moment
		//throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
}

?>