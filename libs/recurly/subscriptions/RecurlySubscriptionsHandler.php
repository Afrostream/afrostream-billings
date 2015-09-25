<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../libs/recurly/db/dbRecurly.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';

class RecurlySubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doUpdateUserSubscriptions(User $user) {
		//
		Recurly_Client::$subdomain = RECURLY_API_SUBDOMAIN;
		Recurly_Client::$apiKey = RECURLY_API_KEY;
		//
		//provider
		$provider = ProviderDAO::getProviderByName('recurly');
		if($provider == NULL) {
			$msg = "provider named 'recurly' not found";
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		try {
			$api_subscriptions = Recurly_SubscriptionList::getForAccount($user->getAccountCode());
			$db_subscriptions = SubscriptionDAO::getSubscriptionByUserId($provider->getId(), $user->getId());
			foreach ($api_subscriptions as $api_subscription) {
				print "Subscription: ".$api_subscription->uuid."\n";
				//print "Subscription: ".$subscription->uuid."\n";
				//SubscriptionDAO::getSubscriptionBySubUuid($providerId, $sub_uuid)
			}
		} catch (Recurly_NotFoundError $e) {
			print "Account Not Found: $e";
		}
	}
	
	//function getDbSubscriptionByUuid(array)
}

?>