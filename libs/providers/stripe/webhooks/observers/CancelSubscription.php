<?php

require_once __DIR__ . '/../../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/utils.php';
require_once __DIR__ . '/../../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../../db/dbGlobal.php';
require_once __DIR__ . '/HookInterface.php';
require_once __DIR__ . '/../../../global/ProviderHandlersBuilder.php';

use \Stripe\Event;

/**
 * Update billing subscription to mark as canceled
 */
class CancelSubscription implements HookInterface
{
    const REQUESTED_HOOK_TYPE = 'customer.subscription.deleted';

    public function __construct()
    {
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
            config::getLogger()->addInfo(sprintf('STRIPE - '.self::REQUESTED_HOOK_TYPE.' : unable to find subscription %s for provider %s', $subscription['id'], $provider->getId()));
            return null;
        }

        $oldSubscription = clone $billingSubscription;
        
        // update status to expired
        $billingSubscription->setSubStatus('expired');
        $billingSubscription->setSubExpiresDate($this->createDate($subscription['canceled_at']));
        //if not already set, SubCanceledDate = subExpiresDate when ends before the end of current_period, that generally means a payment failed
        if($billingSubscription->getSubCanceledDate() == NULL) {
	        if($subscription['ended_at'] != $subscription['current_period_end']) {
	        	$billingSubscription->setSubCanceledDate($this->createDate($subscription['canceled_at']));
	        }
        }
        $billingSubscription = BillingsSubscriptionDAO::updateBillingsSubscription($billingSubscription);
        
        $providerSubscriptionsHandlerInstance = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
        
        $providerSubscriptionsHandlerInstance->doSendSubscriptionEvent($oldSubscription, $billingSubscription);
        
        config::getLogger()->addInfo('STRIPE - '.self::REQUESTED_HOOK_TYPE.' : expire subscription #'.$billingSubscription->getId());
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

?>