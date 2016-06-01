<?php
require_once __DIR__ . '/../../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/utils.php';
require_once __DIR__ . '/../../../../utils/BillingsException.php';
require_once __DIR__.'/HookInterface.php';

use \Stripe\Event;

class CancelSubscription implements HookInterface
{
    const REQUESTED_HOOK_TYPE = 'customer.subscription.deleted';

    public function event(Event $event, Provider $provider)
    {
        if ($event['type'] != self::REQUESTED_HOOK_TYPE) {
            return;
        }

        if (empty($event['data']['object']) || ($event['data']['object']['object'] !== 'subscription')) {
            return null;
        }

        $subscription = $event['data']['object'];
        $billingSubscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($provider->getId(), $subscription['id']);

        // update status to cancel
        $billingSubscription->setSubStatus('canceled');
        // take care date reflect the date of canceling subscption not the end of access
        $billingSubscription->setSubExpiresDate( new \DateTime(date('c', $subscription['canceled_at'])));
    }
    
}