<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../global/subscriptions/ProviderSubscriptionsHandler.php';

class RecurlySubscriptionsHandler extends ProviderSubscriptionsHandler {
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_billing_uuid, $subscription_provider_uuid, BillingInfo $billingInfo, BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("recurly subscription creation...");
			if(isset($subscription_provider_uuid)) {
				checkSubOptsArray($subOpts->getOpts(), 'recurly', 'get');
				//** in recurly : user subscription is pre-created **/
				//
				Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
				Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
				//
				$subscriptions = Recurly_SubscriptionList::getForAccount($user->getUserProviderUuid());
				$found = false;
				foreach ($subscriptions as $subscription) {
					if($subscription->uuid == $subscription_provider_uuid) {
						$found = true;
						break;
					}
				}
				if(!$found) {
					$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." not found for user with provider_user_uuid=".$user->getUserProviderUuid();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
			} else {
				checkSubOptsArray($subOpts->getOpts(), 'recurly', 'create');
				//** in recurly : user subscription is NOT pre-created **/
				Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
				Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
				//
				$subscription = new Recurly_Subscription();
				$subscription->plan_code = $plan->getPlanUuid();
				$subscription->currency = $internalPlan->getCurrency();
				
				$account = Recurly_Account::get($user->getUserProviderUuid());
			
				$billing_info = new Recurly_BillingInfo();
				if(!array_key_exists('customerBankAccountToken', $subOpts->getOpts())) {
					$msg = "subOpts field 'customerBankAccountToken' field is missing";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$billing_info->token_id = $subOpts->getOpts()['customerBankAccountToken'];
				
				$account->billing_info = $billing_info;
				$subscription->account = $account;
				//couponCode
				if(array_key_exists('couponCode', $subOpts->getOpts())) {
					$couponCode = $subOpts->getOpts()['couponCode'];
					if(strlen($couponCode) > 0) {
						$couponsInfos = $this->getCouponInfos($couponCode, $provider, $user, $internalPlan);
						$subscription->coupon_code = $couponsInfos['providerCouponsCampaign']->getExternalUuid();
					}
				}
				//startsAt
				if(array_key_exists('startsAt', $subOpts->getOpts())) {
					$startsAt = $subOpts->getOpts()['startsAt'];
					if(strlen($startsAt) > 0) {
						$subscription->starts_at = $startsAt;
					}
				}
				
				$subscription->create();
				//<-- POSTPONING -->
				if(getenv('RECURLY_POSTPONE_ACTIVATED') == 1) {
					if($subscription->trial_ends_at == NULL) {
						//only postpone renewable and montlhy plans
						if($internalPlan->getCycle() == 'auto' && $internalPlan->getPeriodUnit() == 'month') {
							$interval = 0;
							$period_ends_date_ref = clone $subscription->current_period_ends_at;
							$period_ends_date_new = clone $subscription->current_period_ends_at;
							$dayOfMonth = $period_ends_date_ref->format('j');
							
							if($dayOfMonth >= 1 && $dayOfMonth <= getEnv('RECURLY_POSTPONE_LIMIT_IN')) {
								config::getLogger()->addInfo("recurly subscription creation...RECURLY_POSTPONE_LIMIT_IN limit");
								$interval = getEnv('RECURLY_POSTPONE_TO') - $dayOfMonth; 
							} else if($dayOfMonth >= getEnv('RECURLY_POSTPONE_LIMIT_OUT')) {
								config::getLogger()->addInfo("recurly subscription creation...RECURLY_POSTPONE_LIMIT_OUT limit");
								$lastDayOfMonth = $period_ends_date_ref->format('t');
								$interval = getEnv('RECURLY_POSTPONE_TO') + ($lastDayOfMonth - $dayOfMonth);
							} else {
								//nothing to do
								config::getLogger()->addInfo("recurly subscription creation...no RECURLY_POSTPONE_LIMIT");
							}
							if($interval > 0) {
								$period_ends_date_new->add(new DateInterval("P".$interval."D"));
								config::getLogger()->addInfo("recurly subscription creation...postponing from : ".dbGlobal::toISODate($period_ends_date_ref)." to ".dbGlobal::toISODate($period_ends_date_new)."...");
								$subscription->postpone(dbGlobal::toISODate($period_ends_date_new));
								config::getLogger()->addInfo("recurly subscription creation...postponing from : ".dbGlobal::toISODate($period_ends_date_ref)." to ".dbGlobal::toISODate($period_ends_date_new)." done successfullly");
							} else {
								config::getLogger()->addInfo("recurly subscription creation...no postpone needed");
							}
						} else {
							config::getLogger()->addInfo("recurly subscription creation...no postpone there");
						}
					} else {
						config::getLogger()->addInfo("recurly subscription creation...no postpone, trial activated");
					}
				} else {
					config::getLogger()->addInfo("recurly subscription creation...postpone not activated");
				}
				//<-- POSTPONING -->
			}
			//
			$sub_uuid = $subscription->uuid;
			config::getLogger()->addInfo("recurly subscription creation done successfully, recurly_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a recurly subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription creation failed : ".$msg);
			throw $e;
		} catch(Recurly_NotFoundError $e) {
			$msg = "a not found error exception occurred while creating a recurly subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch (Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while creating a recurly subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a recurly subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($sub_uuid);
	}
	
	public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
		config::getLogger()->addInfo("recurly dbsubscriptions update for userid=".$user->getId()."...");
		//
		Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
		Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
		//
		$provider = ProviderDAO::getProviderById($user->getProviderId());
		//
		if($provider == NULL) {
			$msg = "unknown provider id : ".$user->getProviderId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		try {
			$api_subscriptions = Recurly_SubscriptionList::getForAccount($user->getUserProviderUuid());
		} catch (Recurly_NotFoundError $e) {
			$msg = "an account not found exception occurred while getting subscriptions for user_provider_uuid=".$user->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting subscriptions for user_provider_uuid=".$user->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		//ADD OR UPDATE
		foreach ($api_subscriptions as $api_subscription) {
			//plan
			$plan_uuid = $api_subscription->plan->plan_code;
			$plan = PlanDAO::getPlanByUuid($provider->getId(), $plan_uuid);
			if($plan == NULL) {
				$msg = "plan with uuid=".$plan_uuid." not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
			$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($plan->getId()));
			if($internalPlan == NULL) {
				$msg = "plan with uuid=".$plan_uuid." for provider ".$provider->getName()." is not linked to an internal plan";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
			$db_subscription = $this->getDbSubscriptionByUuid($db_subscriptions, $api_subscription->uuid);
			if($db_subscription == NULL) {
				//CREATE
				$db_subscription = $this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, NULL, NULL, guid(), $api_subscription, 'api', 0);
			} else {
				//UPDATE
				$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, 'api', 0);
			}
		}
		//DELETE UNUSED SUBSCRIPTIONS (DELETED FROM THIRD PARTY)
		foreach ($db_subscriptions as $db_subscription) {
			$api_subscription = $this->getApiSubscriptionByUuid($api_subscriptions, $db_subscription->getSubUid());
			if($api_subscription == NULL) {
				BillingsSubscriptionDAO::deleteBillingsSubscriptionById($db_subscription->getId());
			}
		}
		config::getLogger()->addInfo("recurly dbsubscriptions update for userid=".$user->getId()." done successfully");
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, $sub_uuid, $update_type, $updateId) {
		//
		Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
		Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
		//
		$api_subscription = Recurly_Subscription::get($sub_uuid);
		//
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $billingInfo, $subscription_billing_uuid, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, Recurly_Subscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("recurly dbsubscription creation for userid=".$user->getId().", recurly_subscription_uuid=".$api_subscription->uuid."...");
		//CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid($subscription_billing_uuid);
		$db_subscription->setProviderId($provider->getId());
		$db_subscription->setUserId($user->getId());
		$db_subscription->setPlanId($plan->getId());
		$db_subscription->setSubUid($api_subscription->uuid);
		switch ($api_subscription->state) {
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
				$msg = "unknown subscription state : ".$api_subscription->state;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		$db_subscription->setSubActivatedDate($api_subscription->activated_at);
		$db_subscription->setSubCanceledDate($api_subscription->canceled_at);
		$db_subscription->setSubExpiresDate($api_subscription->expires_at);
		$db_subscription->setSubPeriodStartedDate($api_subscription->current_period_started_at);
		$db_subscription->setSubPeriodEndsDate($api_subscription->current_period_ends_at);
		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription->setDeleted(false);
		//?COUPON?
		$couponsInfos = NULL;
		$couponCode = NULL;
		if(isset($subOpts)) {
			if(array_key_exists('couponCode', $subOpts->getOpts())) {
				$str = $subOpts->getOpts()['couponCode'];
				if(strlen($str) > 0) {
					$couponCode = $str;
				}
			}
		}
		if(isset($couponCode)) {
			$couponsInfos = $this->getCouponInfos($couponCode, $provider, $user, $internalPlan);
		}
		//NO MORE TRANSACTION (DONE BY CALLER)
		//<-- DATABASE -->
		//BILLING_INFO (NOT MANDATORY)
		if(isset($billingInfo)) {
			$billingInfo = BillingInfoDAO::addBillingInfo($billingInfo);
			$db_subscription->setBillingInfoId($billingInfo->getId());
		}
		$db_subscription = BillingsSubscriptionDAO::addBillingsSubscription($db_subscription);
		//SUB_OPTS (NOT MANDATORY)
		if(isset($subOpts)) {
			$subOpts->setSubId($db_subscription->getId());
			$subOpts = BillingsSubscriptionOptsDAO::addBillingsSubscriptionOpts($subOpts);
		}
		//COUPON (NOT MANDATORY)
		if(isset($couponsInfos)) {
			$userInternalCoupon = $couponsInfos['userInternalCoupon'];
			$internalCoupon = $couponsInfos['internalCoupon'];
			$internalCouponsCampaign = $couponsInfos['internalCouponsCampaign'];
			//
			$now = new DateTime();
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
		}
		//<-- DATABASE -->
		config::getLogger()->addInfo("recurly dbsubscription creation for userid=".$user->getId().", recurly_subscription_uuid=".$api_subscription->uuid." done successfully, id=".$db_subscription->getId());
		return($this->doFillSubscription($db_subscription));
	}
	
	public function updateDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, Recurly_Subscription $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("recurly dbsubscription update for userid=".$user->getId().", recurly_subscription_uuid=".$api_subscription->uuid.", id=".$db_subscription->getId()."...");
		//UPDATE
		$db_subscription_before_update = clone $db_subscription;
		//
		//$db_subscription->setProviderId($provider->getId());//STATIC
		//$db_subscription->setUserId($user->getId());//STATIC
		$db_subscription->setPlanId($plan->getId());
		$db_subscription = BillingsSubscriptionDAO::updatePlanId($db_subscription);
		//$db_subscription->setSubUid($subscription_uuid);//STATIC
		switch ($api_subscription->state) {
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
				$msg = "unknown subscription state : ".$api_subscription->state;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
		//
		$db_subscription->setSubActivatedDate($api_subscription->activated_at);
		$db_subscription = BillingsSubscriptionDAO::updateSubActivatedDate($db_subscription);
		//
		$db_subscription->setSubCanceledDate($api_subscription->canceled_at);
		$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
		//
		$db_subscription->setSubExpiresDate($api_subscription->expires_at);
		$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
		//
		$db_subscription->setSubPeriodStartedDate($api_subscription->current_period_started_at);
		$db_subscription = BillingsSubscriptionDAO::updateSubStartedDate($db_subscription);
		//
		$db_subscription->setSubPeriodEndsDate($api_subscription->current_period_ends_at);
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
		config::getLogger()->addInfo("recurly dbsubscription update for userid=".$user->getId().", recurly_subscription_uuid=".$api_subscription->uuid.", id=".$db_subscription->getId()." done successfully");
		return($this->doFillSubscription($db_subscription));
	}
	
	private function getDbSubscriptionByUuid(array $db_subscriptions, $subUuid) {
		foreach ($db_subscriptions as $db_subscription) {
			if($db_subscription->getSubUid() == $subUuid) {
				return($db_subscription);
			}
		}
		return(NULL);
	}
	
	private function getApiSubscriptionByUuid(Recurly_SubscriptionList $api_subscriptions, $subUuid) {
		foreach ($api_subscriptions as $api_subscription) {
			if($api_subscription->uuid == $subUuid) {
				return($api_subscription);
			}
		}
		return(NULL);
	}
	
	public function doCancelSubscription(BillingsSubscription $subscription, DateTime $cancel_date, $is_a_request = true) {
		try {
			config::getLogger()->addInfo("recurly subscription canceling...");
			if(
					$subscription->getSubStatus() == "canceled"
					||
					$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				//
				Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
				Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
				//
				$api_subscription = Recurly_Subscription::get($subscription->getSubUid());
				//
				$api_subscription->cancel();
				//
				$subscription->setSubCanceledDate($cancel_date);
				$subscription->setSubStatus('canceled');
				//
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					BillingsSubscriptionDAO::updateSubCanceledDate($subscription);
					BillingsSubscriptionDAO::updateSubStatus($subscription);
					//COMMIT
					pg_query("COMMIT");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			}
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("recurly subscription canceling done successfully for recurly_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while canceling a recurly subscription for recurly_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription canceling failed : ".$msg);
			throw $e;
		} catch (Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while canceling a recurly subscription for recurly_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription canceling failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while canceling a recurly subscription for recurly_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription canceling failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
	
	protected function doFillSubscription(BillingsSubscription $subscription = NULL) {
		parent::doFillSubscription($subscription);
		if($subscription == NULL) {
			return NULL;
		}
		$is_active = NULL;
		switch($subscription->getSubStatus()) {
			case 'active' :
				$is_active = 'yes';//always 'yes' : recurly is managing the status
				break;
			case 'canceled' :
				$is_active = 'yes';//always 'yes' : recurly is managing the status (will change to expired status automatically)
				break;
			case 'future' :
				$is_active = 'no';
				break;
			case 'expired' :
				$is_active = 'no';
				break;
			default :
				$is_active = 'no';
				config::getLogger()->addWarning("recurly dbsubscription unknown subStatus=".$subscription->getSubStatus().", recurly_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;		
		}
		$subscription->setIsActive($is_active);
		if($subscription->getSubStatus() == 'canceled') {
			$subscription->setIsReactivable(true);
		}
		return($subscription);
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		parent::doSendSubscriptionEvent($subscription_before_update, $subscription_after_update);
	}
	
	public function doReactivateSubscription(BillingsSubscription $subscription) {
		try {
			config::getLogger()->addInfo("recurly subscription reactivating...");
			if($subscription->getSubStatus() == "active")
			{
				//nothing todo
			} else {
				//
				Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
				Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
				//
				$api_subscription = Recurly_Subscription::get($subscription->getSubUid());
				//
				$api_subscription->reactivate();
				//
				$subscription->setSubCanceledDate(NULL);
				$subscription->setSubStatus('active');
				//
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					BillingsSubscriptionDAO::updateSubCanceledDate($subscription);
					BillingsSubscriptionDAO::updateSubStatus($subscription);
					//COMMIT
					pg_query("COMMIT");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			}
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("recurly subscription reactivating done successfully for recurly_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while reactivating a recurly subscription for recurly_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription reactivating failed : ".$msg);
			throw $e;
		} catch (Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while reactivating a recurly subscription for recurly_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription reactivating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while reactivating a recurly subscription for recurly_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription reactivating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
	
	public function doUpdateInternalPlan(BillingsSubscription $subscription, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts) {
		try {
			config::getLogger()->addInfo("recurly subscription updating Plan...");
			//
			Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
			Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
			//
			$api_subscription = Recurly_Subscription::get($subscription->getSubUid());
			//
			$api_subscription->plan_code = $plan->getPlanUuid();
			$api_subscription->updateImmediately();
			//
			$subscription->setPlanId($plan->getId());
			//
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				BillingsSubscriptionDAO::updatePlanId($subscription);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("recurly subscription updating Plan done successfully for recurly_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating a Plan recurly subscription for recurly_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription reactivating failed : ".$msg);
			throw $e;
		} catch (Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while updating a Plan recurly subscription for recurly_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription updating Plan failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating a Plan recurly subscription for recurly_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription updating Plan failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
	
}

?>