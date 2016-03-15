<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../client/soap-wsse.php';
require_once __DIR__ . '/../client/WSSoapClient.class.php';
require_once __DIR__ . '/../client/ByTelBAchat.class.php';

class BachatSubscriptionsHandler extends SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_provider_uuid, BillingInfoOpts $billingInfoOpts, BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("bachat subscription creation...");
			//pre-requisite
			checkSubOptsArray($subOpts->getOpts(), 'bachat');
			if(isset($subscription_provider_uuid)) {
				$msg = "field 'subscriptionProviderUuid' must not be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			$requestId = $subOpts->getOpts()['requestId'];
			$idSession = $subOpts->getOpts()['idSession'];
			$otpCode = $subOpts->getOpts()['otpCode'];
			//
			$bachat = new ByTelBAchat();
			
			$res = $bachat->requestEDBBilling($requestId, $idSession, $otpCode);
			if($res->resultMessage == "SUCCESS") {
				//OK
				config::getLogger()->addInfo("BACHAT OK, result=".$res->result.", subscriptionId=".$res->subscriptionId.", requestId=".$res->requestId.", chargeTransactionId=".$res->chargeTransactionId);
				$subOpts->setOpt('chargeTransactionId', $res->chargeTransactionId);
				$subscription_provider_uuid = $res->subscriptionId;
			} else {
				//KO
				$msg = "BACHAT ERROR, result=".var_export($res, true);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "BACHAT ERROR, see logs for more details");
			}
			//
			$sub_uuid = $subscription_provider_uuid;
			config::getLogger()->addInfo("bachat subscription creation done successfully, bachat_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a bachat subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("bachat subscription creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a bachat subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("bachat subscription creation failed : ".$msg);
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
		$end_date = NULL;
		switch($internalPlan->getPeriodUnit()) {
			case PlanPeriodUnit::day :
				$end_date = clone $start_date;
				$end_date->add(new DateInterval("P".($internalPlan->getPeriodLength() - 1)."D"));//fix first day must be taken in account
				//DO NOT FORCE ANYMORE AS RECOMMENDED BY Niji
				//$end_date->setTime(23, 59, 59);//force the time to the end of the day
				break;
			default :
				$msg = "unsupported periodUnit : ".$internalPlan->getPeriodUnit()->getValue();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
		$api_subscription->setSubPeriodEndsDate($end_date);
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingsSubscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("bachat dbsubscription creation for userid=".$user->getId().", bachat_subscription_uuid=".$api_subscription->getSubUid()."...");
		if($subOpts == NULL) {
			$msg = "subOpts is NULL";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid($subOpts->getOpts()['subscriptionBillingUuid']);
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
		config::getLogger()->addInfo("bachat dbsubscription creation for userid=".$user->getId().", bachat_subscription_uuid=".$api_subscription->getSubUid()." done successfully, id=".$db_subscription->getId());
		return($db_subscription);
	}
	
	protected function doFillSubscription(BillingsSubscription $subscription = NULL) {
		if($subscription == NULL) {
			return;
		}
		$is_active = NULL;
		$periodStartedDate = (new DateTime($subscription->getSubPeriodStartedDate()))->setTimezone(new DateTimeZone(config::$timezone));
		$periodEndsDate = (new DateTime($subscription->getSubPeriodEndsDate()))->setTimezone(new DateTimeZone(config::$timezone));
		$periodEndsDate->setTime(23, 59, 59);
		$periodeGraceEndsDate = clone $periodEndsDate;
		$periodeGraceEndsDate->add(new DateInterval("P3D"));//3 full days of grace period
		switch($subscription->getSubStatus()) {
			case 'pending_active' :
			case 'active' :
			case 'requesting_canceled' :
			case 'pending_canceled' :
			case 'canceled' :
			case 'pending_expired' :
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
				config::getLogger()->addWarning("bachat dbsubscription unknown subStatus=".$subscription->getSubStatus().", bachat_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;
		}
		$subscription->setIsActive($is_active);
	}
	
	public function doRenewSubscription(BillingsSubscription $subscription, DateTime $start_date = NULL, DateTime $end_date = NULL) {
		if($end_date != NULL) {
			$msg = "renewing a bachat subscription does not support that end_date is already set";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);			
		}
		if($subscription->getSubStatus() != "active" || $subscription->getSubStatus() != "pending_active") {
			$msg = "cannot renew because of the current_status=".$subscription->getSubStatus();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$providerPlan = PlanDAO::getPlanById($subscription->getPlanId());
		if($providerPlan == NULL) {
			$msg = "unknown plan with id : ".$subscription->getPlanId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($providerPlan->getId()));
		if($internalPlan == NULL) {
			$msg = "plan with uuid=".$providerPlan->getId()." for provider bachat is not linked to an internal plan";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($internalPlan->getPeriodUnit() != PlanPeriodUnit::day) {
			$msg = "unsupported periodUnit : ".$internalPlan->getPeriodUnit()->getValue();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		
		$today = new DateTime();
		$today->setTimezone(new DateTimeZone(config::$timezone));
		$today->setTime(0, 0, 0);
		
		if($start_date == NULL) {
			$start_date = new DateTime($subscription->getSubPeriodEndsDate());
			$start_date->add(new DateInterval("P1D"));
		}
		$start_date->setTimezone(new DateTimeZone(config::$timezone));
		
		$end_date = clone $start_date;
		
		$to_be_updated = false;
		
		while($end_date < $today) {
			$to_be_updated = true;
			$start_date = clone $end_date;
			$end_date->add(new DateInterval("P".($internalPlan->getPeriodLength() - 1)."D"));
		}
		//done
		$start_date->setTime(0, 0, 0);//force start_date to beginning of the day
		if($to_be_updated) {
			$subscription->setSubPeriodStartedDate($start_date);
			$subscription->setSubPeriodEndsDate($end_date);
			$subscription->setSubStatus('active');
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				BillingsSubscriptionDAO::updateSubStartedDate($subscription);
				BillingsSubscriptionDAO::updateSubEndsDate($subscription);
				BillingsSubscriptionDAO::updateSubStatus($subscription);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
		}
		return(BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId()));
	}
		
	public function doCancelSubscription(BillingsSubscription $subscription, DateTime $cancel_date, $is_a_request = true) {
		$doIt = false;
		if($is_a_request == true) {
			if($subscription->getSubStatus() == "pending_active") {
				$msg = "cannot cancel because of the current_status=".$subscription->getSubStatus();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(
					$subscription->getSubStatus() == "canceled"
					||
					$subscription->getSubStatus() == "requesting_canceled"
					||
					$subscription->getSubStatus() == "pending_canceled"
			)
			{
				//nothing todo : already done or in process
				$doIt = false;
			} else {
				$doIt = true;
			}
		} else {
			//do it only if not already canceled
			if($subscription->getSubStatus() != "canceled") {
				$doIt = true;
			}
		}
		if($doIt == true) {
			$subscription->setSubCanceledDate($cancel_date);
			if($is_a_request == true) {
				$subscription->setSubStatus('requesting_canceled');
			} else {
				$subscription->setSubStatus('canceled');
			}
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
		return(BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId()));
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		parent::doSendSubscriptionEvent($subscription_before_update, $subscription_after_update);
	}
	
}

?>