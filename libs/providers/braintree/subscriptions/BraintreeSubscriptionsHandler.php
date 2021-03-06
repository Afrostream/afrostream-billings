<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../global/ProviderHandlersBuilder.php';
require_once __DIR__ . '/../../global/subscriptions/ProviderSubscriptionsHandler.php';
require_once __DIR__ . '/../../global/transactions/ProviderTransactionsHandler.php';

class BraintreeSubscriptionsHandler extends ProviderSubscriptionsHandler {
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_billing_uuid, $subscription_provider_uuid, BillingInfo $billingInfo, BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("braintree subscription creation...");
			if(isset($subscription_provider_uuid)) {
				checkSubOptsArray($subOpts->getOpts(), 'braintree', 'get');
				// in braintree : user subscription is pre-created
				Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
				Braintree_Configuration::merchantId($this->provider->getMerchantId());
				Braintree_Configuration::publicKey($this->provider->getApiKey());
				Braintree_Configuration::privateKey($this->provider->getApiSecret());
				//
				$subscription = NULL;
				$customer = Braintree\Customer::find($user->getUserProviderUuid());
				foreach ($customer->paymentMethods as $paymentMethod) {
					foreach ($paymentMethod->subscriptions as $customer_subscription) {
						if($customer_subscription->id == $subscription_provider_uuid) {
							$subscription = $customer_subscription;
							break;
						}
					}
				}
				if($subscription == NULL) {
					$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." not found for user with provider_user_uuid=".$user->getUserProviderUuid();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);					
				}
			} else {
				checkSubOptsArray($subOpts->getOpts(), 'braintree', 'create');
				// in braintree : user subscription is NOT pre-created
				Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
				Braintree_Configuration::merchantId($this->provider->getMerchantId());
				Braintree_Configuration::publicKey($this->provider->getApiKey());
				Braintree_Configuration::privateKey($this->provider->getApiSecret());
				//
				if(!array_key_exists('customerBankAccountToken', $subOpts->getOpts())) {
					$msg = "subOpts field 'customerBankAccountToken' field is missing";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$paymentMethod = NULL;
				if($subOpts->getOpts()['customerBankAccountToken'] == 'DEFAULT') {
				    //USE DEFAULT PAYMENT METHOD
				    $customer = Braintree\Customer::find($user->getUserProviderUuid());
				    $currentPaymentMethod = NULL;
				    foreach ($customer->paymentMethods as $loopingPaymentMethod) {
				        if($loopingPaymentMethod->isDefault()) {
				            $paymentMethod = $loopingPaymentMethod;
				            break;
				        }
				    }
				    if($paymentMethod == NULL) {
				        //Exception
				        $msg = "no default payment method found for user with provider_user_uuid=".$user->getUserProviderUuid();
				        throw new Exception($msg);
				    }
				} else {
				    //CREATE NEW PAYMENT METHOD
        				$paymentMethod_attribs = array();
        				$paymentMethod_attribs['customerId'] = $user->getUserProviderUuid();
        				$paymentMethod_attribs['paymentMethodNonce'] = $subOpts->getOpts()['customerBankAccountToken'];
        				$paymentMethod_attribs['options'] = [
        						'makeDefault' => true
        				];
        				$result = Braintree\PaymentMethod::create($paymentMethod_attribs);
        				if ($result->success) {
        					$paymentMethod = $result->paymentMethod;
        				} else {
        					$msg = 'a braintree api error occurred : ';
        					$errorString = $result->message;
        					foreach($result->errors->deepAll() as $error) {
        						$errorString.= '; Code=' . $error->code . ", msg=" . $error->message;
        					}
        					throw new Exception($msg.$errorString);					
        				}
				}
				//
				$attribs = array();
				$attribs['planId'] = $plan->getPlanUuid();
				$attribs['paymentMethodToken'] = $paymentMethod->token;
				$couponCode = NULL;
				if(array_key_exists('couponCode', $subOpts->getOpts())) {
					$couponCode = $subOpts->getOpts()['couponCode'];
				}
				$couponsInfos = $this->getCouponInfos($couponCode, $user, $internalPlan, new CouponTimeframe(CouponTimeframe::onSubCreation));
				$attribs = $this->updateCouponAttribs($attribs, $couponsInfos, $user, $internalPlan);
				$result = Braintree\Subscription::create($attribs);
				if ($result->success) {
					$subscription = $result->subscription;
				} else {
					$msg = 'a braintree api error occurred : ';
					$errorString = $result->message;
					foreach($result->errors->deepAll() as $error) {
						$errorString.= '; Code=' . $error->code . ", msg=" . $error->message;
					}
					throw new Exception($msg.$errorString);
				}
			}
			$sub_uuid = $subscription->id;
			config::getLogger()->addInfo("braintree subscription creation done successfully, braintree_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a braintree subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription creation failed : ".$msg);
			throw $e;
		} catch(Braintree\Exception\NotFound $e) {
			$msg = "a not found error exception occurred while creating a braintree subscription for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);	
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a braintree subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($sub_uuid);
	}
	
	public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
		config::getLogger()->addInfo("braintree dbsubscriptions update for userid=".$user->getId()."...");
		//
		Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
		Braintree_Configuration::merchantId($this->provider->getMerchantId());
		Braintree_Configuration::publicKey($this->provider->getApiKey());
		Braintree_Configuration::privateKey($this->provider->getApiSecret());
		//
		$api_subscriptions = array();
		try {
			$customer = Braintree\Customer::find($user->getUserProviderUuid());
			foreach ($customer->paymentMethods as $paymentMethod) {
				foreach ($paymentMethod->subscriptions as $customer_subscription) {
					$api_subscriptions[] = $customer_subscription;
				}
			}
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting subscriptions for user_provider_uuid=".$user->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		//ADD OR UPDATE
		foreach ($api_subscriptions as $api_subscription) {
			//plan
			$plan_uuid = $api_subscription->planId;
			$plan = PlanDAO::getPlanByUuid($this->provider->getId(), $plan_uuid);
			if($plan == NULL) {
				$msg = "plan with uuid=".$plan_uuid." not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
			$internalPlan = InternalPlanDAO::getInternalPlanById($plan->getInternalPlanId());
			if($internalPlan == NULL) {
				$msg = "plan with uuid=".$plan_uuid." for provider ".$this->provider->getName()." is not linked to an internal plan";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
			$db_subscription = $this->getDbSubscriptionByUuid($db_subscriptions, $api_subscription->id);
			if($db_subscription == NULL) {
				//CREATE
				$db_subscription = $this->createDbSubscriptionFromApiSubscription($user, $userOpts, $internalPlan, $internalPlanOpts, $plan, $planOpts, NULL, NULL, guid(), $api_subscription, 'api', 0);
			} else {
				//UPDATE
				$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, $userOpts, $this->provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, 'api', 0);
			}
		}
		//DELETE UNUSED SUBSCRIPTIONS (DELETED FROM THIRD PARTY)
		foreach ($db_subscriptions as $db_subscription) {
			$api_subscription = $this->getApiSubscriptionByUuid($api_subscriptions, $db_subscription->getSubUid());
			if($api_subscription == NULL) {
				BillingsSubscriptionDAO::deleteBillingsSubscriptionById($db_subscription->getId());
			}
		}
		config::getLogger()->addInfo("braintree dbsubscriptions update for userid=".$user->getId()." done successfully");
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
		//
		Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
		Braintree_Configuration::merchantId($this->provider->getMerchantId());
		Braintree_Configuration::publicKey($this->provider->getApiKey());
		Braintree_Configuration::privateKey($this->provider->getApiSecret());
		//
		$api_subscription = Braintree\Subscription::find($sub_uuid);
		//
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $billingInfo, $subscription_billing_uuid, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, Braintree\Subscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("braintree dbsubscription creation for userid=".$user->getId().", braintree_subscription_uuid=".$api_subscription->id."...");
		//CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid($subscription_billing_uuid);
		$db_subscription->setProviderId($this->provider->getId());
		$db_subscription->setUserId($user->getId());
		$db_subscription->setPlanId($plan->getId());
		$db_subscription->setSubUid($api_subscription->id);
		switch ($api_subscription->status) {
			case Braintree\Subscription::ACTIVE :
				$db_subscription->setSubStatus('active');
				$db_subscription->setSubActivatedDate($api_subscription->createdAt);
				break;
			case Braintree\Subscription::CANCELED :
				$status_history_array = $api_subscription->statusHistory;
				$subscriptionStatus = 'canceled';//by default
				$subCanceledDate = $api_subscription->updatedAt;
				$subExpiresDate = NULL;
				if(count($status_history_array) > 0) {
					$last_status = $status_history_array[0];
					if($last_status->status == Braintree\Subscription::CANCELED) {
						if($last_status->subscriptionSource == Braintree\Subscription::RECURRING) {
							$subscriptionStatus = 'expired';
							$subExpiresDate = $subCanceledDate;
						}
					}
				}
				$db_subscription->setSubStatus($subscriptionStatus);
				$db_subscription->setSubCanceledDate($subCanceledDate);
				$db_subscription->setSubExpiresDate($subExpiresDate);
				break;
			case Braintree\Subscription::EXPIRED :
				$db_subscription->setSubStatus('expired');
				$db_subscription->setSubExpiresDate($api_subscription->updatedAt);
				break;
			case Braintree\Subscription::PAST_DUE :
				$db_subscription->setSubStatus('active');//TODO : check
				break;
			case Braintree\Subscription::PENDING :
				$db_subscription->setSubStatus('future');
				break;
			default :
				$msg = "unknown subscription state : ".$api_subscription->status;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		$subPeriodStartedDate = NULL;
		if($api_subscription->billingPeriodStartDate == NULL) {
			$subPeriodStartedDate = clone $api_subscription->createdAt;
		} else {
			$subPeriodStartedDate = clone $api_subscription->billingPeriodStartDate;
		}
		$db_subscription->setSubPeriodStartedDate($subPeriodStartedDate);
		$subPeriodEndsDate = NULL;
		if($api_subscription->billingPeriodEndDate == NULL) {
			$subPeriodEndsDate = clone $api_subscription->nextBillingDate;
		} else {
			$subPeriodEndsDate = clone $api_subscription->billingPeriodEndDate;
		}
		$subPeriodEndsDate->setTime(23, 59, 59);//force the time to the end of the day (API always gives 00:00:00)
		$db_subscription->setSubPeriodEndsDate($subPeriodEndsDate);
		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription->setDeleted(false);
		//?COUPON?
		$couponCode = NULL;
		if(isset($subOpts)) {
			if(array_key_exists('couponCode', $subOpts->getOpts())) {
				$couponCode = $subOpts->getOpts()['couponCode'];
			}
		}
		$couponsInfos = $this->getCouponInfos($couponCode, $user, $internalPlan, new CouponTimeframe(CouponTimeframe::onSubCreation));
		//NO MORE TRANSACTION (DONE BY CALLER)
		//<-- DATABASE -->
		//BILLING_INFO
		if(isset($billingInfo)) {
			$billingInfo = BillingInfoDAO::addBillingInfo($billingInfo);
			$db_subscription->setBillingInfoId($billingInfo->getId());
		}
		$db_subscription->setPlatformId($this->provider->getPlatformId());
		$db_subscription = BillingsSubscriptionDAO::addBillingsSubscription($db_subscription);
		//SUB_OPTS
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
			$userInternalCoupon->setCouponTimeframe(new CouponTimeframe(CouponTimeframe::onSubCreation));
			$userInternalCoupon = BillingUserInternalCouponDAO::updateCouponTimeframe($userInternalCoupon);
			//internalCoupon
			if($internalCouponsCampaign->getGeneratedMode() == 'bulk') {
				$internalCoupon->setStatus("redeemed");
				$internalCoupon = BillingInternalCouponDAO::updateStatus($internalCoupon);
				$internalCoupon->setRedeemedDate($now);
				$internalCoupon = BillingInternalCouponDAO::updateRedeemedDate($internalCoupon);
				$internalCoupon->setCouponTimeframe(new CouponTimeframe(CouponTimeframe::onSubCreation));
				$internalCoupon = BillingInternalCouponDAO::updateCouponTimeframe($internalCoupon);
			}
		}
		//<-- DATABASE -->
		config::getLogger()->addInfo("braintree dbsubscription creation for userid=".$user->getId().", braintree_subscription_uuid=".$api_subscription->id." done successfully, id=".$db_subscription->getId());
		return($this->doFillSubscription($db_subscription));
	}
	
	public function updateDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, Braintree\Subscription $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {		
		config::getLogger()->addInfo("braintree dbsubscription update for userid=".$user->getId().", braintree_subscription_uuid=".$api_subscription->id.", id=".$db_subscription->getId()."...");
		//UPDATE
		$db_subscription_before_update = clone $db_subscription;
		//
		$now = new DateTime();
		//$db_subscription->setProviderId($this->provider->getId());//STATIC
		//$db_subscription->setUserId($user->getId());//STATIC
		$db_subscription->setPlanId($plan->getId());
		$db_subscription = BillingsSubscriptionDAO::updatePlanId($db_subscription);
		//$db_subscription->setSubUid($subscription_uuid);//STATIC
		switch ($api_subscription->status) {
			case Braintree\Subscription::ACTIVE :
				$db_subscription->setSubStatus('active');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				if($db_subscription->getSubActivatedDate() == NULL) {
					$db_subscription->setSubActivatedDate($now);//assume it's now only if not already set
					$db_subscription = BillingsSubscriptionDAO::updateSubActivatedDate($db_subscription);
				}
				break;
			case Braintree\Subscription::CANCELED :
				$status_history_array = $api_subscription->statusHistory;
				$subscriptionStatus = 'canceled';//by default
				$subCanceledDate = $api_subscription->updatedAt;
				$subExpiresDate = NULL;
				if(count($status_history_array) > 0) {
					$last_status = $status_history_array[0];
					if($last_status->status == Braintree\Subscription::CANCELED) {
						if($last_status->subscriptionSource == Braintree\Subscription::RECURRING) {
							$subscriptionStatus = 'expired';
							$subExpiresDate = $subCanceledDate;
						}
					}
				}
				//NC : cannot replace status when 'expired'
				if($db_subscription->getSubStatus() != 'expired') {
					$db_subscription->setSubStatus($subscriptionStatus);
					$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				}
				//NC : cannot replace canceledDate if already set 
				if($db_subscription->getSubCanceledDate() == NULL) {
					$db_subscription->setSubCanceledDate($subCanceledDate);
					$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
				}
				//NC : cannot replace expiredDate if already set
				if($db_subscription->getSubExpiresDate() == NULL) {
					$db_subscription->setSubExpiresDate($subExpiresDate);
					$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
				}
				break;
			case Braintree\Subscription::EXPIRED :
				$db_subscription->setSubStatus('expired');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				$db_subscription->setSubExpiresDate($api_subscription->updatedAt);
				$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
				break;
			case Braintree\Subscription::PAST_DUE :
				$db_subscription->setSubStatus('active');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				break;
			case Braintree\Subscription::PENDING :
				$db_subscription->setSubStatus('future');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				break;
			default :
				$msg = "unknown subscription state : ".$api_subscription->status;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		//
		$subPeriodStartedDate = NULL;
		if($api_subscription->billingPeriodStartDate == NULL) {
			$subPeriodStartedDate = clone $api_subscription->createdAt;
		} else {
			$subPeriodStartedDate = clone $api_subscription->billingPeriodStartDate;
		}
		$db_subscription->setSubPeriodStartedDate($subPeriodStartedDate);
		$db_subscription = BillingsSubscriptionDAO::updateSubStartedDate($db_subscription);
		$subPeriodEndsDate = NULL;
		if($api_subscription->billingPeriodEndDate == NULL) {
			$subPeriodEndsDate = clone $api_subscription->nextBillingDate;
		} else {
			$subPeriodEndsDate = clone $api_subscription->billingPeriodEndDate;
		}
		$subPeriodEndsDate->setTime(23, 59, 59);//force the time to the end of the day (API always gives 00:00:00)
		$db_subscription->setSubPeriodEndsDate($subPeriodEndsDate);
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
		config::getLogger()->addInfo("braintree dbsubscription update for userid=".$user->getId().", braintree_subscription_uuid=".$api_subscription->id.", id=".$db_subscription->getId()." done successfully");
		return($this->doFillSubscription($db_subscription));
	}
	
	public function doCancelSubscription(BillingsSubscription $subscription, CancelSubscriptionRequest $cancelSubscriptionRequest) {
		try {
			config::getLogger()->addInfo("braintree subscription canceling...");
			if(
					$subscription->getSubStatus() == "canceled"
					||
					$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				//
				Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
				Braintree_Configuration::merchantId($this->provider->getMerchantId());
				Braintree_Configuration::publicKey($this->provider->getApiKey());
				Braintree_Configuration::privateKey($this->provider->getApiSecret());
				//
				$result = Braintree\Subscription::cancel($subscription->getSubUid());
				if (!$result->success) {
					$msg = 'a braintree api error occurred : ';
					$errorString = $result->message;
					foreach($result->errors->deepAll() as $error) {
						$errorString.= '; Code=' . $error->code . ", msg=" . $error->message;
					}
					throw new Exception($msg.$errorString);
				}
				//
				$subscription->setSubCanceledDate($cancelSubscriptionRequest->getCancelDate());
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
			config::getLogger()->addInfo("braintree subscription canceling done successfully for braintree_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while canceling a braintree subscription for braintree_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription canceling failed : ".$msg);
			throw $e;
		} catch(Braintree\Exception\NotFound $e) {
			$msg = "a not found error exception occurred while canceling a braintree subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription canceling failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);	
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while canceling a braintree subscription for braintree_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription canceling failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
	
	public function doFillSubscription(BillingsSubscription $subscription = NULL) {
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
				config::getLogger()->addWarning("braintree dbsubscription unknown subStatus=".$subscription->getSubStatus().", braintree_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;		
		}
		$subscription->setIsActive($is_active);
		if($subscription->getSubStatus() == 'active') {
			$subscription->setIsPlanChangeCompatible(true);
			//ONLY ONE COUPON BY SUB
			$subscription->setIsCouponCodeOnLifetimeCompatible((count(BillingUserInternalCouponDAO::getBillingUserInternalCouponsBySubId($subscription->getId())) == 0) ? true : false);
		}
		return($subscription);
	}
		
	public function doUpdateInternalPlanSubscription(BillingsSubscription $subscription, UpdateInternalPlanSubscriptionRequest $updateInternalPlanSubscriptionRequest) {
		try {
			config::getLogger()->addInfo("braintree subscription updating Plan...");
			//
			$internalPlan = InternalPlanDAO::getInternalPlanByUuid($updateInternalPlanSubscriptionRequest->getInternalPlanUuid(), $this->provider->getPlatformId());
			if($internalPlan == NULL) {
				$msg = "unknown internalPlanUuid : ".$updateInternalPlanSubscriptionRequest->getInternalPlanUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerPlan = PlanDAO::getPlanByInternalPlanId($internalPlan->getId(), $this->provider->getId());
			if($providerPlan == NULL) {
				$msg = "unknown plan : ".$internalPlan->getInternalPlanUuid()." for provider : ".$this->provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
			Braintree_Configuration::merchantId($this->provider->getMerchantId());
			Braintree_Configuration::publicKey($this->provider->getApiKey());
			Braintree_Configuration::privateKey($this->provider->getApiSecret());
			//
			$options = array();
			switch ($updateInternalPlanSubscriptionRequest->getTimeframe()) {
				case 'now' :
					$options['prorateCharges'] = true;
					break;
				case 'atRenewal' :
					//Exception
					$msg = "unsupported timeframe : ".$updateInternalPlanSubscriptionRequest->getTimeframe()." for provider named : ".$this->provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
				default :
					//Exception
					$msg = "unknown timeframe : ".$updateInternalPlanSubscriptionRequest->getTimeframe();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			$result = Braintree\Subscription::update($subscription->getSubUid(), 
					[
							'planId' => $providerPlan->getPlanUuid(),
							'price' => $internalPlan->getAmount(),	//Braintree does not change the price !!!
							'options' => $options,
					]);
			if (!$result->success) {
			    $msg = 'a braintree api error occurred : ';
			    $errorString = $result->message;
			    foreach($result->errors->deepAll() as $error) {
			        $errorString.= '; Code=' . $error->code . ", msg=" . $error->message;
			    }
			    throw new Exception($msg.$errorString);
			}
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				$subscription->setPlanId($providerPlan->getId());
				$subscription = BillingsSubscriptionDAO::updatePlanId($subscription);
				$subscription->setPlanChangeId($providerPlan->getId());
				$subscription = BillingsSubscriptionDAO::updatePlanChangeId($subscription);
				$subscription->setPlanChangeProcessed(true);
				$subscription = BillingsSubscriptionDAO::updatePlanChangeProcessed($subscription);
				$subscription->setPlanChangeProcessedDate(new DateTime());
				$subscription = BillingsSubscriptionDAO::updatePlanChangeProcessedDate($subscription);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("braintree subscription updating Plan done successfully for braintree_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating a Plan braintree subscription for braintree_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription updating Plan failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating a Plan braintree subscription for braintree_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription updating Plan failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
		
	private function getApiSubscriptionByUuid(array $api_subscriptions, $subUuid) {
		foreach ($api_subscriptions as $api_subscription) {
			if($api_subscription->id == $subUuid) {
				return($api_subscription);
			}
		}
		return(NULL);
	}
	
	public function doExpireSubscription(BillingsSubscription $subscription, ExpireSubscriptionRequest $expireSubscriptionRequest) {
		try {
			config::getLogger()->addInfo("braintree subscription expiring...");
			if(
				$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				//
				$expiresDate = $expireSubscriptionRequest->getExpiresDate();
				//
				if(in_array($subscription->getSubStatus(), ['active', 'canceled'])) {
					if($subscription->getSubPeriodEndsDate() > $expiresDate) {
						if($expireSubscriptionRequest->getForceBeforeEndsDate() == false) {
							$msg = "cannot expire a ".$this->provider->getName()." subscription that has not ended yet";
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_EXP_BEFORE_ENDS_DATE_UNSUPPORTED);
						}
					}
				}
				if(in_array($expireSubscriptionRequest->getOrigin(), ['api', 'hook'])) {
					if($subscription->getSubStatus() == "canceled") {
						//already canceled, nothing can be done in braintree side
					} else {
						//
						Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
						Braintree_Configuration::merchantId($this->provider->getMerchantId());
						Braintree_Configuration::publicKey($this->provider->getApiKey());
						Braintree_Configuration::privateKey($this->provider->getApiSecret());
						//
						$result = Braintree\Subscription::cancel($subscription->getSubUid());
						if (!$result->success) {
							$msg = 'a braintree api error occurred : ';
							$errorString = $result->message;
							foreach($result->errors->deepAll() as $error) {
								$errorString.= '; Code=' . $error->code . ", msg=" . $error->message;
							}
							throw new Exception($msg.$errorString);
						}
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
				if($expireSubscriptionRequest->getIsRefundEnabled() == true) {
					$transactionsResult = BillingsTransactionDAO::getBillingsTransactions(1, 0, NULL, NULL, $subscription->getId(), ['purchase'], 'descending', $this->provider->getPlatformId());
					if(count($transactionsResult['transactions']) == 1) {
						$transaction = $transactionsResult['transactions'][0];
						//check status
						switch($transaction->getTransactionStatus()) {
							case BillingsTransactionStatus::success :
							    $amountInCents = NULL; //NULL = Refund ALL
		    					if($expireSubscriptionRequest->getIsRefundProrated() == true) {
		    						$amountInCents = ceil($transaction->getAmountInCents() * ($subscription->getSubPeriodEndsDate()->getTimestamp() - (new DateTime())->getTimestamp())
		    						/
		    						($subscription->getSubPeriodEndsDate()->getTimestamp() - $subscription->getSubPeriodStartedDate()->getTimestamp()));
		    					}
								//
								$providerTransactionsHandlerInstance = ProviderHandlersBuilder::getProviderTransactionsHandlerInstance($this->provider);
								$refundTransactionRequest = new RefundTransactionRequest();
								$refundTransactionRequest->setPlatform($this->platform);
								$refundTransactionRequest->setOrigin($expireSubscriptionRequest->getOrigin());
								$refundTransactionRequest->setTransactionBillingUuid($transaction->getTransactionBillingUuid());
								$refundTransactionRequest->setAmountInCents($amountInCents);
								$transaction = $providerTransactionsHandlerInstance->doRefundTransaction($transaction, $refundTransactionRequest);
								break;
							case BillingsTransactionStatus::waiting :
								$msg = "cannot refund a transaction in status=".$transaction->getTransactionStatus();
								throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
								break;
							case BillingsTransactionStatus::declined :
								$msg = "cannot refund a transaction in status=".$transaction->getTransactionStatus();
								throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
								break;
							case BillingsTransactionStatus::failed :
								$msg = "cannot refund a transaction in status=".$transaction->getTransactionStatus();
								throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
								break;
							case BillingsTransactionStatus::canceled :
								$msg = "cannot refund a transaction in status=".$transaction->getTransactionStatus();
								throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
								break;
							case BillingsTransactionStatus::void :
								//nothing to do
								break;
							default :
								$msg = "unknown transaction status=".$transaction->getTransactionStatus();
								throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
								break;
						}
					}
				}
			}
			//
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("braintree subscription expiring done successfully for braintree_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while expiring a braintree subscription for braintree_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription expiring failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring a braintree subscription for braintree_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
	
	private function updateCouponAttribs(array $attribs, $couponsInfos, User $user, InternalPlan $internalPlan) {
		if(isset($couponsInfos)) {
			$billingInternalCouponsCampaign = $couponsInfos['internalCouponsCampaign'];
			$billingProviderCouponsCampaign = $couponsInfos['providerCouponsCampaign'];
			$discountArray = array();
			$discountArray['inheritedFromId'] = $billingProviderCouponsCampaign->getExternalUuid();
			switch($billingInternalCouponsCampaign->getDiscountType()) {
				case 'amount' :
					$discountArray['amount'] = number_format((float) ($billingInternalCouponsCampaign->getAmountInCents() / 100), 2, '.', '');
					break;
				case 'percent':
					$discountArray['amount'] = number_format((float) (($internalPlan->getAmountInCents() * $billingInternalCouponsCampaign->getPercent()) / 10000), 2, '.', '');
					break;
				default :
					$msg = "unsupported discount_type=".$billingInternalCouponsCampaign->getDiscountType();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			switch($billingInternalCouponsCampaign->getDiscountDuration()) {
				case 'once' :
					$discountArray['numberOfBillingCycles'] = 1;
					break;
				case 'forever' :
					$discountArray['neverExpires'] = true;
					break;
				case 'repeating' :
					//all braintree plans are montlhy based
					$numberOfMonthsInACycle = NULL;
					switch ($internalPlan->getPeriodUnit()) {
						case 'month' :
							$numberOfMonthsInACycle = 1 * $internalPlan->getPeriodLength();
							break;
						case 'year' :
							$numberOfMonthsInACycle = 12 * $internalPlan->getPeriodLength();
							break;
						default :
							$msg = "unsupported period_unit=".$internalPlan->getPeriodUnit();
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							break;
					}
					$numberOfMonthsOfDiscount = NULL;
					switch($billingInternalCouponsCampaign->getDiscountDurationUnit()) {
						case 'month' :
							$numberOfMonthsOfDiscount = 1 * $billingInternalCouponsCampaign->getDiscountDurationLength();
							break;
						case 'year' :
							$numberOfMonthsOfDiscount = 12 * $billingInternalCouponsCampaign->getDiscountDurationLength();
							break;
						default :
							$msg = "unsupported discount_duration_unit=".$billingInternalCouponsCampaign->getDiscountDurationUnit();
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							break;
					}
					if(($numberOfMonthsOfDiscount%$numberOfMonthsInACycle) > 0) {
						$msg = "discount is not compatible with this plan";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					$discountArray['numberOfBillingCycles'] = $numberOfMonthsOfDiscount / $numberOfMonthsInACycle;
					break;
				default :
					$msg = "unsupported discount_duration=".$billingInternalCouponsCampaign->getDiscountDuration();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			$attribs['discounts'] = ['add' =>	[$discountArray]];
		}
		return($attribs);	
	}
	
	public function doRedeemCoupon(BillingsSubscription $subscription, RedeemCouponRequest $redeemCouponRequest) {
		try {
			config::getLogger()->addInfo("redeeming a coupon for braintree_subscription_uuid=".$subscription->getSubUid()."...");
			//
			Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
			Braintree_Configuration::merchantId($this->provider->getMerchantId());
			Braintree_Configuration::publicKey($this->provider->getApiKey());
			Braintree_Configuration::privateKey($this->provider->getApiSecret());
			//
			$user = UserDAO::getUserById($subscription->getUserId());
			$internalPlan = InternalPlanDAO::getInternalPlanByProviderPlanId($subscription->getPlanId());
			//
			$couponsInfos = $this->getCouponInfos($redeemCouponRequest->getCouponCode(), $user, $internalPlan, new CouponTimeframe(CouponTimeframe::onSubLifetime));
			//
			$attribs = array();
			$attribs = $this->updateCouponAttribs($attribs, $couponsInfos, $user, $internalPlan);
			Braintree\Subscription::update($subscription->getSubUid(), $attribs);
			//<-- DATABASE -->
			try {
				//START TRANSACTION
				pg_query("BEGIN");
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
				$userInternalCoupon->setSubId($subscription->getId());
				$userInternalCoupon = BillingUserInternalCouponDAO::updateSubId($userInternalCoupon);
				$userInternalCoupon->setCouponTimeframe(new CouponTimeframe(CouponTimeframe::onSubLifetime));
				$userInternalCoupon = BillingUserInternalCouponDAO::updateCouponTimeframe($userInternalCoupon);
				//internalCoupon
				if($internalCouponsCampaign->getGeneratedMode() == 'bulk') {
					$internalCoupon->setStatus("redeemed");
					$internalCoupon = BillingInternalCouponDAO::updateStatus($internalCoupon);
					$internalCoupon->setRedeemedDate($now);
					$internalCoupon = BillingInternalCouponDAO::updateRedeemedDate($internalCoupon);
					$internalCoupon->setCouponTimeframe(new CouponTimeframe(CouponTimeframe::onSubLifetime));
					$internalCoupon = BillingInternalCouponDAO::updateCouponTimeframe($internalCoupon);
				}
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			//<-- DATABASE -->
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("redeeming a coupon for braintree_subscription_uuid=".$subscription->getSubUid()." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while redeeming a coupon for braintree_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("redeeming a coupon failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while redeeming a coupon for braintree_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("redeeming a coupon failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
	
}

?>