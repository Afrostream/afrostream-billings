<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';

use \Stripe\Subscription;

class StripeSubscriptionsHandler extends SubscriptionsHandler
{
	protected $provider = NULL;
	
    public function __construct()
    {
    	$this->provider = ProviderDAO::getProviderByName('stripe');
        \Stripe\Stripe::setApiKey(getenv('STRIPE_API_KEY'));
    }

    /**
     * Create new subscription to provider if subscriptionProviderUuid does not set
     *
     * @param User                     $user
     * @param UserOpts                 $userOpts
     * @param Provider                 $provider
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
     * @return BillingsSubscription
     */
    public function doCreateUserSubscription(
        User $user,
        UserOpts $userOpts,
        Provider $provider,
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
    	if (isset($subscriptionProviderUuid)) {
    		$subscription = $this->getSubscription($subscriptionProviderUuid, $user);
    	} else {
    		$metadata = [
    				'AfrSource' => 'afrBillingApi',
    				'AfrOrigin' => 'subscription',
    				'AfrSubscriptionBillingUuid' => $subscription_billing_uuid,
    				'AfrUserBillingUuid' => $user->getUserBillingUuid()
    		];
	        if ($internalPlan->getCycle() == PlanCycle::once) {
	            $subscription = $this->chargeCustomer($user, $plan, $subOpts, $internalPlan, $metadata);
            } else {
                $subscription = $this->createSubscription($user, $plan, $subOpts, $internalPlan, $metadata);
            }
        }

        return $this->getNewBillingSubscription($provider, $user, $plan, $subscription, $subscription_billing_uuid);
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
    
    
    public function createDbSubscriptionFromApiSubscription(User $user,
    		UserOpts $userOpts,
    		Provider $provider,
    		InternalPlan $internalPlan,
    		InternalPlanOpts $internalPlanOpts,
    		Plan $plan,
    		PlanOpts $planOpts,
    		BillingsSubscriptionOpts $subOpts = NULL,
    		BillingInfo $billingInfo = NULL,
    		$subscription_billing_uuid,
    		$billingSubscription,
    		$updateType,
    		$updateId)
    {
        $billingSubscription->setUpdateType($updateType);
        $billingSubscription->setUpdateId($updateId);

        $subscriptionInfos = $billingSubscription->jsonSerialize();
        $this->log(
            'record subscription. providerUuid: %s, user billing uuid: %s, user provider uuid: %s, internal plan: %s',
            [
                $subscriptionInfos['subscriptionProviderUuid'],
                $subscriptionInfos['user']['userBillingUuid'],
                $subscriptionInfos['user']['userProviderUuid'],
                $subscriptionInfos['internalPlan']['internalPlanUuid']
            ]
        );
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
        //<-- DATABASE -->
        //BILLING_INFO (NOT MANDATORY)
        if(isset($billingInfo)) {
        	$billingInfo = BillingInfoDAO::addBillingInfo($billingInfo);
        	$billingSubscription->setBillingInfoId($billingInfo->getId());
        }
        
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
        	if($userInternalCoupon->getId() == NULL) {
        		$userInternalCoupon = BillingUserInternalCouponDAO::addBillingUserInternalCoupon($userInternalCoupon);
        	}
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
        return $billingSubscription;
    }

    /**
     * Synchronize subscription between afrostream and provider for the given user
     * 
     * @param User     $user
     * @param UserOpts $userOpts
     *
     * @throws BillingsException
     */
    public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts)
    {
        $provider = ProviderDAO::getProviderById($user->getProviderId());
        if($provider == NULL) {
            throw new BillingsException(new ExceptionType(ExceptionType::internal), "Unknow provider id {$user->getProviderId()}");
        }
		
        $customer = \Stripe\Customer::retrieve($user->getUserProviderUuid());
        if (empty($customer['id'])) {
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Unknow customer');
        }
		
        $subscriptionList = $customer['subscriptions']['data'];
        $recordedSubscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());

