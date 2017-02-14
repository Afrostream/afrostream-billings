<?php

require_once __DIR__ . '/../../db/dbGlobal.php';
//<-- subscriptions -->
require_once __DIR__ . '/subscriptions/ProviderSubscriptionsHandler.php';
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
//<-- transactions -->
require_once __DIR__ . '/transactions/ProviderTransactionsHandler.php';
require_once __DIR__ . '/../recurly/transactions/RecurlyTransactionsHandler.php';
require_once __DIR__ . '/../gocardless/transactions/GocardlessTransactionsHandler.php';
require_once __DIR__ . '/../stripe/transactions/StripeTransactionsHandler.php';
require_once __DIR__ . '/../braintree/transactions/BraintreeTransactionsHandler.php';
require_once __DIR__ . '/../wecashup/transactions/WecashupTransactionsHandler.php';
//<-- users -->
require_once __DIR__ . '/users/ProviderUsersHandler.php';
require_once __DIR__ . '/../celery/users/CeleryUsersHandler.php';
require_once __DIR__ . '/../recurly/users/RecurlyUsersHandler.php';
require_once __DIR__ . '/../gocardless/users/GocardlessUsersHandler.php';
require_once __DIR__ . '/../bachat/users/BachatUsersHandler.php';
require_once __DIR__ . '/../afr/users/AfrUsersHandler.php';
require_once __DIR__ . '/../cashway/users/CashwayUsersHandler.php';
require_once __DIR__ . '/../orange/users/OrangeUsersHandler.php';
require_once __DIR__ . '/../bouygues/users/BouyguesUsersHandler.php';
require_once __DIR__ . '/../stripe/users/StripeUsersHandler.php';
require_once __DIR__ . '/../braintree/users/BraintreeUsersHandler.php';
require_once __DIR__ . '/../netsize/users/NetsizeUsersHandler.php';
require_once __DIR__ . '/../wecashup/users/WecashupUsersHandler.php';

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
				$providerSubscriptionsHandlerInstance = new ProviderSubscriptionsHandler($provider);
				break;
		}
		return($providerSubscriptionsHandlerInstance);
	}
	
	public static function getProviderTransactionsHandlerInstance(Provider $provider) {
		$providerTransactionsHandlerInstance = NULL;
		switch($provider->getName()) {
			case 'recurly' :
				$providerTransactionsHandlerInstance = new RecurlyTransactionsHandler($provider);
				break;
			case 'gocardless' :
				$providerTransactionsHandlerInstance = new GocardlessTransactionsHandler($provider);
				break;
			case 'stripe':
				$providerTransactionsHandlerInstance = new StripeTransactionsHandler($provider);
				break;
			case 'braintree' :
				$providerTransactionsHandlerInstance = new BraintreeTransactionsHandler($provider);
				break;
			case 'wecashup' :
				$providerTransactionsHandlerInstance = new WecashupTransactionsHandler($provider);
				break;
			default:
				$providerTransactionsHandlerInstance = new ProviderTransactionsHandler($provider);
				break;
		}
		return($providerTransactionsHandlerInstance);
	}
	
	public static function getProviderUsersHandlerInstance(Provider $provider) {
		$providerUsersHandlerInstance = NULL;
		switch($provider->getName()) {
			case 'celery' :
				$providerUsersHandlerInstance = new CeleryUsersHandler($provider);
				break;
			case 'recurly' :
				$providerUsersHandlerInstance = new RecurlyUsersHandler($provider);
				break;
			case 'gocardless' :
				$providerUsersHandlerInstance = new GocardlessUsersHandler($provider);
				break;
			case 'bachat' :
				$providerUsersHandlerInstance = new BachatUsersHandler($provider);
				break;
			case 'afr' :
				$providerUsersHandlerInstance = new AfrUsersHandler($provider);
				break;
			case 'cashway' :
				$providerUsersHandlerInstance = new CashwayUsersHandler($provider);
				break;
			case 'orange' :
				$providerUsersHandlerInstance = new OrangeUsersHandler($provider);
				break;
			case 'bouygues' :
				$providerUsersHandlerInstance = new BouyguesUsersHandler($provider);
				break;
			case 'stripe' :
				$providerUsersHandlerInstance = new StripeUsersHandler($provider);
				break;
			case 'braintree' :
				$providerUsersHandlerInstance = new BraintreeUsersHandler($provider);
				break;
			case 'netsize' :
				$providerUsersHandlerInstance = new NetsizeUsersHandler($provider);
				break;
			case 'wecashup' :
				$providerUsersHandlerInstance = new WecashupUsersHandler($provider);
				break;
			default:
				$providerUsersHandlerInstance = new ProviderUsersHandler($provider);
				break;
		}
		return($providerUsersHandlerInstance);
	}
	
}

?>