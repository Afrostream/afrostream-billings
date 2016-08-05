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
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, $sub_uuid, $update_type, $updateId) {
		$api_subscription = self::checkApiSubscriptionByProviderPlanUuid($userOpts->getOpts()['OrangeApiToken'], $plan->getPlanUuid());
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $billingInfo, $subscription_billing_uuid, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, OrangeSubscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("orange dbsubscription creation for userid=".$user->getId().", providerPlanUuid=".$plan->getPlanUuid()."...");
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
		$end_date->add(new DateInterval("P".getEnv('ORANGE_SUBSCRIPTION_PERIOD_LENGTH')."D"));
		//$end_date->setTime(23, 59, 59);
		$db_subscription->setSubPeriodEndsDate($end_date);
		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription->setDeleted('false');
		//NO MORE TRANSACTION (DONE BY CALLER)
		//<-- DATABASE -->
		//BILLING_INFO
		if(isset($billingInfo)) {
			$billingInfo = BillingInfoDAO::addBillingInfo($billingInfo);
			$db_subscription->setBillingInfoId($billingInfo->getBillingInfoId());
		}
		$db_subscription = BillingsSubscriptionDAO::addBillingsSubscription($db_subscription);
		//SUB_OPTS
		if(isset($subOpts)) {
			$subOpts->setSubId($db_subscription->getId());
			$subOpts = BillingsSubscriptionOptsDAO::addBillingsSubscriptionOpts($subOpts);
		}
		//<-- DATABASE -->
		config::getLogger()->addInfo("orange dbsubscription creation for userid=".$user->getId().", providerPlanUuid=".$plan->getPlanUuid()." done successfully, id=".$db_subscription->getId());
		return($db_subscription);
	}
	
	public function updateDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, OrangeSubscription $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("orange dbsubscription update for userid=".$user->getId().", id=".$db_subscription->getId()."...");
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
		config::getLogger()->addInfo("orange dbsubscription update for userid=".$user->getId().", id=".$db_subscription->getId()." done successfully");
		return($db_subscription);
	}
	
	protected function doGetUserSubscriptions(User $user) {
		$shouldUpdate = true;
		//only update after a period :check is HERE
		$usersRequestsLogs_array = UsersRequestsLogDAO::getLastUsersRequestsLogsByUserId($user->getId(), 1);
		if(count($usersRequestsLogs_array) > 0) {
			$usersRequestsLog = $usersRequestsLogs_array[0];
			$now = new DateTime();
			$creation_date_log = $usersRequestsLog->getCreationDate();
			//diff
			$date_to_compare = clone $creation_date_log;
			$date_to_compare->add(new DateInterval("P".getEnv('ORANGE_SUBSCRIPTION_PERIOD_LENGTH')."D"));
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
				config::getLogger()->addError("Updating Orange Subscriptions for userid=".$user->getId()." failed, message=".$e->getMessage().", code=".$e->getCode());
			} catch(Exception $e) {
				config::getLogger()->addError("Updating Orange Subscriptions for userid=".$user->getId()." failed, message=".$e->getMessage());
			}
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
		$subscription->setIsCancelable(false);
	}
	
	public function doRenewSubscription(BillingsSubscription $subscription, DateTime $start_date = NULL, DateTime $end_date = NULL) {
		if($end_date != NULL) {
			$msg = "renewing a orange subscription does not support that end_date is already set";
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
		self::checkApiSubscriptionByProviderPlanUuid($userOpts->getOpts()['OrangeApiToken'], $providerPlan->getPlanUuid());
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
			$end_date->add(new DateInterval("P".getEnv('ORANGE_SUBSCRIPTION_PERIOD_LENGTH')."D"));
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
		return(BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId()));
	}
	
	public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
		config::getLogger()->addInfo("orange dbsubscriptions update for userid=".$user->getId()."...");
		//
		$orangeTVClient = new OrangeTVClient($userOpts->getOpts()['OrangeApiToken']);
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
		foreach ($orangeSubscriptionsResponse->getOrangeSubscriptions() as $orange_subscription) {
			try {
				if($orange_subscription->getStatus() == 1) {
					//plan
					$plan_uuid = $orange_subscription->getId();
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
						$db_subscription = $this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, NULL, NULL, guid(), $orange_subscription, 'api', 0);
					} else {
						//UPDATE
						$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $orange_subscription, $db_subscription, 'api', 0);
					}
				}
			} catch(Exception $e) {
				$msg = "orange dbsubscription update failed for orange_subscription_id=".$orange_subscription->getId().", message=".$e->getMessage();
				config::getLogger()->addError($msg);
			}
		}
		//DELETE UNUSED SUBSCRIPTIONS (DELETED FROM THIRD PARTY)
		foreach ($db_subscriptions as $db_subscription) {
			$plan = PlanDAO::getPlanById($db_subscription->getPlanId());
			$orange_subscription = self::getApiSubscriptionByProviderPlanUuid($orangeSubscriptionsResponse->getOrangeSubscriptions(), $plan->getPlanUuid());
			if($orange_subscription == NULL || $orange_subscription->getStatus() != 1) {
				BillingsSubscriptionDAO::deleteBillingsSubscriptionById($db_subscription->getId());
			}
		}
		config::getLogger()->addInfo("orange dbsubscriptions update for userid=".$user->getId()." done successfully");
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
	
	private static function getApiSubscriptionByProviderPlanUuid(array $orange_subscriptions, $providerPlanUuid) {
		foreach ($orange_subscriptions as $orange_subscription) {
			if($orange_subscription->getId() == $providerPlanUuid) {
				return($orange_subscription);
			}
		}
	}
	
	private static function checkApiSubscriptionByProviderPlanUuid($orangeAPIToken, $providerPlanUuid) {
		$orangeTVClient = new OrangeTVClient($orangeAPIToken);
		$orangeSubscriptionsResponse = $orangeTVClient->getSubscriptions($providerPlanUuid);
		$orangeSubscription = $orangeSubscriptionsResponse->getOrangeSubscriptionById($providerPlanUuid);
		if($orangeSubscription == NULL) {
			$msg = "No OrangeSubscription was found for this plan : ".$providerPlanUuid;
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::ORANGE_SUBSCRIPTION_NOT_FOUND);
		}
		if($orangeSubscription->getStatus() != 1) {
			$msg = "OrangeSubscription STATUS <> 1, STATUS=".$orangeSubscription->getStatus();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::ORANGE_SUBSCRIPTION_BAD_STATUS);
		}
		return($orangeSubscription);
	}
	
}

?>