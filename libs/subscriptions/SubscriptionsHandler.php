<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/recurly/db/dbRecurly.php';
require_once __DIR__ . '/../../libs/recurly/subscriptions/RecurlySubscriptionsHandler.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';

class SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doUpdateUserSubscriptions(User $user) {
		$provider = ProviderDAO::getProviderByName($user->getBillingProvider());
		
		if($provider == NULL) {
			//todo
		}
		
		switch($provider->getName()) {
			case 'recurly':
				$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
				$recurlySubscriptionsHandler->doUpdateUserSubscriptions($user);
				break;
			default:
				//todo
				break;
		}
	}

}

?>