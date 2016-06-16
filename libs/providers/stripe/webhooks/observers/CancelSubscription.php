<?php
require_once __DIR__ . '/../../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/utils.php';
require_once __DIR__ . '/../../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../../db/dbGlobal.php';
require_once __DIR__.'/HookInterface.php';

use \Stripe\Event;

/**
 * Update billing subscription to mark as canceled
 */
class CancelSubscription implements HookInterface
{
    const REQUESTED_HOOK_TYPE = 'customer.subscription.deleted';

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

        // update status to cancel
        $billingSubscription->setSubStatus('canceled');
        // take care date reflect the date of canceling subscription not the end of access
        $billingSubscription->setSubCanceledDate( new \DateTime(date('c', $subscription['canceled_at'])));

        BillingsSubscriptionDAO::updateBillingsSubscription($billingSubscription);

        config::getLogger()->addInfo('STRIPE - customer.subscription.deleted : cancel subscription #'.$billingSubscription->getId());
    }
    
}