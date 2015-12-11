<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/BillingsException.php';

class RecurlySubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, Plan $plan, PlanOpts $planOpts, BillingInfoOpts $billingInfoOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("recurly subscription creation...");
			if(isset($billingInfoOpts->getOpts()['subscription_uuid'])) {
				//** in recurly : user subscription is pre-created **/
				//
				Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
				Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
				//
				$subscriptions = Recurly_SubscriptionList::getForAccount($user->getUserProviderUuid());
				$found = false;
				foreach ($subscriptions as $subscription) {
					if($subscription->uuid == $billingInfoOpts->getOpts()['subscription_uuid']) {
						$found = true;
						break;
					}
				}
				if(!$found) {
					$msg = "subscription not found for the current customer";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
			} else {
				//** in recurly : user subscription is NOT pre-created **/
				Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
				Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
				//
				$subscription = new Recurly_Subscription();
				$subscription->plan_code = $plan->getPlanUuid();
				$subscription->currency = 'EUR';//TODO
			
				$account = new Recurly_Account();
				$account->account_code = $user->getUserProviderUuid();
				$account->email = $userOpts->getOpts()['email'];
				$account->first_name = $userOpts->getOpts()['first_name'];
				$account->last_name = $userOpts->getOpts()['last_name'];
			
				$billing_info = new Recurly_BillingInfo();
				$billing_info->number = $billingInfoOpts->getOpts()['number'];
				$billing_info->month = $billingInfoOpts->getOpts()['month'];
				$billing_info->year = $billingInfoOpts->getOpts()['year'];
				$billing_info->verification_value = $billingInfoOpts->getOpts()['verification_value'];
				$billing_info->address1 = $billingInfoOpts->getOpts()['address1'];
				$billing_info->city = $billingInfoOpts->getOpts()['city'];
				$billing_info->state = $billingInfoOpts->getOpts()['state'];
				$billing_info->country = $billingInfoOpts->getOpts()['country'];
				$billing_info->zip = $billingInfoOpts->getOpts()['zip'];
			
				$account->billing_info = $billing_info;
				$subscription->account = $account;
			
				$subscription->create();
			}
			//
			$sub_uuid = $subscription->uuid;
			config::getLogger()->addInfo("recurly subscription creation done successfully, recurly_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a recurly subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription creation failed : ".$msg);
			throw $e;
		} catch (Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while creating a recurly subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a recurly subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($sub_uuid);
	}
	
	public function doUpdateUserSubscriptions(User $user) {
		config::getLogger()->addInfo("recurly dbsubscriptions update for userid=".$user->getId()."...");
		//
		Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
		Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
		//
		$provider = ProviderDAO::getProviderById($user->getProviderId());
		//
		if($provider == NULL) {
			$msg = "unknown provider id : ".$user->getProviderId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		try {
			$api_subscriptions = Recurly_SubscriptionList::getForAccount($user->getUserProviderUuid());
		} catch (Recurly_NotFoundError $e) {
			$msg = "an account not found exception occurred while getting subscriptions for user_provider_uuid=".$user->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting subscriptions for user_provider_uuid=".$user->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionByUserId($user->getId());
		//ADD OR UPDATE
		foreach ($api_subscriptions as $api_subscription) {
			//plan
			$plan_uuid = $api_subscription->plan->plan_code;
			$plan = PlanDAO::getPlanByUuid($provider->getId(), $plan_uuid);
			if($plan == NULL) {
				$msg = "plan with uuid=".$plan_uuid." not found";
				config::getLogger()->addError($msg);
				throw new Exception($msg);
			}
			$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
			$db_subscription = $this->getDbSubscriptionByUuid($db_subscriptions, $api_subscription->uuid);
			if($db_subscription == NULL) {
				//CREATE
				$db_subscription = $this->createDbSubscriptionFromApiSubscription($user, $provider, $plan, $planOpts, $api_subscription, 'api', 0);
			} else {
				//UPDATE
				$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, $provider, $plan, $planOpts, $api_subscription, $db_subscription, 'api', 0);
			}
		}
		//DELETE UNUSED SUBSCRIPTIONS (DELETED FROM THIRD PARTY)
		foreach ($db_subscriptions as $db_subscription) {
			$api_subscription = $this->getApiSubscriptionByUuid($api_subscriptions, $db_subscription->getSubUid());
			if($api_subscription == NULL) {
				BillingsSubscriptionDAO::deleteBillingsSubscriptionById($db_subscription->getId());
			}
		}
		config::getLogger()->addInfo("recurly dbsubscriptions update for userid=".$user->getId()." done successfully");
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, Plan $plan, PlanOpts $planOpts, $sub_uuid, $update_type, $updateId) {
		//
		Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
		Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
		//
		$api_subscription = Recurly_Subscription::get($sub_uuid);
		//
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $plan, $planOpts, $api_subscription, $update_type, $updateId));
	}
	
	private function createDbSubscriptionFromApiSubscription(User $user, Provider $provider, Plan $plan, PlanOpts $planOpts, Recurly_Subscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("recurly dbsubscription creation for userid=".$user->getId().", recurly_subscription_uuid=".$api_subscription->uuid."...");
		//CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setProviderId($provider->getId());
		$db_subscription->setUserId($user->getId());
		$db_subscription->setPlanId($plan->getId());
		$db_subscription->setSubUid($api_subscription->uuid);
		switch ($api_subscription->state) {
			case 'active' :
				$db_subscription->setSubStatus('active');
				break;
			case 'canceled' :
				$db_subscription->setSubStatus('canceled');
				break;
			case 'future' :
				$db_subscription->setSubStatus('future');
				break;
			case 'expired' :
				$db_subscription->setSubStatus('expired');
				break;
			default :
				$msg = "unknown subscription state : ".$api_subscription->state;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		$db_subscription->setSubActivatedDate($api_subscription->activated_at);
		$db_subscription->setSubCanceledDate($api_subscription->canceled_at);
		$db_subscription->setSubExpiresDate($api_subscription->expires_at);
		$db_subscription->setSubPeriodStartedDate($api_subscription->current_period_started_at);
		$db_subscription->setSubPeriodEndsDate($api_subscription->current_period_ends_at);
		switch ($api_subscription->collection_mode) {
			case 'automatic' :
				$db_subscription->setSubCollectionMode('automatic');
				break;
			case 'manual' :
				$db_subscription->setSubCollectionMode('manual');
				break;
			default :
				$db_subscription->setSubCollectionMode('manual');//it is the default says recurly
				break;
		}
		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription->setDeleted('false');
		//
		$db_subscription = BillingsSubscriptionDAO::addBillingsSubscription($db_subscription);
		config::getLogger()->addInfo("recurly dbsubscription creation for userid=".$user->getId().", recurly_subscription_uuid=".$api_subscription->uuid." done successfully, id=".$db_subscription->getId());
		return($db_subscription);
	}
	
	private function updateDbSubscriptionFromApiSubscription(User $user, Provider $provider, Plan $plan, PlanOpts $planOpts, Recurly_Subscription $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("recurly dbsubscription update for userid=".$user->getId().", recurly_subscription_uuid=".$api_subscription->uuid.", id=".$db_subscription->getId()."...");
		//UPDATE
		//$db_subscription->setProviderId($provider->getId());//STATIC
		//$db_subscription->setUserId($user->getId());//STATIC
		$db_subscription->setPlanId($plan->getId());
		//$db_subscription->setSubUid($subscription_uuid);//STATIC
		switch ($api_subscription->state) {
			case 'active' :
				$db_subscription->setSubStatus('active');
				break;
			case 'canceled' :
				$db_subscription->setSubStatus('canceled');
				break;
			case 'future' :
				$db_subscription->setSubStatus('future');
				break;
			case 'expired' :
				$db_subscription->setSubStatus('expired');
				break;
			default :
				$msg = "unknown subscription state : ".$api_subscription->state;
				config::getLogger()->addError($msg);
				throw new Exception($msg);
				//break;
		}
		$db_subscription->setSubActivatedDate($api_subscription->activated_at);
		$db_subscription->setSubCanceledDate($api_subscription->canceled_at);
		$db_subscription->setSubExpiresDate($api_subscription->expires_at);
		$db_subscription->setSubPeriodStartedDate($api_subscription->current_period_started_at);
		$db_subscription->setSubPeriodEndsDate($api_subscription->current_period_ends_at);
		switch ($api_subscription->collection_mode) {
			case 'automatic' :
				$db_subscription->setSubCollectionMode('automatic');
				break;
			case 'manual' :
				$db_subscription->setSubCollectionMode('manual');
				break;
			default :
				$db_subscription->setSubCollectionMode('manual');//it is the default says recurly
				break;
		}
		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		//$db_subscription->setDeleted('false');//STATIC
		//
		$db_subscription = BillingsSubscriptionDAO::updateBillingsSubscription($db_subscription);
		config::getLogger()->addInfo("recurly dbsubscription update for userid=".$user->getId().", recurly_subscription_uuid=".$api_subscription->uuid.", id=".$db_subscription->getId()." done successfully");
		return($db_subscription);
	}
	
	private function getDbSubscriptionByUuid(array $db_subscriptions, $subUuid) {
		foreach ($db_subscriptions as $db_subscription) {
			if($db_subscription->getSubUid() == $subUuid) {
				return($db_subscription);
			}
		}
	}
	
	private function getApiSubscriptionByUuid(Recurly_SubscriptionList $api_subscriptions, $subUuid) {
		foreach ($api_subscriptions as $api_subscription) {
			if($api_subscription->uuid == $subUuid) {
				return($api_subscription);
			}
		}
	}
	
	public function doFillSubscription(BillingsSubscription $subscription) {
		//nothing to do specific to Recurly
	}
	
}

?>