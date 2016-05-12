<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../client/OrangeTVClient.php';

class OrangeSubscriptionsHandler extends SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_provider_uuid, BillingInfoOpts $billingInfoOpts, BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("orange subscription creation...");
			//pre-requisite
			checkSubOptsArray($subOpts->getOpts(), 'orange');
			if(!isset($subscription_provider_uuid)) {
				$msg = "field 'subscriptionProviderUuid' was not provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($subscription_provider_uuid == 'generate') {
				$subscription_provider_uuid = guid();
			}
			$orangeTVClient = new OrangeTVClient($userOpts->getOpts()['OrangeAPIToken']);
			$orangeSubscriptionsResponse = $orangeTVClient->getSubscriptions($plan->getPlanUuid());
			$orangeSubscription = $orangeSubscriptionsResponse->getOrangeSubscriptionById($plan->getPlanUuid());
			if($orangeSubscription == NULL) {
				$msg = "TODO : OrangeSubscription IS NULL";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($orangeSubscription->getStatus() != 1) {
				$msg = "TODO : OrangeSubscription STATUS <> 1 : ".$orangeSubscription->getStatus();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//OK
			$sub_uuid = $subscription_provider_uuid;
			config::getLogger()->addInfo("orange subscription creation done successfully, orange_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a orange subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("orange subscription creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a orange subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("orange subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($sub_uuid);
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, $sub_uuid, $update_type, $updateId) {
		$api_subscription = new BillingsSubscription();
		$api_subscription->setSubUid($sub_uuid);
		$api_subscription->setSubStatus('active');
		$start_date = (new DateTime())->setTimezone(new DateTimeZone(config::$timezone));
		$api_subscription->setSubActivatedDate($start_date);
		$api_subscription->setSubPeriodStartedDate($start_date);
		$end_date = clone $start_date;
		$end_date->add(new DateInterval("P".getEnv('ORANGE_SUBSCRIPTION_PERIOD_LENGTH')."D"));
		$end_date->setTime(23, 59, 59);
		$api_subscription->setSubPeriodEndsDate($end_date);
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingsSubscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("orange dbsubscription creation for userid=".$user->getId().", orange_subscription_uuid=".$api_subscription->getSubUid()."...");
		//CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid(guid());
		$db_subscription->setProviderId($provider->getId());
		$db_subscription->setUserId($user->getId());
		$db_subscription->setPlanId($plan->getId());
		$db_subscription->setSubUid($api_subscription->getSubUid());
		switch ($api_subscription->getSubStatus()) {
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
				$msg = "unknown subscription state : ".$api_subscription->getSubStatus();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		$db_subscription->setSubActivatedDate($api_subscription->getSubActivatedDate());
		$db_subscription->setSubCanceledDate($api_subscription->getSubCanceledDate());
		$db_subscription->setSubExpiresDate($api_subscription->getSubExpiresDate());
		$db_subscription->setSubPeriodStartedDate($api_subscription->getSubPeriodStartedDate());
		$db_subscription->setSubPeriodEndsDate($api_subscription->getSubPeriodEndsDate());
		//The information is in the PLAN
		/*switch ($api_subscription->collection_mode) {
			case 'automatic' :
				$db_subscription->setSubCollectionMode('automatic');
				break;
			case 'manual' :
				$db_subscription->setSubCollectionMode('manual');
				break;
			default :
				$db_subscription->setSubCollectionMode('manual');//it is the default says recurly
				break;
		}*/
		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription->setDeleted('false');
		//NO MORE TRANSACTION (DONE BY CALLER)
		//<-- DATABASE -->
		$db_subscription = BillingsSubscriptionDAO::addBillingsSubscription($db_subscription);
		//SUB_OPTS
		if(isset($subOpts)) {
			$subOpts->setSubId($db_subscription->getId());
			$subOpts = BillingsSubscriptionOptsDAO::addBillingsSubscriptionOpts($subOpts);
		}
		//<-- DATABASE -->
		config::getLogger()->addInfo("orange dbsubscription creation for userid=".$user->getId().", orange_subscription_uuid=".$api_subscription->getSubUid()." done successfully, id=".$db_subscription->getId());
		return($db_subscription);
	}
	
	protected function doGetUserSubscriptions(User $user) {
		$shouldUpdate = true;
		if($shouldUpdate) {
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			$this->doUpdateUserSubscriptions($user, $userOpts);
		}
		return(BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId()));
	}
	
	protected function doFillSubscription(BillingsSubscription $subscription = NULL) {
		if($subscription == NULL) {
			return;
		}
		$is_active = NULL;
		$periodStartedDate = $subscription->getSubPeriodStartedDate()->setTimezone(new DateTimeZone(config::$timezone));
		$periodEndsDate = $subscription->getSubPeriodEndsDate()->setTimezone(new DateTimeZone(config::$timezone));
		$periodeGraceEndsDate = clone $periodEndsDate;
		$periodeGraceEndsDate->setTime(23, 59, 59);//is active until end of the day
		switch($subscription->getSubStatus()) {
			case 'active' :
			case 'canceled' :
				$now = new DateTime();
				//check dates
				if(
						($now < $periodeGraceEndsDate)
						&&
						($now >= $periodStartedDate)
						) {
							//inside the period
							$is_active = 'yes';
						} else {
							//outside the period
							$is_active = 'no';
						}
						break;
			case 'future' :
				$is_active = 'no';
				break;
			case 'expired' :
				$is_active = 'no';
				break;
			default :
				$is_active = 'no';
				config::getLogger()->addWarning("orange dbsubscription unknown subStatus=".$subscription->getSubStatus().", orange_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;
		}
		//done
		$subscription->setIsActive($is_active);	
	}
	
	public function doRenewSubscription(BillingsSubscription $subscription, DateTime $start_date = NULL, DateTime $end_date = NULL) {
		//TODO
	}
	
	
	public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
		config::getLogger()->addInfo("orange dbsubscriptions update for userid=".$user->getId()."...");
		//
		$orangeTVClient = new OrangeTVClient($userOpts->getOpts()['OrangeAPIToken']);
		//
		$provider = ProviderDAO::getProviderById($user->getProviderId());
		//
		if($provider == NULL) {
			$msg = "unknown provider id : ".$user->getProviderId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$orangeSubscriptionsResponse = $orangeTVClient->getSubscriptions();
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		//ADD OR UPDATE
		foreach ($orangeSubscriptionsResponse->getOrangeSubscriptions() as $api_subscription) {
			if($api_subscription->getStatus() == 1) {
				//plan
				$plan_uuid = $api_subscription->getId();
				$plan = PlanDAO::getPlanByUuid($provider->getId(), $plan_uuid);
				if($plan == NULL) {
					$msg = "plan with uuid=".$plan_uuid." not found";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
				$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($plan->getId()));
				if($internalPlan == NULL) {
					$msg = "plan with uuid=".$plan_uuid." for provider ".$provider->getName()." is not linked to an internal plan";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
				$db_subscription = $this->getDbSubscriptionByProviderPlanId($db_subscriptions, $plan->getId());
				if($db_subscription == NULL) {
					//CREATE
					$db_subscription = $this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, NULL, $api_subscription, 'api', 0);
				} else {
					//UPDATE
					//$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, 'api', 0);
				}
			}
		}
		//DELETE UNUSED SUBSCRIPTIONS (DELETED FROM THIRD PARTY)
		foreach ($db_subscriptions as $db_subscription) {
			$api_subscription = $this->getApiSubscriptionByProviderPlanUuid($orangeSubscriptionsResponse->getOrangeSubscriptions(), $plan_uuid);
			if($api_subscription == NULL) {
				BillingsSubscriptionDAO::deleteBillingsSubscriptionById($db_subscription->getId());
			}
		}
		config::getLogger()->addInfo("orange dbsubscriptions update for userid=".$user->getId()." done successfully");
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		parent::doSendSubscriptionEvent($subscription_before_update, $subscription_after_update);
	}
	
	private function getDbSubscriptionByProviderPlanId(array $db_subscriptions, $providerPlanId) {
		foreach ($db_subscriptions as $db_subscription) {
			if($db_subscription->getPlanId() == $providerPlanId) {
				return($db_subscription);
			}
		}
	}
	
	private function getApiSubscriptionByProviderPlanUuid(array $api_subscriptions, $providerPlanUuid) {
		foreach ($api_subscriptions as $api_subscription) {
			if($api_subscription->getId() == $providerPlanUuid) {
				return($api_subscription);
			}
		}
	}
	
}

?>