<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../client/NetsizeClient.php';
		
class NetsizeSubscriptionsHandler extends SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_provider_uuid, BillingInfoOpts $billingInfoOpts, BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("netsize subscription creation...");
			if(isset($subscription_provider_uuid)) {
				checkSubOptsArray($subOpts->getOpts(), 'netsize', 'get');
				//in netsize : user subscription is pre-created
				$netsizeClient = new NetsizeClient();
				
				$getStatusRequest = new GetStatusRequest();
				$getStatusRequest->setTransactionId($subscription_provider_uuid);
				
				$getStatusResponse = $netsizeClient->getStatus($getStatusRequest);
				
				$sub_uuid = $subscription_provider_uuid;
			} else {
				checkSubOptsArray($subOpts->getOpts(), 'netsize', 'create');
				// in netsize : user subscription is NOT pre-created
				$netsizeClient = new NetsizeClient();
				
				$initializeSubscriptionRequest = new InitializeSubscriptionRequest();
				//$initializeSubscriptionRequest->setFlowId(?);
				$initializeSubscriptionRequest->setSubscriptionModelId($plan->getPlanUuid());
				$initializeSubscriptionRequest->setProductName($plan->getName());
				$initializeSubscriptionRequest->setProductType(getEnv('NETSIZE_API_PRODUCT_TYPE'));
				$initializeSubscriptionRequest->setProductDescription($plan->getDescription());
				$initializeSubscriptionRequest->setCountryCode('FR');
				$initializeSubscriptionRequest->setLanguageCode('fr');
				$initializeSubscriptionRequest->setMerchantUserId($user->getUserBillingUuid());
	
				$initializeSubscriptionResponse = $netsizeClient->initializeSubscription($initializeSubscriptionRequest);
				
				$sub_uuid = $initializeSubscriptionResponse->getTransactionId();
			}
			config::getLogger()->addInfo("netsize subscription creation done successfully, netsize_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a netsize subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("netsize subscription creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a netsize subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("netsize subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($sub_uuid);
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, $sub_uuid, $update_type, $updateId) {
		//
		$netsizeClient = new NetsizeClient();
		
		$getStatusRequest = new GetStatusRequest();
		$getStatusRequest->setTransactionId($subscription_provider_uuid);
		
		$getStatusResponse = $netsizeClient->getStatus($getStatusRequest);
		//
		$api_subscription = $getStatusResponse;
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, GetStatusResponse $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("netsize dbsubscription creation for userid=".$user->getId().", netsize_subscription_uuid=".$api_subscription->getUserId()."...");
		//CREATE
		$now = new DateTime();
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid(guid());
		$db_subscription->setProviderId($provider->getId());
		$db_subscription->setUserId($user->getId());
		$db_subscription->setPlanId($plan->getId());
		$db_subscription->setSubUid($api_subscription->uuid);
		switch ($api_subscription->getTransactionStatusCode()) {
			case 400 ://Started
			case 410 ://Pending
				$db_subscription->setSubStatus('future');
				break;
			case 420 ://Activated
			case 421 ://Activated (Auto Billed)
			case 422 ://Activated (Termination in Progress)
				$db_subscription->setSubStatus('active');
				$db_subscription->setSubActivatedDate($now);
				$db_subscription->setSubPeriodStartedDate($now);
				//$db_subscription->setSubPeriodEndsDate(?);
				break;
			case 432 ://Cancelled
				$db_subscription->setSubStatus('canceled');
				$db_subscription->setSubCanceledDate($now);
				break;
			case 430 ://Expired
			case 431 ://Suspended
				$db_subscription->setSubStatus('expired');
				$db_subscription->setSubExpiresDate($now);
				break;
			case 433 ://Failed
				$msg = "netsize subscription creation failed (code 433)";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
			default :
				$msg = "unknown subscription state : ".$api_subscription->getTransactionStatusCode();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
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
		config::getLogger()->addInfo("netsize dbsubscription creation for userid=".$user->getId().", netsize_subscription_uuid=".$api_subscription->getUserId()." done successfully, id=".$db_subscription->getId());
		return($db_subscription);
	}
	
	public function doCancelSubscription(BillingsSubscription $subscription, DateTime $cancel_date, $is_a_request = true) {
		try {
			config::getLogger()->addInfo("netsize subscription canceling...");
			$doIt = false;
			if($is_a_request == true) {
				if(
						$subscription->getSubStatus() == "requesting_canceled"
						||
						$subscription->getSubStatus() == "canceled"
						||
						$subscription->getSubStatus() == "expired"
				) {
					//nothing to do : already done or in process
				} else {
					$doIt = true;
					$netsizeClient = new NetsizeClient();
						
					$closeSubscriptionRequest = new CloseSubscriptionRequest();
					$closeSubscriptionRequest->setTransactionId($subscription->getSubUid());
					$closeSubscriptionRequest->setTrigger(1);
					$closeSubscriptionRequest->setReturnUrl('todo');//TODO
						
					$closeSubscriptionResponse = $netsizeClient->closeSubscription($closeSubscriptionRequest);
					//done
				}
			} else {
				if(
						$subscription->getSubStatus() == "canceled"
						||
						$subscription->getSubStatus() == "expired"
				) {
					//nothing to do : already done or in process
				} else {
					$doIt = true;
					$netsizeClient = new NetsizeClient();
						
					$getStatusRequest = new GetStatusRequest();
					$getStatusRequest->setTransactionId($subscription->getSubUid());
						
					$getStatusResponse = $netsizeClient->getStatus($getStatusRequest);
						
					if($getStatusResponse->getTransactionStatusCode() != 432) {
						$msg = "netsize subscription cannot be canceled, code=".$getStatusResponse->getTransactionStatusCode();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::provider), $msg);
					}
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
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("netsize subscription canceling done successfully for netsize_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while canceling a netsize subscription for netsize_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("netsize subscription canceling failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while canceling a netsize subscription for netsize_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("netsize subscription canceling failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($subscription);
	}
	
	public function doRenewSubscription(BillingsSubscription $subscription, DateTime $start_date = NULL, DateTime $end_date = NULL) {
		if($end_date == NULL) {
			$msg = "renewing a netsize subscription does not support that end_date is NOT set";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($subscription->getSubStatus() != "active") {
			$msg = "cannot renew because of the current_status=".$subscription->getSubStatus();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($start_date == NULL) {
			$start_date = $subscription->getSubPeriodEndsDate();
		}
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
		return(BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId()));
	}
	
	public function doExpireSubscription(BillingsSubscription $subscription, DateTime $expires_date, $is_a_request = true) {
		try {
			config::getLogger()->addInfo("netsize subscription expiring...");
			if(
					$subscription->getSubStatus() == "expired"
			)
			{
				//nothing to do : already done or in process
			} else {
				//
				if($subscription->getSubPeriodEndsDate() < $expires_date) {
					$subscription->setSubExpiresDate($expires_date);
					$subscription->setSubStatus("expired");
				} else {
					$msg = "cannot expire a subscription that has not ended yet";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
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
			config::getLogger()->addInfo("netsize subscription expiring done successfully for netsize_subscription_uuid=".$subscription->getSubUid());
			return($subscription);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while expiring a netsize subscription for netsize_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("netsize subscription expiring failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring a netsize subscription for netsize_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("netsize subscription expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
		config::getLogger()->addInfo("netsize dbsubscriptions update for userid=".$user->getId()."...");
		//ONLY UPDATE
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		$provider = ProviderDAO::getProviderById($user->getProviderId());
		//
		if($provider == NULL) {
			$msg = "unknown provider id : ".$user->getProviderId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$netsizeClient = new NetsizeClient();
		foreach ($db_subscriptions as $db_subscription) {
			try {
				$plan = PlanDAO::getPlanById($db_subscription->getPlanId());
				if($plan == NULL) {
					$msg = "unknown plan with id : ".$subscription->getPlanId();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
				if($internalPlan == NULL) {
					$msg = "plan with uuid=".$plan->getPlanUuid()." for provider ".$provider->getName()." is not linked to an internal plan";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
				//
				$getStatusRequest = new GetStatusRequest();
				$getStatusRequest->setTransactionId($subscription->getSubUid());
				$getStatusResponse = $netsizeClient->getStatus($getStatusRequest);
				$api_subscription = $getStatusResponse;
				$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, 'api', 0);
			} catch(Exception $e) {
				//TODO
			}
		}
		config::getLogger()->addInfo("netsize dbsubscriptions update for userid=".$user->getId()." done successfully");
	}

	public function updateDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, GetStatusResponse $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("netsize dbsubscription update for userid=".$user->getId().", netsize_subscription_uuid=".$api_subscription->getUserId().", id=".$db_subscription->getId()."...");
		//UPDATE
		$now = new DateTime();
		//$db_subscription->setProviderId($provider->getId());//STATIC
		//$db_subscription->setUserId($user->getId());//STATIC
		$db_subscription->setPlanId($plan->getId());
		$db_subscription = BillingsSubscriptionDAO::updatePlanId($db_subscription);
		//$db_subscription->setSubUid($subscription_uuid);//STATIC
		switch ($api_subscription->getTransactionStatusCode()) {
			case 400 ://Started
			case 410 ://Pending
				$db_subscription->setSubStatus('future');
				break;
			case 420 ://Activated
			case 421 ://Activated (Auto Billed)
			case 422 ://Activated (Termination in Progress)
				$db_subscription->setSubStatus('active');
				if($db_subscription->getSubActivatedDate() == NULL) {
					$db_subscription->setSubActivatedDate($now);//assume it's now only if not already set
					$db_subscription = BillingsSubscriptionDAO::updateSubActivatedDate($db_subscription);
				}
				if($db_subscription->getSubPeriodStartedDate() == NULL) {
					$db_subscription->setSubPeriodStartedDate($now);//assume it's now only if not already set
					$db_subscription = BillingsSubscriptionDAO::updateSubStartedDate($db_subscription);
				}
				//$db_subscription->setSubPeriodEndsDate(?);
				break;
			case 432 ://Cancelled
				$db_subscription->setSubStatus('canceled');
				if($db_subscription->getSubCanceledDate() == NULL) {
					$db_subscription->setSubCanceledDate($now);//assume it's now only if not already set
					$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
				}
				break;
			case 430 ://Expired
			case 431 ://Suspended
				$db_subscription->setSubStatus('expired');
				if($db_subscription->getSubExpiresDate() == NULL) {
					$db_subscription->setSubExpiresDate($now);//assume it's now only if not already set
					$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
				}
				break;
			case 433 ://Failed
				$msg = "netsize subscription creation failed (code 433)";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
			default :
				$msg = "unknown subscription state : ".$api_subscription->getTransactionStatusCode();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
		//
		$db_subscription->setUpdateType($update_type);
		$db_subscription = BillingsSubscriptionDAO::updateUpdateType($db_subscription);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription = BillingsSubscriptionDAO::updateUpdateId($db_subscription);
		//$db_subscription->setDeleted('false');//STATIC
		//
		config::getLogger()->addInfo("netsize dbsubscription update for userid=".$user->getId().", netsize_subscription_uuid=".$api_subscription->getUserId().", id=".$db_subscription->getId()." done successfully");
		return($db_subscription);
	}
	
}

?>