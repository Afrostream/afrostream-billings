<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../client/NetsizeClient.php';
require_once __DIR__ . '/../../global/subscriptions/ProviderSubscriptionsHandler.php';
require_once __DIR__ . '/../../global/requests/ExpireSubscriptionRequest.php';
		
class NetsizeSubscriptionsHandler extends ProviderSubscriptionsHandler {
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_billing_uuid, $subscription_provider_uuid, BillingInfo $billingInfo, BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("netsize subscription creation...");
			if(isset($subscription_provider_uuid)) {
				checkSubOptsArray($subOpts->getOpts(), 'netsize', 'get');
				//in netsize : user subscription is pre-created and must be finalized
				$netsizeClient = new NetsizeClient();
				
				$finalizeRequest = new FinalizeRequest();
				$finalizeRequest->setTransactionId($subscription_provider_uuid);
				
				$finalizeResponse = $netsizeClient->finalize($finalizeRequest);
				//421 - Activated (Auto Billed)
				$array_TransactionStatusCode_ok = [421];
				if(!in_array($finalizeResponse->getTransactionStatusCode(), $array_TransactionStatusCode_ok)) {
					$msg = "transaction-status/@code ".$finalizeResponse->getTransactionStatusCode()." is not correct";
					config::getLogger()->addError("netsize subscription creation failed : ".$msg);
					throw new BillingsException(new ExceptionType(ExceptionType::provider), $msg);
				}
				$sub_uuid = $subscription_provider_uuid;
			} else {
				$msg = "unsupported feature for provider named netsize, subscriptionProviderUuid has to be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				/*checkSubOptsArray($subOpts->getOpts(), 'netsize', 'create');
				// in netsize : user subscription is NOT pre-created
				$netsizeClient = new NetsizeClient();
				
				$initializeSubscriptionRequest = new InitializeSubscriptionRequest();
				$initializeSubscriptionRequest->setFlowId($subOpts->getOpts()['flowId']);
				$initializeSubscriptionRequest->setSubscriptionModelId($plan->getPlanUuid());
				$initializeSubscriptionRequest->setProductName($plan->getName());
				$initializeSubscriptionRequest->setProductType(getEnv('NETSIZE_API_PRODUCT_TYPE'));
				$initializeSubscriptionRequest->setProductDescription($plan->getDescription());
				$initializeSubscriptionRequest->setCountryCode('FR');
				$initializeSubscriptionRequest->setLanguageCode('fr');
				$initializeSubscriptionRequest->setMerchantUserId($user->getUserBillingUuid());
				//
				$initializeSubscriptionResponse = $netsizeClient->initializeSubscription($initializeSubscriptionRequest);
				if($initializeSubscriptionResponse->getTransactionStatusCode() != 110) {
					$msg = "netsize transactionStatusCode=".$initializeSubscriptionResponse->getTransactionStatusCode()." not compatible";
					config::getLogger()->addError("netsize subscription creation failed : ".$msg);
					throw new BillingsException(new ExceptionType(ExceptionType::provider), $msg, ExceptionError::NETSIZE_INCOMPATIBLE);
				}
				$subOpts->setOpt('authUrl', $initializeSubscriptionResponse->getAuthUrlUrl());
				
				$sub_uuid = $initializeSubscriptionResponse->getTransactionId();*/
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
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, $sub_uuid, $update_type, $updateId) {
		//
		$netsizeClient = new NetsizeClient();
		
		$getStatusRequest = new GetStatusRequest();
		$getStatusRequest->setTransactionId($sub_uuid);
		
		$getStatusResponse = $netsizeClient->getStatus($getStatusRequest);
		//
		$api_subscription = $getStatusResponse;
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $billingInfo, $subscription_billing_uuid, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, GetStatusResponse $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("netsize dbsubscription creation for userid=".$user->getId().", netsize_subscription_uuid=".$api_subscription->getTransactionId()."...");
		//CREATE
		$now = new DateTime();
		$now->setTimezone(new DateTimeZone(config::$timezone));
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid($subscription_billing_uuid);
		$db_subscription->setProviderId($provider->getId());
		$db_subscription->setUserId($user->getId());
		$db_subscription->setPlanId($plan->getId());
		$db_subscription->setSubUid($api_subscription->getTransactionId());
		switch ($api_subscription->getTransactionStatusCode()) {
			case 110 ://Authentification Pending
			case 120 ://Success
			case 210 ://Pending
			case 220 ://Success
			case 400 ://Started
			case 410 ://Pending
				$db_subscription->setSubStatus('future');
				break;
			case 420 ://Activated
			case 421 ://Activated (Auto Billed)
				$db_subscription->setSubStatus('active');
				$db_subscription->setSubActivatedDate($now);
				$start_date = $now;
				$end_date = NULL;
				switch($internalPlan->getPeriodUnit()) {
					case PlanPeriodUnit::day :
						$end_date = clone $start_date;
						$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."D"));
						$end_date->setTime(23, 59, 59);//force the time to the end of the day
						break;
					case PlanPeriodUnit::month :
						$end_date = clone $start_date;
						$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."M"));
						$end_date->setTime(23, 59, 59);//force the time to the end of the day
						break;
					case PlanPeriodUnit::year :
						$end_date = clone $start_date;
						$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."Y"));
						$end_date->setTime(23, 59, 59);//force the time to the end of the day
						break;
					default :
						$msg = "unsupported periodUnit : ".$internalPlan->getPeriodUnit()->getValue();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						break;
				}
				$db_subscription->setSubPeriodStartedDate($start_date);
				$db_subscription->setSubPeriodEndsDate($end_date);
				break;
			case 422 ://Activated (Termination in Progress)
			case 432 ://Cancelled
				$db_subscription->setSubStatus('canceled');
				$db_subscription->setSubCanceledDate($now);
				break;
			case 430 ://Expired
			case 431 ://Suspended
			case 433 ://Failed
				$db_subscription->setSubStatus('expired');
				$db_subscription->setSubExpiresDate($now);
				break;
			default :
				$msg = "unknown subscription state : ".$api_subscription->getTransactionStatusCode();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
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
		config::getLogger()->addInfo("netsize dbsubscription creation for userid=".$user->getId().", netsize_subscription_uuid=".$api_subscription->getTransactionId()." done successfully, id=".$db_subscription->getId());
		return($this->doFillSubscription($db_subscription));
	}
	
	public function doCancelSubscription(BillingsSubscription $subscription, DateTime $cancel_date, $is_a_request = true) {
		try {
			config::getLogger()->addInfo("netsize subscription canceling...");
			$doIt = false;
			/*if($is_a_request == true) {
				if(
						$subscription->getSubStatus() == "requesting_canceled"
						||
						$subscription->getSubStatus() == "canceled"
						||
						$subscription->getSubStatus() == "expired"
				) {
					//nothing to do : already done or in process
				} else {
					//
					$doIt = true;
					$netsizeClient = new NetsizeClient();
						
					$closeSubscriptionRequest = new CloseSubscriptionRequest();
					$closeSubscriptionRequest->setTransactionId($subscription->getSubUid());
					$closeSubscriptionRequest->setTrigger(0);
					$closeSubscriptionRequest->setReturnUrl('');//TODO : is it needed ?
					
					$closeSubscriptionResponse = $netsizeClient->closeSubscription($closeSubscriptionRequest);
					
					if($closeSubscriptionResponse->getTransactionStatusCode() != 422) {
						$msg = "netsize subscription cannot be canceled, code=".$getStatusResponse->getTransactionStatusCode();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::provider), $msg);
					}
				}
			} else {*/
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
					//422 - Activated (Termination in Progress)
					//432 - Cancelled
					$array_sub_is_canceled = [422, 432];
					if(!in_array($getStatusResponse->getTransactionStatusCode(), $array_sub_is_canceled)) {
						$msg = "netsize subscription cannot be canceled, code=".$getStatusResponse->getTransactionStatusCode();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::provider), $msg);
					}
				}
			/*}*/
			if($doIt == true) {
				$subscription->setSubCanceledDate($cancel_date);
				/*if($is_a_request == true) {
					$subscription->setSubStatus('requesting_canceled');
				} else {*/
					$subscription->setSubStatus('canceled');
				/*}*/
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
		return($this->doFillSubscription($subscription));
	}
	
	public function doRenewSubscription(BillingsSubscription $subscription, DateTime $start_date = NULL, DateTime $end_date = NULL) {
		if($end_date != NULL) {
			$msg = "renewing a netsize subscription does not support that end_date is already set";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($subscription->getSubStatus() != "active") {
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
			$msg = "plan with uuid=".$providerPlan->getPlanUuid()." for provider netsize is not linked to an internal plan";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//VERIFY THAT SUBSCRIPTION IS STILL ACTIVE BEFORE RENEWING
		//TODO : like Orange + Bouygues
		$today = new DateTime();
		$today->setTimezone(new DateTimeZone(config::$timezone));
		$today->setTime(23, 59, 59);//consider all the day
		
		if($start_date == NULL) {
			$start_date = $subscription->getSubPeriodEndsDate();
		}
		$start_date->setTimezone(new DateTimeZone(config::$timezone));
		
		$end_date = clone $start_date;
		
		$to_be_updated = false;

		switch($internalPlan->getPeriodUnit()) {
			case PlanPeriodUnit::day :
				while ($end_date < $today) {
					$to_be_updated = true;
					$start_date = clone $end_date;
					$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."D"));
					$end_date->setTime(23, 59, 59);//force the time to the end of the day
				}
				break;
			case PlanPeriodUnit::month :
				while ($end_date < $today) {
					$to_be_updated = true;
					$start_date = clone $end_date;
					$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."M"));
					$end_date->setTime(23, 59, 59);//force the time to the end of the day
				}
				break;	
			case PlanPeriodUnit::year :
				while ($end_date < $today) {
					$to_be_updated = true;
					$start_date = clone $end_date;
					$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."Y"));
					$end_date->setTime(23, 59, 59);//force the time to the end of the day
				}
				break;
			default :
				$msg = "unsupported periodUnit : ".$internalPlan->getPeriodUnit()->getValue();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
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
	
	public function doExpireSubscription(BillingsSubscription $subscription, ExpireSubscriptionRequest $expireSubscriptionRequest) {
		try {
			config::getLogger()->addInfo("netsize subscription expiring...");
			$doIt = false;
			//
			$expiresDate = $expireSubscriptionRequest->getExpiresDate();
			//
			if(
				$subscription->getSubStatus() == "expired"
			)
			{
				//nothing to do : already done or in process
			} else {
				//NC : only check via the API as we are not sure about dates when looking status in the script
				if($expireSubscriptionRequest->getOrigin() == 'api') {
					if(in_array($subscription->getSubStatus(), ['active', 'canceled'])) {
						if($subscription->getSubPeriodEndsDate() > $expiresDate) {
							if($expireSubscriptionRequest->getForceBeforeEndsDate() == false) {
								$msg = "cannot expire a ".$this->provider->getName()." subscription that has not ended yet";
								config::getLogger()->addError($msg);
								throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							}
						}
					}
				}
				$doIt = true;
				$netsizeClient = new NetsizeClient();
				
				$getStatusRequest = new GetStatusRequest();
				$getStatusRequest->setTransactionId($subscription->getSubUid());
				
				$getStatusResponse = $netsizeClient->getStatus($getStatusRequest);
				//430 - Expired
				//431 - Suspended
				//432 - Cancelled
				//433 - Failed
				$array_sub_can_be_expired = [430, 431, 432, 433];
				if(!in_array($getStatusResponse->getTransactionStatusCode(), $array_sub_can_be_expired)) {
					$msg = "netsize subscription cannot be expired, code=".$getStatusResponse->getTransactionStatusCode();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::provider), $msg);
				}
			}
			if($doIt == true) {
				$subscription->setSubExpiresDate($expiresDate);
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
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("netsize subscription expiring done successfully for netsize_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while expiring a netsize subscription for netsize_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("netsize subscription expiring failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring a netsize subscription for netsize_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("netsize subscription expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
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
				$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($plan->getId()));
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
				$msg = "netsize dbsubscription update failed for subscriptionBillingUuid=".$db_subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
				config::getLogger()->addError($msg);
			}		
		}
		config::getLogger()->addInfo("netsize dbsubscriptions update for userid=".$user->getId()." done successfully");
	}

	public function updateDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, GetStatusResponse $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("netsize dbsubscription update for userid=".$user->getId().", netsize_subscription_uuid=".$api_subscription->getTransactionId().", id=".$db_subscription->getId()."...");
		//UPDATE
		$db_subscription_before_update = clone $db_subscription;
		//
		$now = new DateTime();
		//$db_subscription->setProviderId($provider->getId());//STATIC
		//$db_subscription->setUserId($user->getId());//STATIC
		$db_subscription->setPlanId($plan->getId());
		$db_subscription = BillingsSubscriptionDAO::updatePlanId($db_subscription);
		//$db_subscription->setSubUid($subscription_uuid);//STATIC
		switch ($api_subscription->getTransactionStatusCode()) {
			case 110 ://Authentification Pending
			case 120 ://Success
			case 210 ://Pending
			case 220 ://Success
			case 400 ://Started
			case 410 ://Pending
				$db_subscription->setSubStatus('future');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				break;
			case 420 ://Activated
			case 421 ://Activated (Auto Billed)
				$db_subscription->setSubStatus('active');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				if($db_subscription->getSubActivatedDate() == NULL) {
					$db_subscription->setSubActivatedDate($now);//assume it's now only if not already set
					$db_subscription = BillingsSubscriptionDAO::updateSubActivatedDate($db_subscription);
				}
				if($db_subscription->getSubPeriodStartedDate() == NULL) {
					$db_subscription->setSubPeriodStartedDate($now);//assume it's now only if not already set
					$db_subscription = BillingsSubscriptionDAO::updateSubStartedDate($db_subscription);
				}
				if($db_subscription->getSubPeriodEndsDate() == NULL) {
					$start_date = $db_subscription->getSubPeriodStartedDate();
					$start_date->setTimezone(new DateTimeZone(config::$timezone));
					$end_date = NULL;
					switch($internalPlan->getPeriodUnit()) {
						case PlanPeriodUnit::day :
							$end_date = clone $start_date;
							$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."D"));
							$end_date->setTime(23, 59, 59);//force the time to the end of the day
							break;
						case PlanPeriodUnit::month :
							$end_date = clone $start_date;
							$periodLengthInDays = 30 * $internalPlan->getPeriodLength();
							$end_date->add(new DateInterval("P".$periodLengthInDays."D"));
							$end_date->setTime(23, 59, 59);//force the time to the end of the day
							break;
						case PlanPeriodUnit::year :
							$end_date = clone $start_date;
							$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."Y"));
							$end_date->setTime(23, 59, 59);//force the time to the end of the day
							break;
						default :
							$msg = "unsupported periodUnit : ".$internalPlan->getPeriodUnit()->getValue();
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							break;
					}
					$db_subscription->setSubPeriodEndsDate($end_date);
					$db_subscription = BillingsSubscriptionDAO::updateSubEndsDate($db_subscription);
				}
				break;
			case 422 ://Activated (Termination in Progress)
			case 432 ://Cancelled
				$db_subscription->setSubStatus('canceled');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				if($db_subscription->getSubCanceledDate() == NULL) {
					$db_subscription->setSubCanceledDate($now);//assume it's now only if not already set
					$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
				}
				break;
			case 430 ://Expired
			case 431 ://Suspended
			case 433 ://Failed
				$db_subscription->setSubStatus('expired');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				if($db_subscription->getSubExpiresDate() == NULL) {
					$db_subscription->setSubExpiresDate($now);//assume it's now only if not already set
					$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
				}
				break;
			default :
				$msg = "unknown subscription state : ".$api_subscription->getTransactionStatusCode();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
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
		config::getLogger()->addInfo("netsize dbsubscription update for userid=".$user->getId().", netsize_subscription_uuid=".$api_subscription->getTransactionId().", id=".$db_subscription->getId()." done successfully");
		return($this->doFillSubscription($db_subscription));
	}
	
	protected function doFillSubscription(BillingsSubscription $subscription = NULL) {
		$subscription = parent::doFillSubscription($subscription);
		if($subscription == NULL) {
			return NULL;
		}
		$is_active = NULL;
		switch($subscription->getSubStatus()) {
			case 'active' :
			case 'canceled' :
				$now = new DateTime();
				//check dates
				if(
						($now < $subscription->getSubPeriodEndsDate())
						&&
						($now >= $subscription->getSubPeriodStartedDate())
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
				config::getLogger()->addWarning("netsize dbsubscription unknown subStatus=".$subscription->getSubStatus().", netsize_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;
		}
		//done
		$subscription->setIsActive($is_active);
		return($subscription);
	}
	
	public function doUpdateUserSubscription(BillingsSubscription $db_subscription) {
		$user = UserDAO::getUserById($db_subscription->getUserId());
		if($user == NULL) {
			$msg = "unknown user with id : ".$db_subscription->getUserId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
		$provider = ProviderDAO::getProviderById($db_subscription->getProviderId());
		//
		if($provider == NULL) {
			$msg = "unknown provider id : ".$db_subscription->getProviderId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$plan = PlanDAO::getPlanById($db_subscription->getPlanId());
		if($plan == NULL) {
			$msg = "unknown plan with id : ".$subscription->getPlanId();
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
		//
		$netsizeClient = new NetsizeClient();
		$getStatusRequest = new GetStatusRequest();
		$getStatusRequest->setTransactionId($db_subscription->getSubUid());
		$getStatusResponse = $netsizeClient->getStatus($getStatusRequest);
		$api_subscription = $getStatusResponse;
		$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, 'api', 0);
		return($this->doFillSubscription($db_subscription));
	}
	
}

?>