<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../client/GoogleClient.php';
require_once __DIR__ . '/../../global/subscriptions/ProviderSubscriptionsHandler.php';
		
class GoogleSubscriptionsHandler extends ProviderSubscriptionsHandler {
	
	public function doCreateUserSubscription(User $user, 
			UserOpts $userOpts, 
			Provider $provider, 
			InternalPlan $internalPlan, 
			InternalPlanOpts $internalPlanOpts, 
			Plan $plan, 
			PlanOpts $planOpts, 
			$subscription_billing_uuid, 
			$subscription_provider_uuid, 
			BillingInfo $billingInfo, 
			BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo($this->provider->getName()." subscription creation...");
			if(isset($subscription_provider_uuid)) {
				$msg = "unsupported feature for provider named google, subscriptionProviderUuid has NOT to be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			checkSubOptsArray($subOpts->getOpts(), $this->provider->getName(), 'create');
			//** in google : user subscription is pre-created **/
			//
			$googleClient = new GoogleClient();
			$googleGetSubscriptionRequest = new GoogleGetSubscriptionRequest();
			$googleGetSubscriptionRequest->setSubscriptionId($plan->getPlanUuid());
			$googleGetSubscriptionRequest->setToken($subOpts->getOpts()['customerBankAccountToken']);
			$api_subscription = $googleClient->getSubscription($googleGetSubscriptionRequest);
			config::getLogger()->addInfo($this->provider->getName()." subscription creation...result=".var_export($api_subscription, true));
			$sub_uuid = guid();
			//
			config::getLogger()->addInfo($this->provider->getName()." subscription creation done successfully, ".$this->provider->getName()."_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a ".$this->provider->getName()." subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." subscription creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a ".$this->provider->getName()." subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($sub_uuid);
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, 
			UserOpts $userOpts, 
			Provider $provider, 
			InternalPlan $internalPlan, 
			InternalPlanOpts $internalPlanOpts, 
			Plan $plan, 
			PlanOpts $planOpts, 
			BillingsSubscriptionOpts $subOpts = NULL,
			BillingInfo $billingInfo = NULL, 
			$subscription_billing_uuid, 
			$sub_uuid, 
			$update_type, 
			$updateId) {
		//
		if($subOpts == NULL) {
			//Exception
			$msg = "field 'subOpts' is missing";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$googleClient = new GoogleClient();
		$googleGetSubscriptionRequest = new GoogleGetSubscriptionRequest();
		$googleGetSubscriptionRequest->setSubscriptionId($plan->getPlanUuid());
		$googleGetSubscriptionRequest->setToken($subOpts->getOpts()['customerBankAccountToken']);
		$api_subscription = $googleClient->getSubscription($googleGetSubscriptionRequest);
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $billingInfo, $subscription_billing_uuid, $api_subscription, $sub_uuid, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, 
			UserOpts $userOpts, 
			Provider $provider, 
			InternalPlan $internalPlan, 
			InternalPlanOpts $internalPlanOpts, 
			Plan $plan, PlanOpts $planOpts, 
			BillingsSubscriptionOpts $subOpts = NULL, 
			BillingInfo $billingInfo = NULL, 
			$subscription_billing_uuid, 
			Google_Service_AndroidPublisher_SubscriptionPurchase $api_subscription, 
			$sub_uuid,
			$update_type,
			$updateId) {
		config::getLogger()->addInfo($this->provider->getName()." dbsubscription creation for userid=".$user->getId()."...");
		//
		if($subOpts == NULL) {
			//Exception
			$msg = "field 'subOpts' is missing";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//SUBSCRIPTION CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid($subscription_billing_uuid);
		$db_subscription->setProviderId($provider->getId());
		$db_subscription->setUserId($user->getId());
		$db_subscription->setPlanId($plan->getId());
		$db_subscription->setSubUid($sub_uuid);
		$db_subscription->setSubStatus('active');
		$start_date = new DateTime();
		$start_date->setTimestamp($api_subscription->getStartTimeMillis() / 1000);
		$end_date = new DateTime();
		$end_date->setTimestamp($api_subscription->getExpiryTimeMillis() / 1000);
		$db_subscription->setSubActivatedDate($start_date);
		$db_subscription->setSubPeriodStartedDate($start_date);
		$db_subscription->setSubPeriodEndsDate($end_date);
		//
		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription->setDeleted(false);
		//NO MORE DB TRANSACTION (DONE BY CALLER)
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
		config::getLogger()->addInfo($this->provider->getName()." dbsubscription creation for userid=".$user->getId().", ".$this->provider->getName()."_subscription_uuid=".$db_subscription->getSubUid()." done successfully, id=".$db_subscription->getId());
		return($this->doFillSubscription($db_subscription));
	}
	
	public function updateDbSubscriptionFromApiSubscription(User $user, 
			UserOpts $userOpts, 
			Provider $provider, 
			InternalPlan $internalPlan, 
			InternalPlanOpts $internalPlanOpts, 
			Plan $plan, 
			PlanOpts $planOpts, 
			Google_Service_AndroidPublisher_SubscriptionPurchase $api_subscription, 
			BillingsSubscription $db_subscription, 
			$update_type, 
			$updateId) {
		config::getLogger()->addInfo($this->provider->getName()." dbsubscription update for userid=".$user->getId().", ".$this->provider->getName()."_subscription_uuid=".$db_subscription->getSubUid().", id=".$db_subscription->getId()."...");
		//UPDATE
		$db_subscription_before_update = clone $db_subscription;
		//
		//$db_subscription->setProviderId($provider->getId());//STATIC
		//$db_subscription->setUserId($user->getId());//STATIC
		$db_subscription->setPlanId($plan->getId());
		$db_subscription = BillingsSubscriptionDAO::updatePlanId($db_subscription);
		//$db_subscription->setSubUid($subscription_uuid);//STATIC
		$now = new DateTime();
		//activatedDate, startedDate, endsDate
		$start_date = new DateTime();
		$start_date->setTimestamp($api_subscription->getStartTimeMillis() / 1000);
		$end_date = new DateTime();
		$end_date->setTimestamp($api_subscription->getExpiryTimeMillis() / 1000);
		//
		//status
		$status = NULL;
		//canceledDate, expiresDate
		$canceledDate = NULL;
		$expiresDate = NULL;
		if($api_subscription->getAutoRenewing()) {
			$status = 'active';
		} else {
			$status = 'canceled';
		}
		if($end_date < $now) {
			$status = 'expired';
		}
		$db_subscription->setSubStatus($status);
		$db_subscription = BillingsSubscriptionDAO::updateSubStatus($subscription);
		switch($status) {
			case 'canceled' :
				if($db_subscription->getSubCanceledDate() == NULL) {
					$db_subscription->setSubCanceledDate($now);
					$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
				}
				break;
			case 'expired' :
				if($db_subscription->getSubExpiresDate() == NULL) {
					$db_subscription->setSubExpiresDate($now);
					$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
				}
				break;
		}
		//
		$db_subscription->setSubActivatedDate($start_date);
		$db_subscription = BillingsSubscriptionDAO::updateSubActivatedDate($db_subscription);
		$db_subscription->setSubPeriodStartedDate($start_date);
		$db_subscription = BillingsSubscriptionDAO::updateSubStartedDate($db_subscription);
		$db_subscription->setSubPeriodEndsDate($end_date);
		$db_subscription = BillingsSubscriptionDAO::updateSubEndsDate($db_subscription);
		//
		$db_subscription->setUpdateType($update_type);
		$db_subscription = BillingsSubscriptionDAO::updateUpdateType($db_subscription);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription = BillingsSubscriptionDAO::updateUpdateId($db_subscription);
		//$db_subscription->setDeleted(false);//STATIC
		//
		$this->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
		//
		config::getLogger()->addInfo($this->provider->getName()." dbsubscription update for userid=".$user->getId().", ".$this->provider->getName()."_subscription_uuid=".$db_subscription->getSubUid().", id=".$db_subscription->getId()." done successfully");
		return($this->doFillSubscription($db_subscription));
	}
	
	public function doCancelSubscription(BillingsSubscription $subscription, CancelSubscriptionRequest $cancelSubscriptionRequest) {
		try {
			config::getLogger()->addInfo($this->provider->getName()." subscription canceling...");
			if(
					$subscription->getSubStatus() == "canceled"
					||
					$subscription->getSubStatus() == "expired"
					)
			{
				//nothing todo : already done or in process
			} else {
				//
				if($cancelSubscriptionRequest->getOrigin() == 'api') {
					$plan = PlanDAO::getPlanById($subscription->getPlanId());
					$subOpts = BillingsSubscriptionOptsDAO::getBillingsSubscriptionOptsBySubId($subscription->getId());
					$googleClient = new GoogleClient();
					$googleCancelSubscriptionRequest = new GoogleCancelSubscriptionRequest();
					$googleCancelSubscriptionRequest->setSubscriptionId($plan->getPlanUuid());
					$googleCancelSubscriptionRequest->setToken($subOpts->getOpts()['customerBankAccountToken']);
					$api_subscription = $googleClient->cancelSubscription($googleCancelSubscriptionRequest);
					config::getLogger()->addInfo($this->provider->getName()." subscription canceling...result=".var_export($api_subscription, true));
				}
				$subscription->setSubCanceledDate($cancelSubscriptionRequest->getCancelDate());
				$subscription->setSubStatus('canceled');
				//
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					BillingsSubscriptionDAO::updateSubCanceledDate($subscription);
					BillingsSubscriptionDAO::updateSubStatus($subscription);
					//COMMIT
					pg_query("COMMIT");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			}
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo($this->provider->getName()." subscription canceling done successfully for ".$this->provider->getName()."_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while canceling a ".$this->provider->getName()." subscription for ".$this->provider->getName()."_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." subscription canceling failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while canceling a ".$this->provider->getName()." subscription for ".$this->provider->getName()."_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." subscription canceling failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
	
	public function doExpireSubscription(BillingsSubscription $subscription, ExpireSubscriptionRequest $expireSubscriptionRequest) {
		try {
			config::getLogger()->addInfo($this->provider->getName()." subscription expiring...");
			if(
					$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				//
				$expiresDate = $expireSubscriptionRequest->getExpiresDate();
				//
				if(in_array($subscription->getSubStatus(), ['active', 'canceled'])) {
					if($subscription->getSubPeriodEndsDate() > $expiresDate) {
						if($expireSubscriptionRequest->getForceBeforeEndsDate() == false) {
							$msg = "cannot expire a ".$this->provider->getName()." subscription that has not ended yet";
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_EXP_BEFORE_ENDS_DATE_UNSUPPORTED);
						}
					}
				}
				if($expireSubscriptionRequest->getOrigin() == 'api') {
					if($expireSubscriptionRequest->getIsRefundEnabled() != true) {
						$msg = "cannot expire and NOT refund a ".$this->provider->getName()." subscription";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_EXP_REFUND_MANDATORY);
					}
					$plan = PlanDAO::getPlanById($subscription->getPlanId());
					$subOpts = BillingsSubscriptionOptsDAO::getBillingsSubscriptionOptsBySubId($subscription->getId());
					$googleClient = new GoogleClient();
					$googleExpireSubscriptionRequest = new GoogleExpireSubscriptionRequest();
					$googleExpireSubscriptionRequest->setSubscriptionId($plan->getPlanUuid());
					$googleExpireSubscriptionRequest->setToken($subOpts->getOpts()['customerBankAccountToken']);
					$api_subscription = $googleClient->expireSubscription($googleExpireSubscriptionRequest);
					config::getLogger()->addError($this->provider->getName()." subscription expiring...result=".var_export($api_subscription, true));
				}
				$subscription->setSubExpiresDate($expiresDate);
				$subscription->setSubStatus('expired');
				//
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					BillingsSubscriptionDAO::updateSubCanceledDate($subscription);
					BillingsSubscriptionDAO::updateSubExpiresDate($subscription);
					BillingsSubscriptionDAO::updateSubStatus($subscription);
					//COMMIT
					pg_query("COMMIT");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			}
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo($this->provider->getName()." subscription expiring done successfully for ".$this->provider->getName()."_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while expiring a ".$this->provider->getName()." subscription for ".$this->provider->getName()."_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." subscription expiring failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring a ".$this->provider->getName()." subscription for ".$this->provider->getName()."_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." subscription expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
	
	public function doFillSubscription(BillingsSubscription $subscription = NULL) {
		$subscription = parent::doFillSubscription($subscription);
		if($subscription == NULL) {
			return NULL;
		}
		$is_active = NULL;
		switch($subscription->getSubStatus()) {
			case 'active' :
			case 'canceled' :
				$now = new DateTime();
				$periodStartedDate = $subscription->getSubPeriodStartedDate()->setTimezone(new DateTimeZone(config::$timezone));
				$periodEndsDate = $subscription->getSubPeriodEndsDate()->setTimezone(new DateTimeZone(config::$timezone));
				$periodEndsDate->setTime(23, 59, 59);
				$periodeGraceEndsDate = clone $periodEndsDate;
				$periodeGraceEndsDate->add(new DateInterval("P1D"));//1 full day of grace period
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
				config::getLogger()->addWarning("google dbsubscription unknown subStatus=".$subscription->getSubStatus().", google_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;
		}
		$subscription->setIsActive($is_active);
		return($subscription);
	}
	
	public function doUpdateUserSubscription(BillingsSubscription $db_subscription, UpdateSubscriptionRequest $updateSubscriptionRequest) {
		$user = UserDAO::getUserById($db_subscription->getUserId());
		if($user == NULL) {
			$msg = "unknown user with id : ".$db_subscription->getUserId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
		$plan = PlanDAO::getPlanById($db_subscription->getPlanId());
		if($plan == NULL) {
			$msg = "unknown plan with id : ".$db_subscription->getPlanId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
		$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($plan->getId()));
		if($internalPlan == NULL) {
			$msg = "plan with uuid=".$plan->getPlanUuid()." for provider ".$provider->getName()." is not linked to an internal plan";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
		$subOpts = BillingsSubscriptionOptsDAO::getBillingsSubscriptionOptsBySubId($db_subscription->getId());
		//
		$googleClient = new GoogleClient();
		$googleGetSubscriptionRequest = new GoogleGetSubscriptionRequest();
		$googleGetSubscriptionRequest->setSubscriptionId($plan->getPlanUuid());
		$googleGetSubscriptionRequest->setToken($subOpts->getOpts()['customerBankAccountToken']);
		$api_subscription = $googleClient->getSubscription($googleGetSubscriptionRequest);
		//
		$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, 
				$userOpts, 
				$this->provider, 
				$internalPlan, 
				$internalPlanOpts, 
				$plan, 
				$planOpts, 
				$api_subscription, 
				$db_subscription, 
				'api', 
				0);
		return($this->doFillSubscription($db_subscription));
	}
}

?>