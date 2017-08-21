<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../global/ProviderHandlersBuilder.php';
require_once __DIR__ . '/../../global/subscriptions/ProviderSubscriptionsHandler.php';
require_once __DIR__ . '/../../global/transactions/ProviderTransactionsHandler.php';

class StripeSubscriptionsHandler extends ProviderSubscriptionsHandler
{
	
    public function __construct($provider) {
    	parent::__construct($provider);
    	\Stripe\Stripe::setApiKey($this->provider->getApiSecret());
    }

    /**
     * Create new subscription to provider if subscriptionProviderUuid is not set
     *
     * @param User                     $user
     * @param UserOpts                 $userOpts
     * @param InternalPlan             $internalPlan
     * @param InternalPlanOpts         $internalPlanOpts
     * @param Plan                     $plan
     * @param PlanOpts                 $planOpts
     * @param string                   $subscription_billing_uuid
     * @param string                   $subscriptionProviderUuid
     * @param BillingInfo			   $billingInfo
     * @param BillingsSubscriptionOpts $subOpts
     *
     * @throws BillingsException
     *
     * @return BillingsSubscriptionId
     */
    public function doCreateUserSubscription(
        User $user,
        UserOpts $userOpts,
        InternalPlan $internalPlan,
        InternalPlanOpts $internalPlanOpts,
        Plan $plan,
        PlanOpts $planOpts,
    	$subscription_billing_uuid,
        $subscriptionProviderUuid,
        BillingInfo $billingInfo,
        BillingsSubscriptionOpts $subOpts
    )
    {
    	$subscriptionId = NULL;
    	if (isset($subscriptionProviderUuid)) {
    		$subscription = $this->getSubscription($subscriptionProviderUuid, $user);
    		$subscriptionId = $subscription['id'];
    	} else {
    		$metadata = [
    				'AfrSource' => 'afrBillingApi',
    				'AfrOrigin' => 'subscription',
    				'AfrSubscriptionBillingUuid' => $subscription_billing_uuid,
    				'AfrUserBillingUuid' => $user->getUserBillingUuid()
    		];
	        if ($internalPlan->getCycle() == PlanCycle::once) {
	            $subscriptionId = $this->chargeCustomer($user, $plan, $subOpts, $internalPlan, $metadata);
            } else {
                $subscriptionId = $this->createSubscription($user, $plan, $subOpts, $internalPlan, $metadata);
            }
        }
        return $subscriptionId;
    }

