<?php

require_once __DIR__ . '/../../config/config.php';
//require_once __DIR__ . '/../../libs/recurly/db/dbRecurly.php';
require_once __DIR__ . '/../../libs/recurly/subscriptions/RecurlySubscriptionsHandler.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';

class SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription($userid, $internal_plan_uuid) {
		
		$user = UserDAO::getUserById($userid);
		if($user == NULL) {
			$msg = "unknown userid : ".$userid;
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		
		$internal_plan = InternalPlanDAO::getInternalPlanByUuid($internal_plan_uuid);
		if($internal_plan == NULL) {
			$msg = "unknown internal_plan_uuid : ".$internal_plan_uuid;
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}

		$provider = ProviderDAO::getProviderById($user->getProviderId());
		
		$provider_plan_id = InternalPlanLinksDAO::getInternalPlanLink($internal_plan->getId(), $provider->getId());
		if($provider_plan_id == NULL) {
			$msg = "unknown plan : ".$internal_plan_uuid." for provider : ".$provider->getName();
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		$provider_plan = PlanDAO::getPlanById($provider_plan_id);
		//
		
	}
	
	public function doUpdateUserSubscriptions($userid) {
		
		$user = UserDAO::getUserById($userid);
		if($user == NULL) {
			$msg = "unknown userid : ".$userid;
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		
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