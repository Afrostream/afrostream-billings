<?php
require_once __DIR__ . '/../../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/utils.php';
require_once __DIR__ . '/../../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../../db/dbGlobal.php';
require_once __DIR__.'/HookInterface.php';
require_once __DIR__ . '/../../subscriptions/StripeSubscriptionsHandler.php';

use \Stripe\Event;

/**
 * Class UpdateSubscription
 */
class UpdateSubscription implements HookInterface
{
    const REQUESTED_HOOK_TYPE = 'customer.subscription.updated';

    public function event(Event $event, Provider $provider)
    {
        if ($event['type'] != self::REQUESTED_HOOK_TYPE) {
            return;
        }

        if ($event['data']['object']['object'] !== 'subscription') {
            return null;
        }

        $subscription = $event['data']['object'];

        $billingSubscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($provider->getId(), $subscription['id']);

        if (empty($billingSubscription)) {
            config::getLogger()->addInfo(sprintf('STRIPE - customer.subscription.updated : unable to find subscription %s for provider %s', $subscription['id'], $provider->getId()));
            return null;
        }

        $newProviderPlan = PlanDAO::getPlanByUuid($provider->getId(), $subscription['plan']['id']);
        if (empty($newProviderPlan)) {
            config::getLogger()->addInfo(sprintf('STRIPE - customer.subscription.updated : unable to find subscription plan %s for provider %s', $subscription['plan']['id'], $provider->getId()));
            return null;
        }

        $status = StripeSubscriptionsHandler::getMappedStatus($subscription['status']);
        if (empty($status)) {
            config::getLogger()->addInfo(sprintf('STRIPE - customer.subscription.updated : unknown subscription status %s', $subscription['status']));
            return null;
        }

        if (!empty($subscription['canceled_at'])) {
            $status = 'canceled';
        }
        
        $billingSubscription->setPlanId($newProviderPlan->getId());
        $billingSubscription->setSubStatus($status);
        $billingSubscription->setSubCanceledDate($this->createDate($subscription['canceled_at']));
        $billingSubscription->setSubPeriodStartedDate($this->createDate($subscription['current_period_start']));
        $billingSubscription->setSubPeriodEndsDate($this->createDate($subscription['current_period_end']));

        BillingsSubscriptionDAO::updateBillingsSubscription($billingSubscription);
        config::getLogger()->addInfo('STRIPE - customer.subscription.updated : update subscription '.$billingSubscription->getId());
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
}