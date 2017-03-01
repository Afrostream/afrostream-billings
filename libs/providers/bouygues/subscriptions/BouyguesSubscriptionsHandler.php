<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../client/BouyguesTVClient.php';
require_once __DIR__ . '/../../global/subscriptions/ProviderSubscriptionsHandler.php';
require_once __DIR__ . '/../../global/requests/ExpireSubscriptionRequest.php';

class BouyguesSubscriptionsHandler extends ProviderSubscriptionsHandler {
	
	public function createDbSubscriptionFromApiSubscriptionUuid(
			User $user, 
			UserOpts $userOpts, 
			Provider $provider, 
			InternalPlan $internalPlan = NULL, 
			InternalPlanOpts $internalPlanOpts = NULL, 
			Plan $plan = NULL, 
			PlanOpts $planOpts = NULL, 
			BillingsSubscriptionOpts $subOpts = NULL, 
			BillingInfo $billingInfo = NULL, 
			$subscription_billing_uuid, 
			$sub_uuid, 
			$update_type, 
			$updateId) {
		$api_subscription = self::checkApiSubscriptionByProviderPlanUuid($user->getUserProviderUuid(), $plan->getPlanUuid());
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $billingInfo, $subscription_billing_uuid, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, BouyguesSubscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("bouygues dbsubscription creation for userid=".$user->getId().", providerPlanUuid=".$plan->getPlanUuid()."...");
		//CREATE
		$start_date = (new DateTime())->setTimezone(new DateTimeZone(config::$timezone));
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid($subscription_billing_uuid);
		$db_subscription->setProviderId($provider->getId());
		$db_subscription->setUserId($user->getId());
		$db_subscription->setPlanId($plan->getId());
		$db_subscription->setSubUid(guid());
		$db_subscription->setSubStatus('active');
		$db_subscription->setSubActivatedDate($start_date);
		$db_subscription->setSubCanceledDate(NULL);
		$db_subscription->setSubExpiresDate(NULL);
		$db_subscription->setSubPeriodStartedDate($start_date);
		$end_date = clone $start_date;
		$end_date->add(new DateInterval("P".getEnv('BOUYGUES_SUBSCRIPTION_PERIOD_LENGTH')."D"));
		//$end_date->setTime(23, 59, 59);
		$db_subscription->setSubPeriodEndsDate($end_date);
		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription->setDeleted(false);
		//NO MORE TRANSACTION (DONE BY CALLER)
		//<-- DATABASE -->
		//BILLING_INFO
		if(isset($billingInfo)) {
			$billingInfo = BillingInfoDAO::addBillingInfo($billingInfo);
			$db_subscription->setBillingInfoId($billingInfo->getId());
		}
		$db_subscription = BillingsSubscriptionDAO::addBillingsSubscription($db_subscription);
		//SUB_OPTS
		if(isset($subOpts)) {
			$subOpts->setSubId($db_subscription->getId());
			$subOpts = BillingsSubscriptionOptsDAO::addBillingsSubscriptionOpts($subOpts);
		}
		//<-- DATABASE -->
		config::getLogger()->addInfo("bouygues dbsubscription creation for userid=".$user->getId().", providerPlanUuid=".$plan->getPlanUuid()." done successfully, id=".$db_subscription->getId());
		return($this->doFillSubscription($db_subscription));
	}
	
