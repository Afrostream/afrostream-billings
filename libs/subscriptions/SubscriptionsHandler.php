<?php

require_once __DIR__ . '/../../config/config.php';
//require_once __DIR__ . '/../../libs/recurly/db/dbRecurly.php';
require_once __DIR__ . '/../../libs/recurly/subscriptions/RecurlySubscriptionsHandler.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';

class SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription($userid, $internal_plan_uuid, BillingInfoOpts $billingInfoOpts) {
		
		$user = UserDAO::getUserById($userid);
		if($user == NULL) {
			$msg = "unknown userid : ".$userid;
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		
		$userOpts = UserOptsDAO::getUserOptsByUserid($userid);
		
		$internal_plan = InternalPlanDAO::getInternalPlanByUuid($internal_plan_uuid);
		if($internal_plan == NULL) {
			$msg = "unknown internal_plan_uuid : ".$internal_plan_uuid;
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}

		$provider = ProviderDAO::getProviderById($user->getProviderId());
		if($provider == NULL) {
			$msg = "unknown provider with id : ".$user->getProviderId();
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		
		$provider_plan_id = InternalPlanLinksDAO::getInternalPlanLink($internal_plan->getId(), $provider->getId());
		if($provider_plan_id == NULL) {
			$msg = "unknown plan : ".$internal_plan_uuid." for provider : ".$provider->getName();
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		
		$provider_plan = PlanDAO::getPlanById($provider_plan_id);
		if($provider_plan == NULL) {
			$msg = "unknown plan with id : ".$provider_plan_id;
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		
		//subscription creation provider side
		switch($provider->getName()) {
			case 'recurly':
				$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
				$recurly_subscription = $recurlySubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider_plan, $billingInfoOpts);
				break;
			case 'celery' :
				$msg = "unsupported feature for provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new Exception($msg);
				break;
			default:
				$msg = "unsupported feature for provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new Exception($msg);
				break;
		}
		//subscription created provider side, save it in billings database
		//TODO : should not have yet a switch here (later)
		//START TRANSACTION
		$db_subscription = NULL;
		pg_query("BEGIN");
		switch($provider->getName()) {
			case 'recurly':
				$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
				$db_subscription = $recurlySubscriptionsHandler->createDbSubscriptionFromApiSubscription($user, $provider, $provider_plan, $recurly_subscription, 'api', 0);
				break;
			case 'celery' :
				$msg = "unsupported feature for provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new Exception($msg);
				break;
			default:
				$msg = "unsupported feature for provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new Exception($msg);
				break;
		}
		//COMMIT
		pg_query("COMMIT");
		return($db_subscription);
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