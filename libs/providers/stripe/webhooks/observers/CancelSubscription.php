<?php

require_once __DIR__ . '/../../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/utils.php';
require_once __DIR__ . '/../../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../../db/dbGlobal.php';
require_once __DIR__.'/HookInterface.php';
require_once __DIR__ . '/../../../../subscriptions/SubscriptionsHandler.php';

use \Stripe\Event;

/**
 * Update billing subscription to mark as canceled
 */
class CancelSubscription implements HookInterface
{
    const REQUESTED_HOOK_TYPE = 'customer.subscription.deleted';

    protected $subscriptionHandler;

    public function __construct()
    {
        $this->subscriptionHandler = new SubscriptionsHandler();
    }
    
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
            config::getLogger()->addInfo(sprintf('STRIPE - customer.subscription.created : unable to find subscription %s for provider %s', $subscription['id'], $provider->getId()));
            return null;
        }

        $oldSubscription = clone $billingSubscription;
        
        // update status to cancel
        $billingSubscription->setSubStatus('expired');
        // take care date reflect the when event is receipt
        $billingSubscription->setSubExpiresDate( new \DateTime('now'));

        $billingSubscription = BillingsSubscriptionDAO::updateBillingsSubscription($billingSubscription);

        $this->subscriptionHandler->doSendSubscriptionEvent($oldSubscription, $billingSubscription);
        
        config::getLogger()->addInfo('STRIPE - customer.subscription.deleted : expire subscription #'.$billingSubscription->getId());
    }
    
}

?>