	public function updateDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BouyguesSubscription $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("bouygues dbsubscription update for userid=".$user->getId().", id=".$db_subscription->getId()."...");
		//UPDATE
		$db_subscription_before_update = clone $db_subscription;
		//
		$db_subscription->setPlanId($plan->getId());
		$db_subscription = BillingsSubscriptionDAO::updatePlanId($db_subscription);
		//
		$db_subscription->setUpdateType($update_type);
		$db_subscription = BillingsSubscriptionDAO::updateUpdateType($db_subscription);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription = BillingsSubscriptionDAO::updateUpdateId($db_subscription);
		//
		$this->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
		//
		config::getLogger()->addInfo("bouygues dbsubscription update for userid=".$user->getId().", id=".$db_subscription->getId()." done successfully");
		return($this->doFillSubscription($db_subscription));
	}
	
	public function doGetUserSubscriptions(User $user) {
		$shouldUpdate = true;
		//only update after a period :check is HERE
		$usersRequestsLogs_array = UsersRequestsLogDAO::getLastUsersRequestsLogsByUserId($user->getId(), 1);
		if(count($usersRequestsLogs_array) > 0) {
			$usersRequestsLog = $usersRequestsLogs_array[0];
			$now = new DateTime();
			$creation_date_log = $usersRequestsLog->getCreationDate();
			//diff
			$date_to_compare = clone $creation_date_log;
			$date_to_compare->add(new DateInterval("P".getEnv('BOUYGUES_SUBSCRIPTION_PERIOD_LENGTH')."D"));
			if($date_to_compare > $now) {
				$shouldUpdate = false;
			}
		}
		if($shouldUpdate) {
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			//NC : DO NOT THROW THE EXCEPTION, JUST LOG IT AS A BEST EFFORT. 
			//SO WE ALWAYS RETURN SUBSCRIPTIONS (EVEN EXPIRED) INSTEAD OF AN ERROR
			try {
				$this->doUpdateUserSubscriptions($user, $userOpts);
			} catch(BillingsException $e) {
				config::getLogger()->addError("Updating bouygues Subscriptions for userid=".$user->getId()." failed, message=".$e->getMessage().", code=".$e->getCode());
			} catch(Exception $e) {
				config::getLogger()->addError("Updating bouygues Subscriptions for userid=".$user->getId()." failed, message=".$e->getMessage());
			}
		}
		return(parent::doGetUserSubscriptions($user));
	}
	
	public function doFillSubscription(BillingsSubscription $subscription = NULL) {
		$subscription = parent::doFillSubscription($subscription);
		if($subscription == NULL) {
			return NULL;
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
				config::getLogger()->addWarning("bouygues dbsubscription unknown subStatus=".$subscription->getSubStatus().", bouygues_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;
		}
		$subscription->setIsActive($is_active);
		$subscription->setIsCancelable(false);
		$subscription->setIsExpirable(false);
		return($subscription);
	}
	
	public function doRenewSubscription(BillingsSubscription $subscription, RenewSubscriptionRequest $renewSubscriptionRequest) {
		$start_date = $renewSubscriptionRequest->getStartDate();
		$end_date = $renewSubscriptionRequest->getEndDate();
		if($end_date != NULL) {
			$msg = "renewing a bouygues subscription does not support that end_date is already set";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($subscription->getSubStatus() != "active") {
			$msg = "cannot renew because of the current_status=".$subscription->getSubStatus();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$user = UserDAO::getUserById($subscription->getUserId());
		if($user == NULL) {
			$msg = "unknown user with id : ".$subscription->getUserId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
		$providerPlan = PlanDAO::getPlanById($subscription->getPlanId());
		if($providerPlan == NULL) {
			$msg = "unknown plan with id : ".$subscription->getPlanId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//VERIFY THAT SUBSCRIPTION IS STILL ACTIVE BEFORE RENEWING
		self::checkApiSubscriptionByProviderPlanUuid($user->getUserProviderUuid(), $providerPlan->getPlanUuid());
		$today = new DateTime();
		$today->setTimezone(new DateTimeZone(config::$timezone));
		$today->setTime(23, 59, 59);//consider all the day
		
		if($start_date == NULL) {
			$start_date = $subscription->getSubPeriodEndsDate();
		}
		$start_date->setTimezone(new DateTimeZone(config::$timezone));
		
		$end_date = clone $start_date;
		
		$to_be_updated = false;

		while ($end_date < $today) {
			$to_be_updated = true;
			$start_date = clone $end_date;
			$end_date->add(new DateInterval("P".getEnv('BOUYGUES_SUBSCRIPTION_PERIOD_LENGTH')."D"));
			//$end_date->setTime(23, 59, 59);//force the time to the end of the day
		}
		//done
		$start_date->setTime(0, 0, 0);//force start_date to beginning of the day
		if($to_be_updated) {
			$subscription->setSubPeriodStartedDate($start_date);
			$subscription->setSubPeriodEndsDate($end_date);
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				BillingsSubscriptionDAO::updateSubStartedDate($subscription);
				BillingsSubscriptionDAO::updateSubEndsDate($subscription);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
		}
		return($this->doFillSubscription(BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId())));
	}
	
	public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
		config::getLogger()->addInfo("bouygues dbsubscriptions update for userid=".$user->getId()."...");
		//
		$bouyguesTVClient = new BouyguesTVClient($user->getUserProviderUuid());
		//
		$provider = ProviderDAO::getProviderById($user->getProviderId());
		//
		if($provider == NULL) {
			$msg = "unknown provider id : ".$user->getProviderId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		
		//On doit récuperer les plans puis faire les requêtes
		$providerPlans = PlanDAO::getPlans($provider->getId());
		
		$bouyguesSubscriptions = array();
		
		foreach($providerPlans as $providerPlan) {
			$bouyguesSubscriptionResponse = $bouyguesTVClient->getSubscription($providerPlan->getPlanUuid());
			$bouyguesSubscription = $bouyguesSubscriptionResponse->getBouyguesSubscription();
			$bouyguesSubscriptions[] = $bouyguesSubscription;
		}
		
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		//ADD OR UPDATE
		foreach ($bouyguesSubscriptions as $bouygues_subscription) {
			if($bouygues_subscription->getResultMessage() == 'SubscribedNotCoupled') {
				//plan
				$plan_uuid = $bouygues_subscription->getSubscriptionId();
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
				$db_subscription = self::getDbSubscriptionByProviderPlanId($db_subscriptions, $plan->getId());
				if($db_subscription == NULL) {
					//CREATE
					$db_subscription = $this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, NULL, NULL, guid(), $bouygues_subscription, 'api', 0);
				} else {
					//UPDATE
					$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $bouygues_subscription, $db_subscription, 'api', 0);
				}
			}
		}
		//DELETE UNUSED SUBSCRIPTIONS (DELETED FROM THIRD PARTY)
		foreach ($db_subscriptions as $db_subscription) {
			$plan = PlanDAO::getPlanById($db_subscription->getPlanId());
			$bouygues_subscription = self::getApiSubscriptionByProviderPlanUuid($bouyguesSubscriptions, $plan->getPlanUuid());
			if($bouygues_subscription == NULL || $bouygues_subscription->getResultMessage() != 'SubscribedNotCoupled') {
				BillingsSubscriptionDAO::deleteBillingsSubscriptionById($db_subscription->getId());
			}
		}
		config::getLogger()->addInfo("bouygues dbsubscriptions update for userid=".$user->getId()." done successfully");
	}
	
	public function doExpireSubscription(BillingsSubscription $subscription, ExpireSubscriptionRequest $expireSubscriptionRequest) {
		try {
			config::getLogger()->addInfo("bouygues subscription expiring...");
			if(
					$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				if($expireSubscriptionRequest->getIsRefundEnabled() == true) {
					$msg = "cannot expire and refund a ".$this->provider->getName()." subscription";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_EXP_REFUND_UNSUPPORTED);
				}
				if($expireSubscriptionRequest->getOrigin() == 'api') {
					$msg = "cannot expire a ".$this->provider->getName()." subscription from api"; 
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				//
				$expiresDate = $expireSubscriptionRequest->getExpiresDate();
				//
				$subscription->setSubExpiresDate($expiresDate);
				$subscription->setSubStatus("expired");
				//NC : We suppose that it was canceled somewhere in the current day, so we take the beginning of the day
				//NC : Must be different of the expires_date otherwise it will be considered as a payment issue
				$canceled_date = clone $expiresDate;
				$canceled_date->setTimezone(new DateTimeZone(config::$timezone));
				$canceled_date->setTime(0, 0, 0);
				$subscription->setSubCanceledDate($canceled_date);
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					BillingsSubscriptionDAO::updateSubExpiresDate($subscription);
					BillingsSubscriptionDAO::updateSubStatus($subscription);
					BillingsSubscriptionDAO::updateSubCanceledDate($subscription);
					//COMMIT
					pg_query("COMMIT");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			}
			//
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("bouygues subscription expiring done successfully for bouygues_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while expiring a bouygues subscription for bouygues_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("bouygues subscription expiring failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring a bouygues subscription for bouygues_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("bouygues subscription expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		parent::doSendSubscriptionEvent($subscription_before_update, $subscription_after_update);
	}
	
	private static function getDbSubscriptionByProviderPlanId(array $db_subscriptions, $providerPlanId) {
		foreach ($db_subscriptions as $db_subscription) {
			if($db_subscription->getPlanId() == $providerPlanId) {
				return($db_subscription);
			}
		}
	}
	
	private static function getApiSubscriptionByProviderPlanUuid(array $bouygues_subscriptions, $providerPlanUuid) {
		foreach ($bouygues_subscriptions as $bouygues_subscription) {
			if($bouygues_subscription->getSubscriptionId() == $providerPlanUuid) {
				return($bouygues_subscription);
			}
		}
	}
	
	private static function checkApiSubscriptionByProviderPlanUuid($userProviderUuid, $providerPlanUuid) {
		$bouyguesTVClient = new BouyguesTVClient($userProviderUuid);
		$bouyguesSubscriptionsResponse = $bouyguesTVClient->getSubscription($providerPlanUuid);
		$bouyguesSubscription = $bouyguesSubscriptionsResponse->getBouyguesSubscription();
		if($bouyguesSubscription->getResultMessage() != 'SubscribedNotCoupled') {
			$msg = "BouyguesSubscription resultMessage != SubscribedNotCoupled, resultMessage=".$bouyguesSubscription->getResultMessage();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::BOUYGUES_SUBSCRIPTION_BAD_STATUS);
		}
		return($bouyguesSubscription);
	}
	
}

?>