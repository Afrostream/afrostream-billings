<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../client/IdipperClient.php';

class IdipperSubscriptionsHandler extends SubscriptionsHandler {
	
	public function __construct() {
	}

	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_billing_uuid, $subscription_provider_uuid, BillingInfo $billingInfo, BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("idipper subscription creation...");
			//pre-requisite
			checkSubOptsArray($subOpts->getOpts(), 'idipper');
			if(!isset($subscription_provider_uuid)) {
				$msg = "field 'subscriptionProviderUuid' was not provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($subscription_provider_uuid == 'generate') {
				$subscription_provider_uuid = guid();
			}
			//Verification : Just that abonne = 1 for the good Rubrique (we cannot do more)
			$idipperClient = new IdipperClient();
			$utilisateurRequest = new UtilisateurRequest();
			$utilisateurRequest->setExternalUserID($user->getUserProviderUuid());
			$utilisateurResponse = $idipperClient->getUtilisateur($utilisateurRequest);
			$rubriqueFound = false;
			$hasSubscribed = false;
			foreach ($utilisateurResponse->getRubriques() as $rubrique) {
				if($rubrique->getIDRubrique() == $plan->getPlanUuid()) {
					$rubriqueFound = true;
					if($rubrique->getAbonne() == '1') {
						$hasSubscribed = true;
					}
					break;
				}
			}
			if(!$rubriqueFound) {
				$msg = "rubrique with id=".$plan->getPlanUuid()." was not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!$hasSubscribed) {
				$msg = "rubrique with id=".$plan->getPlanUuid()." not subscribed";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$sub_uuid = $subscription_provider_uuid;
			config::getLogger()->addInfo("idipper subscription creation done successfully, idipper_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a idipper subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("idipper subscription creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a idipper subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("idipper subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($sub_uuid);
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, $sub_uuid, $update_type, $updateId) {
		//Verification : Just that abonne = 1 for the good Rubrique (we cannot do more)
		$idipperClient = new IdipperClient();
		$utilisateurRequest = new UtilisateurRequest();
		$utilisateurRequest->setExternalUserID($user->getUserProviderUuid());
		$utilisateurResponse = $idipperClient->getUtilisateur($utilisateurRequest);
		$current_rubrique = NULL;
		$rubriqueFound = false;
		$hasSubscribed = false;
		foreach ($utilisateurResponse->getRubriques() as $rubrique) {
			if($rubrique->getIDRubrique() == $plan->getPlanUuid()) {
				$current_rubrique = $rubrique;
				$rubriqueFound = true;
				if($rubrique->getAbonne() == '1') {
					$hasSubscribed = true;
				}
				break;
			}
		}
		if(!$rubriqueFound) {
			$msg = "rubrique with id=".$plan->getPlanUuid()." was not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if(!$hasSubscribed) {
			$msg = "rubrique with id=".$plan->getPlanUuid()." not subscribed";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//start_date
		$start_date = new DateTime();
		//end_date
		$end_date_str = $current_rubrique->getCreditExpiration();
		
		$end_date = DateTime::createFromFormat("Y-m-d H:i:s", $end_date_str, new DateTimeZone(config::$timezone));
		if($end_date === false) {
			$msg = "rubrique credit expiration date cannot be processed, date : ".$end_date_str." cannot be parsed";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$end_date->setTime(23, 59, 59);//force the time to the end of the day
		
		$api_subscription = new BillingsSubscription();
		$api_subscription->setSubUid($sub_uuid);
		$api_subscription->setSubStatus('active');
		$api_subscription->setSubActivatedDate($start_date);
		$api_subscription->setSubPeriodStartedDate($start_date);
		$api_subscription->setSubPeriodEndsDate($end_date);
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $billingInfo, $subscription_billing_uuid, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, BillingsSubscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("idipper dbsubscription creation for userid=".$user->getId().", idipper_subscription_uuid=".$api_subscription->getSubUid()."...");
		//CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid($subscription_billing_uuid);
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

		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription->setDeleted('false');
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
		config::getLogger()->addInfo("idipper dbsubscription creation for userid=".$user->getId().", idipper_subscription_uuid=".$api_subscription->getSubUid()." done successfully, id=".$db_subscription->getId());
		return($db_subscription);
	}
	
	protected function doFillSubscription(BillingsSubscription $subscription = NULL) {
		if($subscription == NULL) {
			return;
		}
		$is_active = NULL;
		$periodStartedDate = $subscription->getSubPeriodStartedDate()->setTimezone(new DateTimeZone(config::$timezone));
		$periodEndsDate = $subscription->getSubPeriodEndsDate()->setTimezone(new DateTimeZone(config::$timezone));
		$periodEndsDate->setTime(23, 59, 59);
		$periodeGraceEndsDate = clone $periodEndsDate;
		$periodeGraceEndsDate->add(new DateInterval("P7D"));//7 full days of grace period
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
				config::getLogger()->addWarning("idipper dbsubscription unknown subStatus=".$subscription->getSubStatus().", idipper_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;
		}
		//done
		$subscription->setIsActive($is_active);
	}
	
	public function doRenewSubscription(BillingsSubscription $subscription, DateTime $start_date = NULL, DateTime $end_date = NULL) {
		if($end_date == NULL) {
			$msg = "renewing a idipper subscription does not support that end_date is NOT set";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($subscription->getSubStatus() != "active" && $subscription->getSubStatus() != "pending_active") {
			$msg = "cannot renew because of the current_status=".$subscription->getSubStatus();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	
		if($start_date == NULL) {
			$start_date = $subscription->getSubPeriodEndsDate();
		}
		$start_date->setTimezone(new DateTimeZone(config::$timezone));
		$end_date->setTimezone(new DateTimeZone(config::$timezone));
		
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
		return(BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId()));
	}
	
	public function doCancelSubscription(BillingsSubscription $subscription, DateTime $cancel_date, $is_a_request = true) {
		try {
			config::getLogger()->addInfo("idipper subscription cancel...");
			if(
					$subscription->getSubStatus() == "canceled"
					||
					$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				$to_be_canceled = false;
				if($is_a_request == true) {
					$user = UserDAO::getUserById($subscription->getUserId());
					if($user == NULL) {
						$msg = "unknown user with id : ".$subscription->getUserId();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					$plan = PlanDAO::getPlanById($subscription->getPlanId());
					if($plan == NULL) {
						$msg = "unknown plan with id : ".$subscription->getPlanId();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					$idipperClient = new IdipperClient();
					$utilisateurRequest = new UtilisateurRequest();
					$utilisateurRequest->setExternalUserID($user->getUserReferenceUuid());
					$utilisateurReponse = $idipperClient->getUtilisateur($utilisateurRequest);
					$current_rubrique = NULL;
					$rubriqueFound = false;
					$hasSubscribed = false;
					foreach ($utilisateurResponse->getRubriques() as $rubrique) {
						if($rubrique->getIDRubrique() == $plan->getPlanUuid()) {
							$current_rubrique = $rubrique;
							$rubriqueFound = true;
							if($rubrique->getAbonne() == '1') {
								$hasSubscribed = true;
							}
							break;
						}
					}
					if(!$rubriqueFound) {
						$msg = "rubrique with id=".$plan->getPlanUuid()." was not found";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					if($hasSubscribed) {
						$msg = "cannot cancel because the subscription is still active";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					} else {
						$to_be_canceled = true;
					}
				} else {
					$to_be_canceled = true;
				}
				if($to_be_canceled) {
					$subscription->setSubCanceledDate($cancel_date);
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
			}
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("idipper subscription cancel done successfully for idipper_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while canceling a idipper subscription for idipper_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("idipper subscription canceling failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while canceling a idipper subscription for idipper_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("idipper subscription canceling failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($subscription);
	}
	
	public function doExpireSubscription(BillingsSubscription $subscription, DateTime $expires_date, $is_a_request = true) {
		try {
			config::getLogger()->addInfo("idipper subscription expiring...");
			if(
					$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				//
				if($subscription->getSubStatus() != "canceled") {
					//exception
					$msg = "cannot expire a subscription that has not been canceled";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($subscription->getSubPeriodEndsDate() > $expires_date) {
					//exception
					$msg = "cannot expire a subscription that has not ended yet";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$subscription->setSubExpiresDate($expires_date);
				$subscription->setSubStatus("expired");
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					BillingsSubscriptionDAO::updateSubExpiresDate($subscription);
					BillingsSubscriptionDAO::updateSubStatus($subscription);
					//COMMIT
					pg_query("COMMIT");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			}
			//
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("idipper subscription expiring done successfully for idipper_subscription_uuid=".$subscription->getSubUid());
			return($subscription);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while expiring a idipper subscription for idipper_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("idipper subscription expiring failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring a idipper subscription for idipper_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("idipper subscription expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		parent::doSendSubscriptionEvent($subscription_before_update, $subscription_after_update);
	}
	
}

?>