    /**
     * Record a billing subscription and its options
     *
     * @param BillingsSubscription          $billingSubscription
     * @param BillingsSubscriptionOpts|null $subOpts
     * @param string                        $updateType
     * @param int                           $updateId
     *
     * @return BillingsSubscription
     */
    
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
			$updateType, 
			$updateId) {
    	$api_subscription = NULL;
    	if ($internalPlan->getCycle() == PlanCycle::once) {
    		/* EMULATE API */
    		$currentTimeStamp = time();
    		$api_subscription = new \Stripe\Subscription();
    		$api_subscription['id'] = $sub_uuid;
    		$api_subscription['created'] = $currentTimeStamp;
    		$api_subscription['canceled_at'] = NULL;
    		$api_subscription['current_period_start'] = $currentTimeStamp;
    		$api_subscription['current_period_end'] = $this->calculateSubscriptionDateEnd($internalPlan, $currentTimeStamp);
    		$api_subscription['status'] = 'active';
    	} else {
    		/* GET FROM API */
    		$api_subscription = $this->getSubscription($sub_uuid, $user);
    	}
    	return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $billingInfo, $subscription_billing_uuid, $api_subscription, $updateType, $updateId));
    }

    public function createDbSubscriptionFromApiSubscription(User $user,
    		UserOpts $userOpts,
    		InternalPlan $internalPlan = NULL,
    		InternalPlanOpts $internalPlanOpts = NULL,
    		Plan $plan = NULL,
    		PlanOpts $planOpts = NULL,
    		BillingsSubscriptionOpts $subOpts = NULL,
    		BillingInfo $billingInfo = NULL,
    		$subscription_billing_uuid,
    		\Stripe\Subscription $api_subscription,
    		$updateType,
    		$updateId) {
    	$billingSubscription = $this->getNewBillingSubscription($user, $plan, $api_subscription, $subscription_billing_uuid);
    	$billingSubscription->setUpdateType($updateType);
    	$billingSubscription->setUpdateId($updateId);
    			
    	$subscriptionInfos = $billingSubscription->jsonSerialize();
    	$this->log('record subscription. providerUuid: %s, user billing uuid: %s, user provider uuid: %s, internal plan: %s',
    					[
    						$subscriptionInfos['subscriptionProviderUuid'],
    						$subscriptionInfos['user']['userBillingUuid'],
    						$subscriptionInfos['user']['userProviderUuid'],
    						$subscriptionInfos['internalPlan']['internalPlanUuid']
    					]
    	);
    	//?COUPON?
    	$couponCode = NULL;
    	if(isset($subOpts)) {
    		if(array_key_exists('couponCode', $subOpts->getOpts())) {
    			$couponCode = $subOpts->getOpts()['couponCode'];
    		}
    	}
    	$couponsInfos = $this->getCouponInfos($couponCode, $user, $internalPlan, new CouponTimeframe(CouponTimeframe::onSubCreation));
    	//<-- DATABASE -->
    	//BILLING_INFO (NOT MANDATORY)
    	if(isset($billingInfo)) {
    		$billingInfo = BillingInfoDAO::addBillingInfo($billingInfo);
    		$billingSubscription->setBillingInfoId($billingInfo->getId());
    	}
    	$billingSubscription->setPlatformId($this->provider->getPlatformId());
    	$billingSubscription = BillingsSubscriptionDAO::addBillingsSubscription($billingSubscription);
    		
    	$this->log('Subscription id : '.$billingSubscription->getId());
    	//SUB_OPTS (NOT MANDATORY)
    	if(isset($subOpts)) {
    		$subOpts->setSubId($billingSubscription->getId());
    		BillingsSubscriptionOptsDAO::addBillingsSubscriptionOpts($subOpts);
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
    		$userInternalCoupon->setSubId($billingSubscription->getId());
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
    	return $this->doFillSubscription($billingSubscription);
    }
	    
    /**
     * Synchronize subscription between afrostream and provider for the given user
     * 
     * @param User     $user
     * @param UserOpts $userOpts
     *
     * @throws BillingsException
     */
    public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
    	config::getLogger()->addInfo($this->provider->getName()." dbsubscriptions update for userid=".$user->getId()."...");
    	$customer = \Stripe\Customer::retrieve($user->getUserProviderUuid());
    	///!\ 
    	///!\ *** DO NOT FORGET : subscriptions that are not recurrent are not present in Stripe side ***
    	///!\
    	$api_subscriptions = $customer['subscriptions']['data'];
    	$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
    	foreach ($api_subscriptions as $api_subscription) {
    		$plan = PlanDAO::getPlanByUuid($this->provider->getId(), $subscription['plan']['id']);
    		if($plan == NULL) {
    			$msg = "plan with uuid=".$subscription['plan']['id']." not found";
    			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
    		}
    		$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
    		$internalPlan = InternalPlanDAO::getInternalPlanByProviderPlanId($plan->getId());
    		if($internalPlan == NULL) {
    			$msg = "plan with uuid=".$plan->getPlanUuid()." for provider ".$this->provider->getName()." is not linked to an internal plan";
    			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
    		}
    		$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
    		$db_subscription = $this->getDbSubscriptionByUuid($db_subscriptions, $api_subscription['id']);
    		if($db_subscription == NULL) {
    			//CREATE
    			$this->createDbSubscriptionFromApiSubscription($user, $userOpts, $internalPlan, $internalPlanOpts, $plan, $planOpts, NULL, NULL, guid(), $api_subscription, 'api', 0);
    		} else {
    			//UPDATE
    			$this->updateDbSubscriptionFromApiSubscription($user, $userOpts, $this->provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, 'api', 0);
    		}
    	}
    	//DELETE UNUSED SUBSCRIPTIONS (DELETED FROM THIRD PARTY) (ONLY RECURRENT ONES)
    	foreach ($db_subscriptions as $db_subscription) {
    		$plan = $db_subscription->getPlanId();
    		$internalPlan = InternalPlanDAO::getInternalPlanByProviderPlanId($plan->getId());
    		if ($internalPlan->getCycle()->getValue() === PlanCycle::auto) {
    			$api_subscription = $this->getApiSubscriptionByUuid($api_subscriptions, $db_subscription->getSubUid());
    			if($api_subscription == NULL) {
    				BillingsSubscriptionDAO::deleteBillingsSubscriptionById($db_subscription->getId());
    			}
    		}
    	}
    	config::getLogger()->addInfo($this->provider->getName()." dbsubscriptions update for userid=".$user->getId()." done successfully");
    	/*
        $customer = \Stripe\Customer::retrieve($user->getUserProviderUuid());
        if (empty($customer['id'])) {
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Unknow customer');
        }
		
        $subscriptionList = $customer['subscriptions']['data'];
        $recordedSubscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());

        foreach ($subscriptionList as $subscription) {
            $providerPlanId = $subscription['plan']['id'];

            $plan = PlanDAO::getPlanByUuid($this->provider->getId(), $providerPlanId);
            if($plan == NULL) {
                $msg = "plan with uuid=".$providerPlanId." not found";
                throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
            }

            $planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
            $internalPlan = InternalPlanDAO::getInternalPlanById($plan->getInternalPlanId());
            if($internalPlan == NULL) {
                $msg = "plan with uuid=".$providerPlanId." for provider ".$this->provider->getName()." is not linked to an internal plan";
                throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
            }
            $internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());

            $billingSubscription = $this->findRecordedSubscriptionByProviderId($recordedSubscriptions, $subscription['id']);

            // subscription not found, so we create it
            if (is_null($billingSubscription)) {
            	$subscription_billing_uuid = guid();
                $billingSubscription = $this->getNewBillingSubscription($user, $plan, $subscription, $subscription_billing_uuid);
                $this->createDbSubscriptionFromApiSubscription($user, $userOpts,
                		$internalPlan, $internalPlanOpts, $plan, $planOpts, 
                		NULL, NULL, $subscription_billing_uuid, $billingSubscription, 'api', 0);
            } else {
                // we found one update and record it
                $billingSubscription->setPlanId($plan->getId());
                $billingSubscription->setSubStatus($this->getStatusFromProvider($subscription));
                $billingSubscription->setSubActivatedDate($this->createDate($subscription['created']));
                $billingSubscription->setSubCanceledDate($this->createDate($subscription['canceled_at']));
                $billingSubscription->setSubPeriodStartedDate($this->createDate($subscription['current_period_start']));
                $billingSubscription->setSubPeriodEndsDate($this->createDate($subscription['current_period_end']));

                BillingsSubscriptionDAO::updateBillingsSubscription($billingSubscription);
            }
        }

        foreach ($recordedSubscriptions as $billingSubscription) {
            if (!$this->findProviderSubscriptionById($subscriptionList, $billingSubscription->getSubUid())) {
                BillingsSubscriptionDAO::deleteBillingsSubscriptionById($billingSubscription->getId());
            }
        }*/
    }

    /**
     * Fill subscription to set active status
     *
     * @param BillingsSubscription|NULL $subscription
     */
    public function doFillSubscription(BillingsSubscription $subscription = NULL)
    {
    	$subscription = parent::doFillSubscription($subscription);
        if($subscription == NULL) {
            return NULL;
        }
        $is_active = NULL;
        switch($subscription->getSubStatus()) {
            case 'active' :
                $is_active = 'yes';
                break;
            case 'canceled' :
                // check on date
                $now = new \DateTime();
                if ($now < $subscription->getSubPeriodEndsDate() && $now > $subscription->getSubPeriodStartedDate()) {
                    $is_active = 'yes';
                } else {
                    $is_active = 'no';
                }

                break;
            case 'expired' :
                $is_active = 'no';
                break;
            default :
                $is_active = 'no';
                break;
        }
        $subscription->setIsActive($is_active);
        if($subscription->getSubStatus() == 'canceled') {
            $subscription->setIsReactivable(true);
        }
        if($subscription->getSubStatus() == 'active') {
        	$subscription->setIsPlanChangeCompatible(true);
        	//ONLY ONE COUPON BY SUB
        	$subscription->setIsCouponCodeOnLifetimeCompatible((count(BillingUserInternalCouponDAO::getBillingUserInternalCouponsBySubId($subscription->getId())) == 0) ? true : false);
        }
        return($subscription);
    }

    /**
     * Cancel a subscription
     *
     * @param BillingsSubscription $billingSubscription
     * @param DateTime             $cancelDate
     */
    public function doCancelSubscription(BillingsSubscription $billingSubscription, CancelSubscriptionRequest $cancelSubscriptionRequest)
    {
        if (in_array($billingSubscription->getSubStatus(), ['canceled', 'expired'])) {
        	//nothing todo : already done or in process
        } else {
	        // get user
	        $user = UserDAO::getUserById($billingSubscription->getUserId());
	        $providerPlan = PlanDAO::getPlanById($billingSubscription->getPlanId());
            $internalPlan = InternalPlanDAO::getInternalPlanById($providerPlan->getInternalPlanId());
            if ($internalPlan->getCycle() == PlanCycle::auto) {
           		$subscription = $this->getSubscription($billingSubscription->getSubUid(), $user);
             	$this->log('Cancel subscription id %s ', [$subscription['id']]);
                $subscription->cancel(['at_period_end' => true]);
            	$subscription->save();
            } else {
            	throw new BillingsException(new ExceptionType(ExceptionType::internal), "Subscription with cycle=".$internalPlan->getCycle()." cannot be canceled");
            }
            $billingSubscription->setSubCanceledDate($cancelSubscriptionRequest->getCancelDate());
            $billingSubscription->setSubStatus('canceled');
            try {
            	//START TRANSACTION
            	pg_query("BEGIN");
            	BillingsSubscriptionDAO::updateSubCanceledDate($billingSubscription);
            	BillingsSubscriptionDAO::updateSubStatus($billingSubscription);
            	//COMMIT
            	pg_query("COMMIT");
            } catch(Exception $e) {
            	pg_query("ROLLBACK");
            	throw $e;
            }
        }
        return($this->doFillSubscription(BillingsSubscriptionDAO::getBillingsSubscriptionById($billingSubscription->getId())));
    }
	
    public function doUpdateInternalPlanSubscription(BillingsSubscription $subscription, UpdateInternalPlanSubscriptionRequest $updateInternalPlanSubscriptionRequest) {
	    try {
	    	config::getLogger()->addInfo("stripe subscription updating Plan...");
	    	$internalPlan = InternalPlanDAO::getInternalPlanByUuid($updateInternalPlanSubscriptionRequest->getInternalPlanUuid(), $updateInternalPlanSubscriptionRequest->getPlatform()->getId());
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
	    	switch ($updateInternalPlanSubscriptionRequest->getTimeframe()) {
	    		case 'now' :
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
	        
	    	$user = UserDAO::getUserById($subscription->getUserId());
	
	        $api_subscription = $this->getSubscription($subscription->getSubUid(), $user);
	        $api_subscription->plan = $providerPlan->getPlanUuid();
	        $api_subscription->save();
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
			config::getLogger()->addInfo("stripe subscription updating Plan done successfully for stripe_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating a Plan stripe subscription for stripe_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("stripe subscription updating Plan failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating a Plan stripe subscription for stripe_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("stripe subscription updating Plan failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
    }

    /**
     * Reactivate a canceled subscription
     *
     * Only available when the period is still active.
     *
     * @TODO create new subscription if period is not active anymore
     *
     * @param BillingsSubscription $billingSubscription
     *
     * @throws BillingsException
     */
	public function doReactivateSubscription(BillingsSubscription $subscription, ReactivateSubscriptionRequest $reactivateSubscriptionRequest) {
		try {
			config::getLogger()->addInfo($this->provider->getName()." subscription reactivating...");
	   		if($subscription->getSubStatus() == "active") {
	   			//nothing to do
	   		} else if ($subscription->getSubStatus() == "canceled") {
	   			$providerPlan = PlanDAO::getPlanById($subscription->getPlanId());
	   			$api_subscription = \Stripe\Subscription::retrieve($subscription->getSubUid());
		        $api_subscription->plan = $providerPlan->getPlanUuid();
		        $api_subscription->save();
		        //
		        $subscription->setSubCanceledDate(NULL);
		        $subscription->setSubStatus('active');
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
	       	} else {
	    		$msg = "cannot reactivate subscription with status=".$subscription->getSubStatus();
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
	       	$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
	    } catch(BillingsException $e) {
	    	$msg = "a billings exception occurred while reactivating a ".$this->provider->getName()." subscription for ".$this->provider->getName()."_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
	       	config::getLogger()->addError($this->provider->getName()." subscription reactivating failed : ".$msg);
	       	throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while reactivating a ".$this->provider->getName()." subscription for ".$this->provider->getName()."_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." subscription reactivating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
       	return($this->doFillSubscription($subscription));
	}

    /**
     * Get internal status from provider status
     * 
     * @param $status
     *
     * @return null|string
     */
    public static function getMappedStatus($status)
    {
        switch ($status) {
            case 'active':
            case 'trialing':
            case 'past_due':
                $status = 'active';
                break;
            case 'canceled':
                $status = 'canceled';
                break;
            case 'unpaid':
                $status = 'expired';
                break;
            default:
                $status = null;
        }

        return $status;
    }
    /**
     * Return date with the given timestamp
     *
     * @param int|null $timestamp
     *
     * @return null|string
     */
    protected function createDate($timestamp) {
        if (empty($timestamp)) {
            return null;
        }

        return new \DateTime(date('c', $timestamp));
    }

    /**
     * Get mapped status between stripe and billing
     *
     * @param Subscription $subscription
     *
     * @throws BillingsException
     *
     * @return string
     */
    protected function getStatusFromProvider(Stripe\Subscription $subscription)
    {
        $status = self::getMappedStatus($subscription['status']);

        if (is_null($status)) {
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Unknow stripe subscription status');
        }

        return $status;
    }

    /**
     * Retrieve subscription with the given provider id
     *
     * @param string $subscriptionProviderUuid
     *
     * @throws BillingsException If not found
     *
     * @return \Stripe\Subscription
     */
    protected function getSubscription($subscriptionProviderUuid, User $user)
    {
        $this->log('Retrieve subscription whith id '.$subscriptionProviderUuid);

        $subscription = \Stripe\Subscription::retrieve($subscriptionProviderUuid);

        if (empty($subscription['id'])) {
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'No subscription available with the given id');
        }

        // check if it's the right customer
        if ($subscription['customer'] != $user->getUserProviderUuid())
        {
            throw new BillingsException(new ExceptionType(ExceptionType::internal), "Supplied stripe's subscription does not belong to given user" );
        }

        return $subscription;
    }

    /**
     * Create a subscriptoion for the given user on the given plan
     *
     * @param User                     $user
     * @param Plan                     $plan
     * @param BillingsSubscriptionOpts $subOpts
     * @throws BillingsException
     *
     * @return string
     */
    protected function createSubscription(User $user, Plan $plan, BillingsSubscriptionOpts $subOpts, InternalPlan $internalPlan, array $metadata)
    {
        if (is_null($subOpts->getOpt('customerBankAccountToken'))) {
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Error while creating subscription. Missing stripe token');
        }

        $subscriptionData = [
            "customer" => $user->getUserProviderUuid(),
            "plan" => $plan->getPlanUuid(),
            'source' => $subOpts->getOpt('customerBankAccountToken'),
            "metadata" => $metadata
        ];

        $logMessage = 'Create subscription : customer : %s, plan : %s, source : %s';
        
        //couponCode
        $couponCode = NULL;
        if(array_key_exists('couponCode', $subOpts->getOpts())) {
        	$couponCode = $subOpts->getOpts()['couponCode'];
        }
        $couponsInfos = $this->getCouponInfos($couponCode, $user, $internalPlan, new CouponTimeframe(CouponTimeframe::onSubCreation));
        if(isset($couponsInfos)) {
	        $subscriptionData['coupon'] = $couponsInfos['providerCouponsCampaign']->getExternalUuid();
	        $logMessage = 'Create subscription : customer : %s, plan : %s, source : %s, coupon : %s';
        }
        $this->log($logMessage, $subscriptionData);
		
        $subscription = \Stripe\Subscription::create($subscriptionData);

        if (empty($subscription['id'])) {
            $this->log('Error while creating subscription');
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Error while creating subscription.');
        }

        return $subscription[id];

    }

    /**
     * Charge a customer for plan who's not recurrent
     *
     * @param User                     $user
     * @param Plan                     $plan
     * @param BillingsSubscriptionOpts $subOpts
     * @param InternalPlan             $internalPlan
     *
     * @throws BillingsException
     *
     * @return Subscription
     */
    protected function chargeCustomer(User $user, Plan $plan, BillingsSubscriptionOpts $subOpts, InternalPlan $internalPlan, array $metadata)
    {
        if (is_null($subOpts->getOpt('customerBankAccountToken'))) {
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Error while creating subscription. customerBankAccountToken field is missing');
        }
        try {
            $this->log('Update customer : set source : '.$subOpts->getOpt('customerBankAccountToken'));

            // assign token to customer then charge him
            $customer = \Stripe\Customer::retrieve($user->getUserProviderUuid());
            $customer->source = $subOpts->getOpt('customerBankAccountToken');
            $customer->save();
            
            $amount = $internalPlan->getAmountInCents();
            $discount = 0;
            $couponCode = NULL;
            if(array_key_exists('couponCode', $subOpts->getOpts())) {
            	$couponCode = $subOpts->getOpts()['couponCode'];
            }
            $couponsInfos = $this->getCouponInfos($couponCode, $user, $internalPlan, new CouponTimeframe(CouponTimeframe::onSubCreation));
            if(isset($couponsInfos)) {
            	$billingInternalCouponsCampaign = $couponsInfos['internalCouponsCampaign'];
            	$billingInternalCoupon = $couponsInfos['internalCoupon'];
            	$billingUserInternalCoupon = $couponsInfos['userInternalCoupon'];
            	if($billingInternalCouponsCampaign->getDiscountDuration() != 'once') {
            		$msg = "discount is not compatible with this plan";
            		config::getLogger()->addError($msg);
            		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
            	}
            	switch($billingInternalCouponsCampaign->getDiscountType()) {
            		case 'amount' :
            			$discount = $billingInternalCouponsCampaign->getAmountInCents();
            			break;
            		case 'percent':
            			$discount = $internalPlan->getAmountInCents() * $billingInternalCouponsCampaign->getPercent() / 100;
            			break;
            		default :
            			$msg = "unsupported discount_type=".$billingInternalCouponsCampaign->getDiscountType();
            			config::getLogger()->addError($msg);
            			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
            			break;
            	}
            	//
            	$metadata['AfrCouponsCampaignInternalBillingUuid'] = $billingInternalCouponsCampaign->getUuid();
            	$metadata['AfrInternalCouponBillingUuid'] = $billingInternalCoupon->getUuid();
            	$metadata['AfrInternalUserCouponBillingUuid'] =	$billingUserInternalCoupon->getUuid();
            }
            $amount = floor($amount - $discount);
            if($amount <= 0) {
            	$msg = "amount is not compatible with this plan";
            	config::getLogger()->addError($msg);
            	throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
            }
            $chargeData = array(
                "amount" => $amount,
                "currency" => $internalPlan->getCurrency(),
                "customer" => $user->getUserProviderUuid(),
                "description" => $plan->getPlanUuid(),
                "metadata" => $metadata
            );

            $this->log("Charge customer,  amount : %s, currency : %s, customer : %s, description : %s", $chargeData);

            $charge = \Stripe\Charge::create($chargeData);

            if (!$charge->paid) {
                $this->log('Payment refused');
                throw new \Exception('Payment refused');
            }

            $subOpts->setOpt('chargeId', $charge['id']);
            
            return guid();

        } catch (\Exception $e) {
            throw new BillingsException(new ExceptionType(ExceptionType::internal), "Error while charging customer : ".$e->getMessage());
        }
    }

    /**
     * Calculate date end of a subscription who is not recurrent
     *
     * @param InternalPlan $internalPlan
     * @param int          $timestampStart
     *
     * @return int
     */
    protected function calculateSubscriptionDateEnd(InternalPlan $internalPlan, $timestampStart)
    {
        $date = new DateTime();
        $date->setTimestamp($timestampStart);

        switch ($internalPlan->getPeriodUnit()) {
            case PlanPeriodUnit::day:
                $interval = new DateInterval("P".$internalPlan->getPeriodLength()."D");
                break;
            case PlanPeriodUnit::month:
                $interval = new DateInterval("P".$internalPlan->getPeriodLength()."M");
                break;
            case PlanPeriodUnit::year:
                $interval = new DateInterval("P".$internalPlan->getPeriodLength()."Y");
                break;
            default:
                throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Unsupported plan period unit : '.$internalPlan->getPeriodUnit());
                break;
        }

        $date->add($interval);
        $date->setTime(23, 59);

        return $date->getTimestamp();
    }

    /**
     * @param array  $recorded
     * @param string $id
     *
     * @return BillingsSubscription|null
     */
    protected function findRecordedSubscriptionByProviderId(array $recorded, $id)
    {
        foreach ($recorded as $subscription) {
            if ($subscription->getSubUid() == $id) {
                return $subscription;
            }
        }
    }

    /**
     * Check if the recorded id is always up on provider side
     *
     * @param array  $subscriptionList
     * @param string $id
     *
     * @return bool
     */
    protected function findProviderSubscriptionById(array $subscriptionList, $id)
    {
        foreach ($subscriptionList as $subscription) {
            if ($subscription['id'] == $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create new BillingSubscription entity
     *
     * @param Provider     $provider
     * @param User         $user
     * @param Plan         $plan
     * @param Subscription $subscription
     *
     * @throws BillingsException
     *
     * @return BillingsSubscription
     */
    protected function getNewBillingSubscription(User $user, Plan $plan, Stripe\Subscription $subscription, $subscription_billing_uuid)
    {
        $billingSubscription = new BillingsSubscription();
        $billingSubscription->setSubscriptionBillingUuid($subscription_billing_uuid);
        $billingSubscription->setProviderId($this->provider->getId());
        $billingSubscription->setUserId($user->getId());
        $billingSubscription->setPlanId($plan->getId());
        $billingSubscription->setSubUid($subscription['id']);
        $billingSubscription->setSubStatus($this->getStatusFromProvider($subscription));
        $billingSubscription->setSubActivatedDate($this->createDate($subscription['created']));
        $billingSubscription->setSubCanceledDate($this->createDate($subscription['canceled_at']));
        $billingSubscription->setSubExpiresDate(NULL);
        $billingSubscription->setSubPeriodStartedDate($this->createDate($subscription['current_period_start']));
        $billingSubscription->setSubPeriodEndsDate($this->createDate($subscription['current_period_end']));
        $billingSubscription->setDeleted(false);
        return $billingSubscription;
    }

    protected function log($message, array $values =  [])
    {
        $message = vsprintf($message, $values);
        config::getLogger()->addInfo('STRIPE - '.$message);
    }
    
    public function doExpireSubscription(BillingsSubscription $subscription, ExpireSubscriptionRequest $expireSubscriptionRequest) {
    	try {
    		config::getLogger()->addInfo("stripe subscription expiring...");
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
    			if($expireSubscriptionRequest->getOrigin() == 'api') {
    				$providerPlan = PlanDAO::getPlanById($subscription->getPlanId());
    				$internalPlan = InternalPlanDAO::getInternalPlanById($providerPlan->getInternalPlanId());
    				if($internalPlan == NULL) {
    					$msg = "plan with id=".$subscription->getPlanId()." for provider ".$this->provider->getName()." is not linked to an internal plan";
    					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
    				}
    				if($internalPlan->getCycle() == PlanCycle::auto) {
    					$user = UserDAO::getUserById($subscription->getUserId());
    					if($user == NULL) {
    						$msg = $msg = "unknown user with id : ".$subscription->getUserId();
    						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
    					}
    					$providerSubscription = $this->getSubscription($subscription->getSubUid(), $user);
    					$providerSubscription->cancel();
    					$providerSubscription->save();
    				}
    			}
    			if(in_array($expireSubscriptionRequest->getOrigin(), ['api', 'hook'])) {
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
    						/* declined status should never happens with stripe */
    						$msg = "cannot refund a transaction in status=".$transaction->getTransactionStatus();
    						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
    						break;
    					case BillingsTransactionStatus::failed :
    						$msg = "cannot refund a transaction in status=".$transaction->getTransactionStatus();
    						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
    						break;
    					case BillingsTransactionStatus::canceled :
							/* canceled status should never happens with stripe */
    						$msg = "cannot refund a transaction in status=".$transaction->getTransactionStatus();
    						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
    						break;
    					case BillingsTransactionStatus::void :
    						/* void status should never happens with stripe, BUT ignore it because there is nothing to refund here */
    						//nothing to do
    						break;
    					default :
    						$msg = "unknown transaction status=".$transaction->getTransactionStatus();
    						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
    						break;
    				}
    			}
    		}
    		//
    		$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
    		config::getLogger()->addInfo("stripe subscription expiring done successfully for stripe_subscription_uuid=".$subscription->getSubUid());
    	} catch(BillingsException $e) {
    		$msg = "a billings exception occurred while expiring a stripe subscription for stripe_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
    		config::getLogger()->addError("stripe subscription expiring failed : ".$msg);
    		throw $e;
    	} catch(Exception $e) {
    		$msg = "an unknown exception occurred while expiring a stripe subscription for stripe_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
    		config::getLogger()->addError("stripe subscription expiring failed : ".$msg);
    		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
    	}
    	return($this->doFillSubscription($subscription));
    }
    
    private function getApiSubscriptionByUuid(array $api_subscriptions, $subUuid) {
    	foreach ($api_subscriptions as $api_subscription) {
    		if($api_subscription['id'] == $subUuid) {
    			return($api_subscription);
    		}
    	}
    	return(NULL);
    }
    
    public function updateDbSubscriptionFromApiSubscription(User $user, 
    		UserOpts $userOpts, 
    		Provider $provider, 
    		InternalPlan $internalPlan, 
    		InternalPlanOpts $internalPlanOpts, 
    		Plan $plan, PlanOpts $planOpts, 
    		\Stripe\Subscription $api_subscription, 
    		BillingsSubscription $db_subscription, 
    		$updateType, $updateId) 
    {
    	config::getLogger()->addInfo($this->provider->getName()." dbsubscription update for userid=".$user->getId().", ".$this->provider->getName()."_subscription_uuid=".$api_subscription['id'].", id=".$db_subscription->getId()."...");
    	//UPDATE
    	
    	config::getLogger()->addInfo($this->provider->getName()." dbsubscription update for userid=".$user->getId().", ".$this->provider->getName()."_subscription_uuid=".$api_subscription['id'].", id=".$db_subscription->getId()." done successfully");
    	return($this->doFillSubscription($db_subscription));
    }
    
    public function doRedeemCoupon(BillingsSubscription $subscription, RedeemCouponRequest $redeemCouponRequest) {
    	try {
    		config::getLogger()->addInfo("redeeming a coupon for stripe_subscription_uuid=".$subscription->getSubUid()."...");
    		//
    		$user = UserDAO::getUserById($subscription->getUserId());
    		$internalPlan = InternalPlanDAO::getInternalPlanByProviderPlanId($subscription->getPlanId());
    		//
    		$couponsInfos = $this->getCouponInfos($redeemCouponRequest->getCouponCode(), $user, $internalPlan, new CouponTimeframe(CouponTimeframe::onSubLifetime));
    		//
    		$subscriptionData = array();
    		$subscriptionData['coupon'] = $couponsInfos['providerCouponsCampaign']->getExternalUuid();
    		\Stripe\Subscription::update($subscription->getSubUid(), $subscriptionData);
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
    		config::getLogger()->addInfo("redeeming a coupon for stripe_subscription_uuid=".$subscription->getSubUid()." done successfully");
    	} catch(BillingsException $e) {
    		$msg = "a billings exception occurred while redeeming a coupon for stripe_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
    		config::getLogger()->addError("redeeming a coupon failed : ".$msg);
    		throw $e;
		} catch(Exception $e) {
    		$msg = "an unknown exception occurred while redeeming a coupon for stripe_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
    		config::getLogger()->addError("redeeming a coupon failed : ".$msg);
    		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
    	}
    	return($this->doFillSubscription($subscription));
    }
	    
}

?>