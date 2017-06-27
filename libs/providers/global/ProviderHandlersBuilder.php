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
require_once __DIR__ . '/../google/subscriptions/GoogleSubscriptionsHandler.php';
//<-- transactions -->
require_once __DIR__ . '/transactions/ProviderTransactionsHandler.php';
require_once __DIR__ . '/../recurly/transactions/RecurlyTransactionsHandler.php';
require_once __DIR__ . '/../gocardless/transactions/GocardlessTransactionsHandler.php';
require_once __DIR__ . '/../stripe/transactions/StripeTransactionsHandler.php';
require_once __DIR__ . '/../braintree/transactions/BraintreeTransactionsHandler.php';
require_once __DIR__ . '/../wecashup/transactions/WecashupTransactionsHandler.php';
require_once __DIR__ . '/../google/transactions/GoogleTransactionsHandler.php';
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
require_once __DIR__ . '/../google/users/GoogleUsersHandler.php';
//<-- couponsCampaigns -->
require_once __DIR__ . '/couponsCampaigns/ProviderCouponsCampaignsHandler.php';
require_once __DIR__ . '/../recurly/couponsCampaigns/RecurlyCouponsCampaignsHandler.php';
require_once __DIR__ . '/../stripe/couponsCampaigns/StripeCouponsCampaignsHandler.php';
require_once __DIR__ . '/../braintree/couponsCampaigns/BraintreeCouponsCampaignsHandler.php';
require_once __DIR__ . '/../afr/couponsCampaigns/AfrCouponsCampaignsHandler.php';
//<-- plans -->
require_once __DIR__ . '/plans/ProviderPlansHandler.php';
//require_once __DIR__ . '/../celery/plans/CeleryPlansHandler.php';
require_once __DIR__ . '/../recurly/plans/RecurlyPlansHandler.php';
require_once __DIR__ . '/../gocardless/plans/GocardlessPlansHandler.php';
require_once __DIR__ . '/../bachat/plans/BachatPlansHandler.php';
require_once __DIR__ . '/../afr/plans/AfrPlansHandler.php';
require_once __DIR__ . '/../cashway/plans/CashwayPlansHandler.php';
//TODO : require_once __DIR__ . '/../orange/plans/OrangePlansHandler.php';
//TODO : require_once __DIR__ . '/../bouygues/plans/BouyguesPlansHandler.php';
require_once __DIR__ . '/../stripe/plans/StripePlansHandler.php';
require_once __DIR__ . '/../braintree/plans/BraintreePlansHandler.php';
//TODO : require_once __DIR__ . '/../netsize/plans/NetsizePlansHandler.php';
//TODO : require_once __DIR__ . '/../wecashup/plans/WecashupPlansHandler.php';
//TODO : require_once __DIR__ . '/../google/plans/GooglePlansHandler.php';
//<-- coupons -->
require_once __DIR__ . '/coupons/ProviderCouponsHandler.php';
require_once __DIR__ . '/../cashway/coupons/CashwayCouponsHandler.php';
require_once __DIR__ . '/../afr/coupons/AfrCouponsHandler.php';
//<-- webhooks -->
require_once __DIR__ . '/webhooks/ProviderWebHooksHandler.php';
require_once __DIR__ . '/../recurly/webhooks/RecurlyWebHooksHandler.php';
require_once __DIR__ . '/../gocardless/webhooks/GocardlessWebHooksHandler.php';
require_once __DIR__ . '/../cashway/webhooks/CashwayWebHooksHandler.php';
require_once __DIR__ . '/../stripe/webhooks/StripeWebHooksHandler.php';
require_once __DIR__ . '/../braintree/webhooks/BraintreeWebHooksHandler.php';
require_once __DIR__ . '/../netsize/webhooks/NetsizeWebHooksHandler.php';
require_once __DIR__ . '/../wecashup/webhooks/WecashupWebHooksHandler.php';
require_once __DIR__ . '/../bachat/webhooks/BachatWebHooksHandler.php';

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
			case 'google' :
				$providerSubscriptionsHandlerInstance = new GoogleSubscriptionsHandler($provider);
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
			case 'google' :
				$providerTransactionsHandlerInstance = new GoogleTransactionsHandler($provider);
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
			case 'google' :
				$providerUsersHandlerInstance = new GoogleUsersHandler($provider);
				break;
			default:
				$providerUsersHandlerInstance = new ProviderUsersHandler($provider);
				break;
		}
		return($providerUsersHandlerInstance);
	}
	
	public static function getProviderCouponsCampaignsHandlerInstance(Provider $provider) {
		$providerCouponsCampaignsHandlerInstance = NULL;
		switch($provider->getName()) {
			case 'recurly' :
				$providerCouponsCampaignsHandlerInstance = new RecurlyCouponsCampaignsHandler($provider);
				break;
			case 'stripe' :
				$providerCouponsCampaignsHandlerInstance = new StripeCouponsCampaignsHandler($provider);
				break;
			case 'braintree' :
				$providerCouponsCampaignsHandlerInstance = new BraintreeCouponsCampaignsHandler($provider);
				break;
			case 'afr' :
				$providerCouponsCampaignsHandlerInstance = new AfrCouponsCampaignsHandler($provider);
				break;
			default :
				$providerCouponsCampaignsHandlerInstance = new ProviderCouponsCampaignsHandler($provider);
				break;
		}
		return($providerCouponsCampaignsHandlerInstance);
	}
	
	public static function getProviderPlansHandlerInstance(Provider $provider) {
		$providerPlansHandlerInstance = NULL;
		switch($provider->getName()) {
			case 'recurly' :
				$providerPlansHandlerInstance = new RecurlyPlansHandler($provider);
				break;
			case 'gocardless' :
				$providerPlansHandlerInstance = new GocardlessPlansHandler($provider);
				break;
			case 'bachat' :
				$providerPlansHandlerInstance = new BachatPlansHandler($provider);
				break;
			case 'afr' :
				$providerPlansHandlerInstance = new AfrPlansHandler($provider);
				break;
			case 'cashway' :
				$providerPlansHandlerInstance = new CashwayPlansHandler($provider);
				break;
			case 'stripe' :
				$providerPlansHandlerInstance = new StripePlansHandler($provider);
				break;
			case 'braintree' :
				$providerPlansHandlerInstance = new BraintreePlansHandler($provider);
				break;
			default :
				$providerPlansHandlerInstance = new ProviderPlansHandler($provider);
				break;
		}
		return($providerPlansHandlerInstance);
	}
		
	public static function getProviderCouponsHandlerInstance(Provider $provider) {
		$providerCouponsHandlerInstance = NULL;
		switch($provider->getName()) {
			case 'cashway' :
				$providerCouponsHandlerInstance = new CashwayCouponsHandler($provider);
				break;
			case 'afr' :
				$providerCouponsHandlerInstance = new AfrCouponsHandler($provider);
				break;
			default :
				$providerCouponsHandlerInstance = new ProviderCouponsHandler($provider);
				break;
		}
		return($providerCouponsHandlerInstance);
	}
	
	public static function getProviderWebHooksHandlerInstance(Provider $provider) {
		$providerWebHooksHandlerInstance  = NULL;
		switch($provider->getName()) {
			case 'recurly' :
				$providerWebHooksHandlerInstance = new RecurlyWebHooksHandler($provider);
				break;
			case 'gocardless' :
				$providerWebHooksHandlerInstance = new GocardlessWebHooksHandler($provider);
				break;
			case 'cashway' :
				$providerWebHooksHandlerInstance = new CashwayWebHooksHandler($provider);
				break;
			case 'stripe':
				$providerWebHooksHandlerInstance = new StripeWebHooksHandler($provider);
				break;
			case 'braintree' :
				$providerWebHooksHandlerInstance = new BraintreeWebHooksHandler($provider);
				break;
			case 'netsize' :
				$providerWebHooksHandlerInstance = new NetsizeWebHooksHandler($provider);
				break;
			case 'wecashup' :
				$providerWebHooksHandlerInstance = new WecashupWebHooksHandler($provider);
				break;
			case 'bachat' :
				$providerWebHooksHandlerInstance = new BachatWebHooksHandler($provider);
				break;
			default :
				$providerWebHooksHandlerInstance = new ProviderWebHooksHandler($provider);
				break;
		}
		return($providerWebHooksHandlerInstance);
	}
	
}

?>