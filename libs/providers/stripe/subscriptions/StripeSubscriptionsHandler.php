<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

use Stripe\StripeObject;

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
     * @param StripeObject $subscription
     *
     * @throws BillingsException
     *
     * @return string
     */
    protected function getStatusFromProvider(StripeObject $subscription)
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
     * @return string
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

}