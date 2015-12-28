<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/providers/celery/subscriptions/CelerySubscriptionsHandler.php';
require_once __DIR__ . '/../../libs/providers/recurly/subscriptions/RecurlySubscriptionsHandler.php';
require_once __DIR__ . '/../../libs/providers/gocardless/subscriptions/GocardlessSubscriptionsHandler.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';

class SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doGetSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid) {
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("subscription getting for subscriptionBillingUuid=".$subscriptionBillingUuid."...");
			//
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid);
			//
			$this->doFillSubscription($db_subscription);
			//
			config::getLogger()->addInfo("subscription getting for subscriptionBillingUuid=".$subscriptionBillingUuid." successfully done");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting a subscription for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscription getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a subscription for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscription getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_subscription);
	}
	
	public function doGetOrCreateSubscription($user_billing_uuid, $internal_plan_uuid, $subscription_provider_uuid, array $billing_info_opts_array) {
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("subscription creating...");
			$this->checkBillingInfoOptsArray($billing_info_opts_array);
			$billing_info_opts = new BillingInfoOpts();
			$billing_info_opts->setOpts($billing_info_opts_array);
			$user = UserDAO::getUserByUserBillingUuid($user_billing_uuid);
			if($user == NULL) {
				$msg = "unknown user_billing_uuid : ".$user_billing_uuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			
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
			
			$provider_plan_id = InternalPlanLinksDAO::getProviderPlanIdFromInternalPlan($internal_plan->getId(), $provider->getId());
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
			if(isset($subscription_provider_uuid)) {
				//check : Does this subscription_provider_uuid already exist in the Database ?
				$db_tmp_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($provider->getId(), $subscription_provider_uuid);
				if($db_tmp_subscription == NULL) {
					//nothing to do
				} else {
					//check if it is linked to the right user
					if($db_tmp_subscription->getUserId() != $user->getId()) {
						//Exception
						$msg = "subscription_provider_uuid=".$subscription_provider_uuid." is already linked to another user_reference_uuid";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					//check if it is linked to the right plan
					if($db_tmp_subscription->getPlanId() != $provider_plan->getId()) {
						//Exception
						$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." is not linked to the plan with provider_plan_uuid=".$provider_plan->getPlanUuid();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					//done
					$db_subscription = $db_tmp_subscription;
				}
			}
			if($db_subscription == NULL)
			{
				//subscription creating provider side
				config::getLogger()->addInfo("subscription creating...provider creating...");
				$sub_uuid = NULL;
				switch($provider->getName()) {
					case 'recurly' :
						$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
						$sub_uuid = $recurlySubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider, $provider_plan, $provider_plan_opts, $subscription_provider_uuid, $billing_info_opts);
						break;
					case 'gocardless' :
						$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler();
						$sub_uuid = $gocardlessSubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider, $provider_plan, $provider_plan_opts, $subscription_provider_uuid, $billing_info_opts);
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
				config::getLogger()->addInfo("subscription creating...provider creating done successfully, provider_subscription_uuid=".$sub_uuid);
				//subscription created provider side, save it in billings database
				config::getLogger()->addInfo("subscription creating...database savings...");
				//TODO : should not have yet a switch here (later)
				//START TRANSACTION
				pg_query("BEGIN");
				switch($provider->getName()) {
					case 'recurly' :
						$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
						$db_subscription = $recurlySubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $provider_plan, $provider_plan_opts, $sub_uuid, 'api', 0);
						break;
					case 'gocardless' :
						$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler();
						$db_subscription = $gocardlessSubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $provider_plan, $provider_plan_opts, $sub_uuid, 'api', 0);
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
			}
			config::getLogger()->addInfo("subscription creating...database savings done successfully");
			//
			$this->doFillSubscription($db_subscription);
			//
			config::getLogger()->addInfo("subscription creating done successfully, db_subscription_id=".$db_subscription->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a subscription user for user_billing_uuid=".$user_billing_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscription creating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a subscription for user_billing_uuid=".$user_billing_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscription creating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_subscription);
	}
	
	public function doGetUserSubscriptionsByUser(User $user) {
		try {
			config::getLogger()->addInfo("subscriptions getting for userid=".$user->getId()."...");
			$subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
			$this->doFillSubscriptions($subscriptions);
			config::getLogger()->addInfo("subscriptions getting for userid=".$user->getId()." done sucessfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting subscriptions for userid=".$user->getId().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscriptions getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting subscriptions for userid=".$user->getId().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscriptions getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($subscriptions);
	}
	
	public function doUpdateUserSubscriptionsByUser(User $user) {
		try {
			config::getLogger()->addInfo("dbsubscriptions updating for userid=".$user->getId()."...");
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			
			$provider = ProviderDAO::getProviderById($user->getProviderId());
			
			if($provider == NULL) {
				$msg = "unknown provider id : ".$user->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			switch($provider->getName()) {
				case 'recurly' :
					$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
					$recurlySubscriptionsHandler->doUpdateUserSubscriptions($user, $userOpts);
					break;
				case 'gocardless' :
					$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler();
					$gocardlessSubscriptionsHandler->doUpdateUserSubscriptions($user, $userOpts);
					break;
				case 'celery' :
					//nothing to do (owned)
					break;
				default:
					//nothing to do (unknown)
					break;
			}
			config::getLogger()->addInfo("dbsubscriptions update for userid=".$user->getId()." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while dbsubscriptions updating for userid=".$user->getId().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscriptions updating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while dbsubscriptions updating for userid=".$user->getId().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscriptions updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	private function doFillSubscriptions($subscriptions) {
		foreach($subscriptions as $subscription) {
			$this->doFillSubscription($subscription);
		}
	}

	private function doFillSubscription(BillingsSubscription $subscription = NULL) {
		if($subscription == NULL) {
			return;
		}
		$provider = ProviderDAO::getProviderById($subscription->getProviderId());
		if($provider == NULL) {
			$msg = "unknown provider with id : ".$user->getProviderId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		switch($provider->getName()) {
			case 'recurly' :
				$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
				$recurlySubscriptionsHandler->doFillSubscription($subscription);
				break;
			case 'gocardless' :
				$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler();
				$gocardlessSubscriptionsHandler->doFillSubscription($subscription);
				break;
			case 'celery' :
				$celerySubscriptionsHandler = new CelerySubscriptionsHandler();
				$celerySubscriptionsHandler->doFillSubscription($subscription);
				break;
			default:
				$msg = "unsupported feature for provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
	}
	
	private function checkBillingInfoOptsArray($billing_info_opts_as_array) {
		//TODO
		/*if(!isset($user_opts_as_array['email'])) {
			//exception
			$msg = "userOpts field 'email' is missing";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if(!isset($user_opts_as_array['firstName'])) {
			//exception
			$msg = "userOpts field 'firstName' is missing";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if(!isset($user_opts_as_array['lastName'])) {
			//exception
			$msg = "userOpts field 'lastName' is missing";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}*/
	}
	
}

?>