<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

use \Stripe\Subscription;

class StripeSubscriptionsHandler extends SubscriptionsHandler
{
    public function __construct()
    {
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
     * @param string                   $subscriptionProviderUuid
     * @param BillingInfoOpts          $billingInfoOpts
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
        $subscriptionProviderUuid,
        BillingInfoOpts $billingInfoOpts,
        BillingsSubscriptionOpts $subOpts
    )
    {
        if ($subscriptionProviderUuid) {
            $subscription = $this->getSubscription($subscriptionProviderUuid, $user);
        } else {
            $subscription = $this->createSubscription($user, $plan, $subOpts);
        }

        return $this->getNewBillingSubscription($provider, $user, $plan, $subscription);
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
    public function createDbSubscriptionFromApiSubscription(
        BillingsSubscription $billingSubscription,
        BillingsSubscriptionOpts $subOpts = null,
        $updateType = 'api',
        $updateId = 0
    )
    {
        $billingSubscription->setUpdateType($updateType);
        $billingSubscription->setUpdateId($updateId);

        $billingSubscription = BillingsSubscriptionDAO::addBillingsSubscription($billingSubscription);

        if (!is_null($subOpts))
        {
            $subOpts->setSubId($billingSubscription->getId());
            BillingsSubscriptionOptsDAO::addBillingsSubscriptionOpts($subOpts);
        }
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

            $billingSubscription = $this->findRecordedSubscriptionByProviderId($recordedSubscriptions, $subscription['id']);

            // subscriptino not found, so we create it
            if (is_null($billingSubscription)) {
                $billingSubscription = $this->getNewBillingSubscription($provider, $user, $plan, $subscription);
                $this->createDbSubscriptionFromApiSubscription($billingSubscription);
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
        switch ($subscription['status']) {
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
    protected function createSubscription(User $user, Plan $plan, BillingsSubscriptionOpts $subOpts)
    {
        if (is_null($subOpts->getOpt('stripeToken'))) {
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Error while creating subscription. Missing stripe token');
        }

        $subscription = \Stripe\Subscription::create(array(
            "customer" => $user->getUserProviderUuid(),
            "plan" => $plan->getPlanUuid(),
            'source' => $subOpts->getOpt('stripeToken')
        ));

        if (empty($subscription['id'])) {
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Error while creating subscription.');
        }

        return $subscription;

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
    protected function getNewBillingSubscription(Provider $provider, User $user, Plan $plan, Subscription $subscription)
    {
        $billingSubscription = new BillingsSubscription();
        $billingSubscription->setSubscriptionBillingUuid(guid());
        $billingSubscription->setProviderId($provider->getId());
        $billingSubscription->setUserId($user->getId());
        $billingSubscription->setPlanId($plan->getId());
        $billingSubscription->setSubUid($subscription['id']);
        $billingSubscription->setSubStatus($this->getStatusFromProvider($subscription));
        $billingSubscription->setSubActivatedDate($this->createDate($subscription['created']));
        $billingSubscription->setSubCanceledDate($this->createDate($subscription['canceled_at']));
        $billingSubscription->setSubExpiresDate(null);
        $billingSubscription->setSubPeriodStartedDate($this->createDate($subscription['current_period_start']));
        $billingSubscription->setSubPeriodEndsDate($this->createDate($subscription['current_period_end']));
        $billingSubscription->setDeleted('false');

        return $billingSubscription;
    }
}
