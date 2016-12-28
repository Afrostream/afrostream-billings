<?php

require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../recurly/subscriptions/RecurlySubscriptionsHandler.php';
require_once __DIR__ . '/../gocardless/subscriptions/GocardlessSubscriptionsHandler.php';
require_once __DIR__ . '/../celery/subscriptions/CelerySubscriptionsHandler.php';
require_once __DIR__ . '/../bachat/subscriptions/BachatSubscriptionsHandler.php';
require_once __DIR__ . '/../afr/subscriptions/AfrSubscriptionsHandler.php';
require_once __DIR__ . '/../cashway/subscriptions/CashwaySubscriptionsHandler.php';
require_once __DIR__ . '/../orange/subscriptions/OrangeSubscriptionsHandler.php';
require_once __DIR__ . '/../bouygues/subscriptions/BouyguesSubscriptionsHandler.php';
require_once __DIR__ . '/../stripe/subscriptions/StripeSubscriptionsHandler.php';
require_once __DIR__ . '/../braintree/subscriptions/BraintreeSubscriptionsHandler.php';
require_once __DIR__ . '/../netsize/subscriptions/NetsizeSubscriptionsHandler.php';
require_once __DIR__ . '/../wecashup/subscriptions/WecashupSubscriptionsHandler.php';

class ProviderHandlersBuilder {
	
	public static function getProviderSubscriptionsHandlerInstance(Provider $provider) {
		$providerSubscriptionsHandlerInstance = NULL;
		switch($provider->getName()) {
			case 'recurly' :
				$providerSubscriptionsHandlerInstance = new RecurlySubscriptionsHandler($provider);
				break;
			case 'gocardless' :
				$providerSubscriptionsHandlerInstance = new GocardlessSubscriptionsHandler($provider);
				break;
			case 'celery' :
				$providerSubscriptionsHandlerInstance = new CelerySubscriptionsHandler($provider);
				break;
			case 'bachat' :
				$providerSubscriptionsHandlerInstance = new BachatSubscriptionsHandler($provider);
				break;
			case 'afr' :
				$providerSubscriptionsHandlerInstance = new AfrSubscriptionsHandler($provider);
				break;
			case 'cashway' :
				$providerSubscriptionsHandlerInstance = new CashwaySubscriptionsHandler($provider);
				break;
			case 'orange' :
				$providerSubscriptionsHandlerInstance = new OrangeSubscriptionsHandler($provider);
				break;
			case 'bouygues' :
				$providerSubscriptionsHandlerInstance = new BouyguesSubscriptionsHandler($provider);
				break;
			case 'stripe':
				$providerSubscriptionsHandlerInstance = new StripeSubscriptionsHandler($provider);
				break;
			case 'braintree' :
				$providerSubscriptionsHandlerInstance = new BraintreeSubscriptionsHandler($provider);
				break;
			case 'netsize' :
				$providerSubscriptionsHandlerInstance = new NetsizeSubscriptionsHandler($provider);
				break;
			case 'wecashup' :
				$providerSubscriptionsHandlerInstance = new WecashupSubscriptionsHandler($provider);
				break;
			default:
				$msg = "unknown provider named : ".$provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
		return($providerSubscriptionsHandlerInstance);
	}
	
}

?>