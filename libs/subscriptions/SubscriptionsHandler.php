<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/providers/recurly/subscriptions/RecurlySubscriptionsHandler.php';
require_once __DIR__ . '/../../libs/providers/gocardless/subscriptions/GocardlessSubscriptionsHandler.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';

class SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription($userid, $internal_plan_uuid, BillingInfoOpts $billingInfoOpts) {
		config::getLogger()->addInfo("subscription creation...");
		$user = UserDAO::getUserById($userid);
		if($user == NULL) {
			$msg = "unknown userid : ".$userid;
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		
		$userOpts = UserOptsDAO::getUserOptsByUserid($userid);
		
		$internal_plan = InternalPlanDAO::getInternalPlanByUuid($internal_plan_uuid);
		if($internal_plan == NULL) {
			$msg = "unknown internal_plan_uuid : ".$internal_plan_uuid;
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}

		$provider = ProviderDAO::getProviderById($user->getProviderId());
		if($provider == NULL) {
			$msg = "unknown provider with id : ".$user->getProviderId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		
		$provider_plan_id = InternalPlanLinksDAO::getInternalPlanLink($internal_plan->getId(), $provider->getId());
		if($provider_plan_id == NULL) {
			$msg = "unknown plan : ".$internal_plan_uuid." for provider : ".$provider->getName();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		
		$provider_plan = PlanDAO::getPlanById($provider_plan_id);
		if($provider_plan == NULL) {
			$msg = "unknown plan with id : ".$provider_plan_id;
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$provider_plan_opts = PlanOptsDAO::getPlanOptsByPlanId($provider_plan->getId());
		
		//subscription creation provider side
		config::getLogger()->addInfo("subscription creation...provider creation...");
		switch($provider->getName()) {
			case 'recurly':
				$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
				$sub_uuid = $recurlySubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider_plan, $provider_plan_opts, $billingInfoOpts);
				break;
			case 'gocardless':
				$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler();
				$sub_uuid = $gocardlessSubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider_plan, $provider_plan_opts, $billingInfoOpts);
				break;
			case 'celery' :
				$msg = "unsupported feature for provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
			default:
				$msg = "unsupported feature for provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
		config::getLogger()->addInfo("subscription creation...provider creation done successfully, provider_subscription_uuid=".$sub_uuid);
		//subscription created provider side, save it in billings database
		config::getLogger()->addInfo("subscription creation...database savings...");
		//TODO : should not have yet a switch here (later)
		//START TRANSACTION
		$db_subscription = NULL;
		pg_query("BEGIN");
		switch($provider->getName()) {
			case 'recurly':
				$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
				$db_subscription = $recurlySubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $provider, $provider_plan, $provider_plan_opts, $sub_uuid, 'api', 0);
				break;
			case 'gocardless':
				$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler();
				$db_subscription = $gocardlessSubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $provider, $provider_plan, $provider_plan_opts, $sub_uuid, 'api', 0);
				break;
			case 'celery' :
				$msg = "unsupported feature for provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
			default:
				$msg = "unsupported feature for provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
		//COMMIT
		pg_query("COMMIT");
		config::getLogger()->addInfo("subscription creation...database savings done successfully");
		config::getLogger()->addInfo("subscription creation done successfully, db_subscription_id=".$db_subscription->getId());
		return($db_subscription);
	}
	
	public function doUpdateUserSubscriptions($userid) {
		
		$user = UserDAO::getUserById($userid);
		if($user == NULL) {
			$msg = "unknown userid : ".$userid;
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
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