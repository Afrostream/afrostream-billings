<?php
require_once __DIR__ . '/../../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/utils.php';
require_once __DIR__ . '/../../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../../db/dbGlobal.php';
require_once __DIR__.'/HookInterface.php';

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
        $previousAttributes = $event['data']['previous_attributes'];
        
        $billingSubscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($provider->getId(), $subscription['id']);

        // check previous attribute to see if a plan have changed
        if (!empty($previousAttributes['plan'])) {
            $newProviderPlan = PlanDAO::getPlanByUuid($provider->getId(), $subscription['plan']['id']);

            if (empty($newProviderPlan)) {
                throw new BillingsException(ExceptionType::internal , sprintf('Unknow subscription %s provided by stripe', $subscription['plan']['id']));
            }

            $billingSubscription->setPlanId($newProviderPlan->getId());
        }

        BillingsSubscriptionDAO::updateBillingsSubscription($billingSubscription);
    }
}