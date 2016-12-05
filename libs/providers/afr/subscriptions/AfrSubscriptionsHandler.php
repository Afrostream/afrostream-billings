<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';

class AfrSubscriptionsHandler extends SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_billing_uuid, $subscription_provider_uuid, BillingInfo $billingInfo, BillingsSubscriptionOpts $subOpts) {
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
			$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponByCode($couponCode);
			if($internalCoupon == NULL) {
				$msg = "coupon : code=".$couponCode." NOT FOUND";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_CODE_NOT_FOUND);
			}
			//Check internalCoupon
			if($internalCoupon->getStatus() == 'redeemed') {
				$msg = "coupon : code=".$couponCode." already redeemed";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_REDEEMED);
			}
			if($internalCoupon->getStatus() == 'expired') {
				$msg = "coupon : code=".$couponCode." expired";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_EXPIRED);
			}
			if($internalCoupon->getStatus() == 'pending') {
				$msg = "coupon : code=".$couponCode." pending";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_PENDING);
			}
			if($internalCoupon->getStatus() != 'waiting') {
				$msg = "coupon : code=".$couponCode." cannot be used";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_NOT_READY);
			}
			//
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
				if($currentProviderCouponsCampaign->getProviderId() == $provider->getId()) {
					$providerCouponsCampaign = $currentProviderCouponsCampaign;
					$isProviderCompatible = true;
					break;
				}
			}
			if($isProviderCompatible == false) {
				//Exception
				$msg = "internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid()." is not associated with provider : ".$provider->getName();
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
			if($internalCouponsCampaign->getCouponType() == CouponCampaignType::sponsorship) {
				$userInternalCoupons = BillingUserInternalCouponDAO::getBillingUserInternalCouponsByInternalcouponsid($internalCoupon->getId());
				if(count($userInternalCoupons) > 0) {
					if(count($userInternalCoupons) > 1) {
						//exception
						$msg = "coupon : code=".$couponCode." used multiple times";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					//only one : take it
					$userInternalCoupon = $userInternalCoupons[0];
				} else {
					//exception
					$msg = $msg = "coupon : code=".$couponCode." was not correctly sponsored";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
			} else {
				$userInternalCoupons = BillingUserInternalCouponDAO::getBillingUserInternalCouponsByUserId($user->getId(), $internalCoupon->getId());
				if(count($userInternalCoupons) > 0) {
					if(count($userInternalCoupons) > 1) {
						//exception
						$msg = "coupon : code=".$couponCode." used multiple times";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					//only one : take it
					$userInternalCoupon = $userInternalCoupons[0];
				}
			}
			if($userInternalCoupon == NULL) {
				$userInternalCoupon = new BillingUserInternalCoupon();
				$userInternalCoupon->setInternalCouponsId($internalCoupon->getId());
				$userInternalCoupon->setCode($internalCoupon->getCode());
				$userInternalCoupon->setUuid(guid());
				$userInternalCoupon->setUserId($user->getId());
				$userInternalCoupon->setExpiresDate($internalCoupon->getExpiresDate());
			}
			//Check userInternalCoupon
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
			//Check userInternalCoupon (specifically for sponsorship)
			if($internalCouponsCampaign->getCouponType() == CouponCampaignType::sponsorship) {
				//
				if($userInternalCoupon->getUserId() == $user->getId()) {
					throw new BillingsException(new ExceptionType(ExceptionType::internal), 'self sponsorship is forbidden', ExceptionError::AFR_COUPON_SPS_SELF_FORBIDDEN);
				}
				//
				if($internalCouponsCampaign->getEmailsEnabled() == true) {
					$userInternalCouponOpts = BillingUserInternalCouponOptsDAO::getBillingUserInternalCouponOptsByUserInternalCouponId($userInternalCoupon->getId());
					$email = $userOpts->getOpt('email');
					$recipientEmail = $userInternalCouponOpts->getOpt('recipientEmail');
					if(strcasecmp($email, $recipientEmail) != 0) {
						$msg = "coupon cannot be used with another email";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::AFR_SUB_SPS_RECIPIENT_DIFFER);						
					}
				}
				$this->doCheckSponsoring($user);
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
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, $sub_uuid, $update_type, $updateId) {
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
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $billingInfo, $subscription_billing_uuid, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, BillingsSubscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("afr dbsubscription creation for userid=".$user->getId().", afr_subscription_uuid=".$api_subscription->getSubUid()."...");
		//CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid($subscription_billing_uuid);
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
		$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponByCode($couponCode);
		if($internalCoupon == NULL) {
			$msg = "coupon : code=".$couponCode." NOT FOUND";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//
		$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($internalCoupon->getInternalCouponsCampaignsId());
		if($internalCouponsCampaign == NULL) {
			$msg = "unknown internalCouponsCampaign with id : ".$internalCoupon->getInternalCouponsCampaignsId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//simple check
		if($internalCoupon->getStatus() != 'waiting') {
			$msg = "coupon : code=".$couponCode." cannot be used";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($internalCouponsCampaign->getCouponType() == CouponCampaignType::sponsorship) {
			$userInternalCoupons = BillingUserInternalCouponDAO::getBillingUserInternalCouponsByInternalcouponsid($internalCoupon->getId());
			if(count($userInternalCoupons) > 0) {
				if(count($userInternalCoupons) > 1) {
					//exception
					$msg = "coupon : code=".$couponCode." used multiple times";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				//only one : take it
				$userInternalCoupon = $userInternalCoupons[0];
			} else {
				//exception
				$msg = $msg = "coupon : code=".$couponCode." was not correctly sponsored";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
		} else {
			$userInternalCoupons = BillingUserInternalCouponDAO::getBillingUserInternalCouponsByUserId($user->getId(), $internalCoupon->getId());
			if(count($userInternalCoupons) > 0) {
				if(count($userInternalCoupons) > 1) {
					//exception
					$msg = "coupon : code=".$couponCode." used multiple times";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				//only one : take it
				$userInternalCoupon = $userInternalCoupons[0];
			}
		}
		if($userInternalCoupon == NULL) {
			$userInternalCoupon = new BillingUserInternalCoupon();
			$userInternalCoupon->setInternalCouponsId($internalCoupon->getId());
			$userInternalCoupon->setCode($internalCoupon->getCode());
			$userInternalCoupon->setUuid(guid());
			$userInternalCoupon->setUserId($user->getId());
			$userInternalCoupon->setExpiresDate($internalCoupon->getExpiresDate());
		}
		//simple check
		if($userInternalCoupon->getStatus() != 'waiting') {
			$msg = "coupon : code=".$couponCode." cannot be used";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//NO MORE TRANSACTION (DONE BY CALLER)
		//<-- DATABASE -->
		//BILLING_INFO (NOT MANDATORY)
		if(isset($billingInfo)) {
			$billingInfo = BillingInfoDAO::addBillingInfo($billingInfo);
			$db_subscription->setBillingInfoId($billingInfo->getId());
		}
		$db_subscription = BillingsSubscriptionDAO::addBillingsSubscription($db_subscription);
		//SUB_OPTS (MANDATORY)
		$subOpts->setSubId($db_subscription->getId());
		$subOpts = BillingsSubscriptionOptsDAO::addBillingsSubscriptionOpts($subOpts);
		//COUPON (MANDATORY)
		$now = new DateTime();
		//userInternalCouponOpts
		$userInternalCouponOpts = NULL;
		if($userInternalCoupon->getId() == NULL) {
			$userInternalCoupon = BillingUserInternalCouponDAO::addBillingUserInternalCoupon($userInternalCoupon);
			$userInternalCouponOpts = new BillingUserInternalCouponOpts();
			$userInternalCouponOpts->setUserInternalCouponId($userInternalCoupon->getId());
			$userInternalCouponOpts = BillingUserInternalCouponOptsDAO::addBillingUserInternalCouponOpts($userInternalCouponOpts);
		} else {
			$userInternalCouponOpts = BillingUserInternalCouponOptsDAO::getBillingUserInternalCouponOptsByUserInternalCouponId($userInternalCoupon->getId());
		}
		//userInternalCoupon
		$userInternalCoupon->setStatus("redeemed");
		$userInternalCoupon = BillingUserInternalCouponDAO::updateStatus($userInternalCoupon);
		$userInternalCoupon->setRedeemedDate($now);
		$userInternalCoupon = BillingUserInternalCouponDAO::updateRedeemedDate($userInternalCoupon);
		$userInternalCoupon->setSubId($db_subscription->getId());
		$userInternalCoupon = BillingUserInternalCouponDAO::updateSubId($userInternalCoupon);
		//internalCoupon
		if($internalCouponsCampaign->getGeneratedMode() == 'bulk') {
			$internalCoupon->setStatus("redeemed");
			$internalCoupon = BillingInternalCouponDAO::updateStatus($internalCoupon);
			$internalCoupon->setRedeemedDate($now);
			$internalCoupon = BillingInternalCouponDAO::updateRedeemedDate($internalCoupon);
		}
		//
		$recipientEmail = NULL;
		if(array_key_exists('email', $userOpts->getOpts())) {
			$recipientEmail = $userOpts->getOpts()['email'];
		}
		if(isset($recipientEmail)) {
			$current_coupon_opts_array = $userInternalCouponOpts->getOpts();
			if(array_key_exists('recipientEmail', $current_coupon_opts_array)) {
				BillingUserInternalCouponOptsDAO::updateBillingUserInternalCouponOptsKey($userInternalCoupon->getId(), 'recipientEmail', $recipientEmail);
			} else {
				BillingUserInternalCouponOptsDAO::addBillingUserInternalCouponsOptsKey($userInternalCoupon->getId(), 'recipientEmail', $recipientEmail);
			}
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
				if($subscription->getSubPeriodEndsDate() <= $expires_date) {
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
	
	protected function doCheckSponsoring(User $user) {
		$subscriptions = $this->doGetUserSubscriptionsByUserReferenceUuid($user->getUserReferenceUuid());
		foreach ($subscriptions as $subscription) {
			$userInternalCoupon = BillingUserInternalCouponDAO::getBillingUserInternalCouponBySubId($subscription->getId());
			if(isset($userInternalCoupon)) {
				$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponById($userInternalCoupon->getInternalCouponsId());
				if($internalCoupon == NULL) {
					$msg = "no internal coupon found linked to user coupon with uuid=".$userInternalCoupon->getUuid();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($internalCoupon->getInternalCouponsCampaignsId());
				if($internalCouponsCampaign == NULL) {
					$msg = "unknown internalCouponsCampaign with id : ".$internalCoupon->getInternalCouponsCampaignsId();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($internalCouponsCampaign->getCouponType() == 'sponsorship') {
					//EXCEPTION
					throw new BillingsException(new ExceptionType(ExceptionType::internal), 'user has already been sponsored', ExceptionError::AFR_SUB_SPS_RECIPIENT_ALREADY_SPONSORED);
				}
			}
		}
	}
	
}

?>