        foreach ($subscriptionList as $subscription) {
            $providerPlanId = $subscription['plan']['id'];

            $plan = PlanDAO::getPlanByUuid($provider->getId(), $providerPlanId);
            if($plan == NULL) {
                $msg = "plan with uuid=".$providerPlanId." not found";
                throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
            }

            $planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
            $internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($plan->getId()));
            if($internalPlan == NULL) {
                $msg = "plan with uuid=".$providerPlanId." for provider ".$provider->getName()." is not linked to an internal plan";
                throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
            }
            $internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());

            $billingSubscription = $this->findRecordedSubscriptionByProviderId($recordedSubscriptions, $subscription['id']);

            // subscription not found, so we create it
            if (is_null($billingSubscription)) {
            	$subscription_billing_uuid = guid();
                $billingSubscription = $this->getNewBillingSubscription($provider, $user, $plan, $subscription, $subscription_billing_uuid);
                $this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider,
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
        }
    }

    /**
     * Fill subscription to set active status
     *
     * @param BillingsSubscription|NULL $subscription
     */
    public function doFillSubscription(BillingsSubscription $subscription = NULL)
    {
        if($subscription == NULL) {
            return;
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
    }

    /**
     * Cancel a subscription
     *
     * @param BillingsSubscription $billingSubscription
     * @param DateTime             $cancelDate
     */
    public function doCancelSubscription(BillingsSubscription $billingSubscription, \DateTime $cancelDate)
    {
        // already canceled, return gracefully
        if (in_array($billingSubscription->getSubStatus(), ['canceled', 'expired'])) {
            return(BillingsSubscriptionDAO::getBillingsSubscriptionById($billingSubscription->getId()));
        } else {

	        // get user
	        $user = UserDAO::getUserById($billingSubscription->getUserId());
	
	        $subscription = $this->getSubscription($billingSubscription->getSubUid(), $user);
	
	        $this->log('Cancel subscription id %s ', [$subscription['id']]);
	
	        $subscription->cancel(['at_period_end' => true]);
	        $subscription->save();
	
	        $billingSubscription->setSubCanceledDate($cancelDate);
	        $billingSubscription->setSubStatus('canceled');
	
	        return(BillingsSubscriptionDAO::updateBillingsSubscription($billingSubscription));
        }
    }


    /**
     * Change subscription owned by user
     *
     * @param BillingsSubscription $billingSubscription
     * @param InternalPlan         $internalPlan
     * @param InternalPlanOpts     $internalPlanOpts
     * @param Plan                 $plan
     * @param PlanOpts             $planOpts
     */
    public function doUpdateInternalPlan(
        BillingsSubscription $billingSubscription,
        InternalPlan $internalPlan,
        InternalPlanOpts $internalPlanOpts,
        Plan $plan,
        PlanOpts $planOpts
    )
    {
        $user = UserDAO::getUserById($billingSubscription->getUserId());

        $subscription = $this->getSubscription($billingSubscription, $user);
        $subscription->plan = $plan->getPlanUuid();

        $subscription->save();

        $billingSubscription->setPlanId($plan->getId());

        BillingsSubscriptionDAO::updateBillingsSubscription($billingSubscription);
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
   public function doReactivateSubscription(BillingsSubscription $billingSubscription)
   {
       // fill the subscription to set needed informations
       $this->doFillSubscription($billingSubscription);

       // always active , nothing to do
        if ($billingSubscription->getStatus() == 'active') {
            return;
        }

       try {
           // reactivable and canceled but still active, we rollback the canceling
           if ($billingSubscription->isReactivable() && $billingSubscription->getSubStatus() == 'canceled' && $billingSubscription->getIsActive() == 'yes') {
               $subscription = \Stripe\Subscription::retrieve($billingSubscription->getSubUid());
               $subscription->cancel_at_period_end = false;
               $subscription->save();
           }

           $billingSubscription->setSubStatus('active');
           $billingSubscription->setSubCanceledDate(null);

           BillingsSubscriptionDAO::updateBillingsSubscription($billingSubscription);
       } catch (\Exception $e) {
           throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
       }
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
    protected function getStatusFromProvider(Subscription $subscription)
    {
        $status = self::getMappedStatus($subscription['status']);

        if (is_null($status)) {
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Unknow stripe subscritpion status');
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
        if(array_key_exists('couponCode', $subOpts->getOpts())) {
        	$couponCode = $subOpts->getOpts()['couponCode'];
        	if(strlen($couponCode) > 0) {
        		$couponsInfos = $this->getCouponInfos($couponCode, $this->provider, $user, $internalPlan);
        		$subscriptionData['coupon'] = $couponsInfos['providerCouponsCampaign']->getPrefix();
        		$logMessage = 'Create subscription : customer : %s, plan : %s, source : %s, coupon : %s';
        	}
        }

        $this->log($logMessage, $subscriptionData);

        $subscription = \Stripe\Subscription::create($subscriptionData);

        if (empty($subscription['id'])) {
            $this->log('Error while creating subscription');
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Error while creating subscription.');
        }

        return $subscription;

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
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Error while creating subscription. Missing stripe token');
        }

        try {
            $this->log('Update customer : set source : '.$subOpts->getOpt('customerBankAccountToken'));

            // assign token to customer then charge him
            $customer = \Stripe\Customer::retrieve($user->getUserProviderUuid());
            $customer->source = $subOpts->getOpt('customerBankAccountToken');
            $customer->save();
			
            $chargeData = array(
                "amount" => $internalPlan->getAmountInCents(),
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

            $subscription = new Subscription();
            $subscription['id'] = guid();
            $subscription['created'] = $charge['created'];
            $subscription['canceled_at'] = null;
            $subscription['current_period_start'] = $charge['created'];
            $subscription['current_period_end'] = $this->calculateSubscriptionDateEnd($internalPlan, $charge['created']);
            $subscription['status'] = 'active';

            return $subscription;

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
                new BillingsException(new ExceptionType(ExceptionType::internal), 'Unsupported plan period unit');
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
    protected function getNewBillingSubscription(Provider $provider, User $user, Plan $plan, Subscription $subscription, $subscription_billing_uuid)
    {
        $billingSubscription = new BillingsSubscription();
        $billingSubscription->setSubscriptionBillingUuid($subscription_billing_uuid);
        $billingSubscription->setProviderId($provider->getId());
        $billingSubscription->setUserId($user->getId());
        $billingSubscription->setPlanId($plan->getId());
        $billingSubscription->setSubUid($subscription['id']);
        $billingSubscription->setSubStatus($this->getStatusFromProvider($subscription));
        $billingSubscription->setSubActivatedDate($this->createDate($subscription['created']));
        $billingSubscription->setSubCanceledDate($this->createDate($subscription['canceled_at']));
        $billingSubscription->setSubExpiresDate(NULL);
        $billingSubscription->setSubPeriodStartedDate($this->createDate($subscription['current_period_start']));
        $billingSubscription->setSubPeriodEndsDate($this->createDate($subscription['current_period_end']));
        $billingSubscription->setDeleted('false');
        return $billingSubscription;
    }

    protected function log($message, array $values =  [])
    {
        $message = vsprintf($message, $values);
        config::getLogger()->addInfo('STRIPE - '.$message);
    }
    
    public function doExpireSubscription(BillingsSubscription $subscription, DateTime $expires_date, $is_a_request = true) {
    	try {
    		config::getLogger()->addInfo("stripe subscription expiring...");
    		if(
    				$subscription->getSubStatus() == "expired"
    		)
    		{
    			//nothing todo : already done or in process
    		} else {
    			//
    			if($subscription->getSubPeriodEndsDate() > $expires_date) {
    				//exception
    				$msg = "cannot expire a subscription that has not ended yet";
    				config::getLogger()->addError($msg);
    				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
    			}
    			$subscription->setSubExpiresDate($expires_date);
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
    		config::getLogger()->addInfo("stripe subscription expiring done successfully for stripe_subscription_uuid=".$subscription->getSubUid());
    		return($subscription);
    	} catch(BillingsException $e) {
    		$msg = "a billings exception occurred while expiring a stripe subscription for stripe_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
    		config::getLogger()->addError("stripe subscription expiring failed : ".$msg);
    		throw $e;
    	} catch(Exception $e) {
    		$msg = "an unknown exception occurred while expiring a stripe subscription for stripe_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
    		config::getLogger()->addError("stripe subscription expiring failed : ".$msg);
    		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
    	}
    }
    
}

?>