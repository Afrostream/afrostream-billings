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
        if (!in_array($event['type'], self::REQUESTED_HOOK_TYPES)) {
        	return;
        }
        
        config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' is being processed...');
        
        if ($event['data']['object']['object'] !== 'charge') {
        	config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' ignored, object is not a charge oject');
        	return null;
        }

        $chargeId = $event['data']['object']['id'];
        
        $api_payment = \Stripe\Charge::retrieve($chargeId);
        
        $metadata = $api_payment->metadata->__toArray();
        $hasToBeProcessed = false;
        $hasToBeIgnored = false;
        if(array_key_exists('AfrIgnore', $metadata)) {
        	$afrIgnore = $metadata['AfrIgnore'];
        	if($afrIgnore == 'true') {
        		$hasToBeIgnored = true;
        	}
        }
        $isRecurlyTransaction = false;
        if(array_key_exists('recurlyTransactionId', $metadata)) {
        	$isRecurlyTransaction = true;
        }
        $hasToBeProcessed = !$hasToBeIgnored && !$isRecurlyTransaction;
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
	        config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' sent to Handler...');
	        $stripeTransactionsHandler = new StripeTransactionsHandler();
	        $stripeTransactionsHandler->createOrUpdateChargeFromProvider($user, $userOpts, $api_customer, $api_payment, 'hook');
	        config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' sent to Handler done successfully');
        } else {
        	config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' has been ignored');
        }
        config::getLogger()->addInfo('STRIPE - Process new event id='.$event['id'].', type='.$event['type'].' has been processed successfully');
    }

}

?>