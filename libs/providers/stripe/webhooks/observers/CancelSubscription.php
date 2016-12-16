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
        
        // update status to expired
        $billingSubscription->setSubStatus('expired');
        $billingSubscription->setSubExpiresDate($this->createDate($subscription['canceled_at']));
        //if not already set, SubCanceledDate = subExpiresDate when ends before the end of current_period, that generally means a payment failed
        if($billingSubscription->getSubCanceledDate() != NULL) {
	        if($subscription['ended_at'] == $subscription['current_period_end']) {   	
	        	$billingSubscription->setSubCanceledDate($this->createDate($subscription['canceled_at']));
	        }
        }
        $billingSubscription = BillingsSubscriptionDAO::updateBillingsSubscription($billingSubscription);

        $this->subscriptionHandler->doSendSubscriptionEvent($oldSubscription, $billingSubscription);
        
        config::getLogger()->addInfo('STRIPE - customer.subscription.deleted : expire subscription #'.$billingSubscription->getId());
    }
    
}

?>