<?php

require_once __DIR__ . '/../../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/utils.php';
require_once __DIR__ . '/../../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../../db/dbGlobal.php';
require_once __DIR__ . '/HookInterface.php';
require_once __DIR__ . '/../../../global/ProviderHandlersBuilder.php';

use \Stripe\Event;

/**
 * Class InvoiceHookObserver
 */
class InvoiceHookObserver implements HookInterface
{
    const REQUESTED_HOOK_TYPES = [
    		'invoice.payment_failed'
    ];

    public function __construct() {
    }

    public function event(Event $event, Provider $provider) {
        if (!in_array($event['type'], self::REQUESTED_HOOK_TYPES)) {
        	return;
        }
        switch($event['type']) {
        	case 'invoice.payment_failed' :
        		$this->processPaymentFailed($event, $provider);
        		break;
        }
    }
    
    protected function processPaymentFailed(Event $event, Provider $provider) {
    	if(getEnv('STRIPE_WH_EVENT_'.strtoupper($event['type']).'_ENABLED') == 1) {
	    	config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' is being processed...');
	    	//
	    	$api_invoice_id = $event['data']['object']['id'];
	    	config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' api_invoice_id='.$api_invoice_id);
	    	$api_invoice = \Stripe\Invoice::retrieve($api_invoice_id);
	    	$hasNextPaymentAttempt = false;
	    	if(isset($api_invoice['next_payment_attempt'])) {
	    		config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' next_payment_attempt='.$api_invoice['next_payment_attempt']);
	    		$hasNextPaymentAttempt = true;
	    	}
	    	if($hasNextPaymentAttempt) {
		    	$api_subscription = NULL;
		    	if(isset($api_invoice['subscription'])) {
		    		config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' api_subscription_id='.$api_invoice['subscription']);
		    		$api_subscription = \Stripe\Subscription::retrieve($api_invoice['subscription']);
		    	} else {
		    		config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' no api_subscription_id found');
		    	}
		    	$billingSubscription = NULL;
		    	if(isset($api_subscription)) {
		    		$billingSubscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($provider->getId(), $api_subscription['id']);
		    	} else {
		    		config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' no api_subscription found');
		    	}
		    	if(isset($billingSubscription)) {
		    		config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' billing_subscription_uuid='.$billingSubscription->getSubscriptionBillingUuid());
		    		$providerSubscriptionsHandlerInstance = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
		    		$providerSubscriptionsHandlerInstance->doSendSubscriptionEvent($billingSubscription, $billingSubscription, 'FAILED_PAYMENT');
		    	} else {
		    		config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' no billingSubscription found');
		    	}
	    	} else {
	    		config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' no next_payment_attempt found');
	    	}
	    	//
	    	config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' has been processed successfully');
    	} else {
    		config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' has been ignored');
    	}
    }

}

?>