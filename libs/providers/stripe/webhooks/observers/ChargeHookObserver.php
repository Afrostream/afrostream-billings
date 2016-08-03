<?php

require_once __DIR__ . '/../../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/utils.php';
require_once __DIR__ . '/../../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../../db/dbGlobal.php';
require_once __DIR__ . '/HookInterface.php';
require_once __DIR__ . '/../../transactions/StripeTransactionsHandler.php';

use \Stripe\Event;

/**
 * Class ChargeHookObserver
 */
class ChargeHookObserver implements HookInterface
{
    const REQUESTED_HOOK_TYPES = [
    	'charge.captured',
    	'charge.failed',
    	'charge.refunded',
    	'charge.succeeded',
    	'charge.updated'
    ];

    public function __construct()
    {
    }

    public function event(Event $event, Provider $provider)
    {
        if (!array_key_exists($event['type'], self::REQUESTED_HOOK_TYPES)) {
            return;
        }
        
        if ($event['data']['object']['object'] !== 'charge') {
        	return null;
        }

        $chargeId = $event['data']['object']['id'];
        
        $api_payment = \Stripe\Charge::retrieve($chargeId);
        
        $metadata = $api_payment->metadata->__toArray();
        $hasToBeProcessed = false;
        $isRecurlyTransaction = false;
        if(array_key_exists('recurlyTransactionId', $metadata)) {
        	$isRecurlyTransaction = true;
        }
        $hasToBeProcessed = !$isRecurlyTransaction;
        if($hasToBeProcessed) {    
	        $api_customer = NULL;
	        $user = NULL;
	        $userOpts = NULL;
	        if(isset($api_payment->customer)) {
	        	$api_customer = \Stripe\Customer::retrieve($api_payment->customer);
	        	$user = UserDAO::getUserByUserProviderUuid($provider->getId(), $api_payment->customer);
	        	if($user == NULL) {
	        		$msg = 'searching user with customer_provider_uuid='.$api_payment->customer.' failed, no user found';
	        		config::getLogger()->addError($msg);
	        		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
	        	}
	        	$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
	        }
	        $stripeTransactionsHandler = new StripeTransactionsHandler();
	        $stripeTransactionsHandler->createOrUpdateChargeFromProvider($user, $userOpts, $api_customer, $api_payment);
        }
    }

}