<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../client/WecashupClient.php';

class WecashupSubscriptionsHandler extends SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_billing_uuid, $subscription_provider_uuid, BillingInfo $billingInfo, BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("wecashup subscription creation...");
			if(isset($subscription_provider_uuid)) {
				$msg = "unsupported feature for provider named wecashup, subscriptionProviderUuid has NOT to be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			checkSubOptsArray($subOpts->getOpts(), 'wecashup', 'create');
			$wecashupClient = new WecashupClient();
			//Check
			$wecashupTransactionRequest = new WecashupTransactionRequest();
			$wecashupTransactionRequest->setTransactionUid($subOpts->getOpt('transaction_uid'));
			$wecashupTransactionsResponse = $wecashupClient->getTransaction($wecashupTransactionRequest);
			$wecashupTransactionsResponseArray = $wecashupTransactionsResponse->getWecashupTransactionsResponseArray();
			if(count($wecashupTransactionsResponseArray) != 1) {
				//Exception
				$msg = "transaction with transactionUid=".$subOpts->getOpt('transaction_uid')." was not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$wecashupTransactionResponse = $wecashupTransactionsResponseArray[0];
			if($internalPlan->getCurrency() != $wecashupTransactionResponse->getTransactionReceiverCurrency()) {
				//Exception
				$msg = "currency of the transaction differs from currency of the plan";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);				
			}
			$amount_from_transaction = $wecashupTransactionResponse->getTransactionReceiverTotalAmount();
			$amount_in_cents_from_transaction = intval($amount_from_transaction * 100);
			if($internalPlan->getAmountInCents() != $amount_in_cents_from_transaction) {
				//Exception
				$msg = "amount in cents (".$amount_in_cents_from_transaction.") of the transaction differs from currency of the plan (".$internalPlan->getAmountInCents().")";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//Validate
			$wecashupValidateTransactionRequest = new WecashupValidateTransactionRequest();
			$wecashupValidateTransactionRequest->setTransactionUid($subOpts->getOpt('transaction_uid'));
			$wecashupValidateTransactionRequest->setTransactionToken($subOpts->getOpt('transaction_token'));
			$wecashupValidateTransactionRequest->setTransactionConfirmationCode($subOpts->getOpt('transaction_confirmation_code'));
			$wecashupValidateTransactionRequest->setTransactionProviderName($subOpts->getOpt('transaction_provider_name'));
			$wecashupValidateTransactionResponse = $wecashupClient->validateTransaction($wecashupValidateTransactionRequest);
			if($wecashupValidateTransactionResponse->getResponseStatus() != 'success') {
				$msg = "The transaction did not succeed, responseStatus=".$wecashupValidateTransactionResponse->getResponseStatus().', responseCode='.$wecashupValidateTransactionResponse->getResponseCode();
				config::getLogger()->addError("wecashup subscription creation failed : ".$msg);
				throw new BillingsException(new ExceptionType(ExceptionType::provider), $msg);			
			}
			//OK
			$sub_uuid = guid();
			config::getLogger()->addInfo("wecashup subscription creation done successfully, wecashup_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a wecashup subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("wecashup subscription creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a wecashup subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("wecashup subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($sub_uuid);
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, $sub_uuid, $update_type, $updateId) {
		//
		if($subOpts == NULL) {
			//Exception
			$msg = "field 'subOpts' is missing";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		/*$wecashupClient = new WecashupClient();
		$wecashupTransactionRequest = new WecashupTransactionRequest();
		$wecashupTransactionRequest->setTransactionUid($subOpts->getOpt('transaction_uid'));
		$wecashupTransactionsResponse = $wecashupClient->getTransaction($wecashupTransactionRequest);
		$wecashupTransactionsResponseArray = $wecashupTransactionsResponse->getWecashupTransactionsResponseArray(); 
		if(count($wecashupTransactionsResponseArray) != 1) {
			//Exception
			$msg = "transaction with transactionUid=".$subOpts->getOpt('transaction_uid')." was not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$wecashupTransactionResponse = $wecashupTransactionsResponseArray[0];
		if($wecashupTransactionResponse->getTransactionStatus() != 'success') {
			$msg = "The transaction did not succeed, responseStatus=".$wecashupTransactionResponse->getTransactionStatus();
			config::getLogger()->addError("wecashup subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $msg);
		}*/
		//
		$api_subscription = new BillingsSubscription();
		$api_subscription->setCreationDate(new DateTime());
		$api_subscription->setSubUid($sub_uuid);
		$api_subscription->setSubStatus('future');
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $billingInfo, $subscription_billing_uuid, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, BillingsSubscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("wecashup dbsubscription creation for userid=".$user->getId().", wecashup_subscription_uuid=".$api_subscription->getSubUid()."...");
		//
		if($subOpts == NULL) {
			//Exception
			$msg = "field 'subOpts' is missing";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		/*$wecashupClient = new WecashupClient();
		$wecashupTransactionRequest = new WecashupTransactionRequest();
		$wecashupTransactionRequest->setTransactionUid($subOpts->getOpt('transaction_uid'));
		$wecashupTransactionsResponse = $wecashupClient->getTransaction($wecashupTransactionRequest);
		$wecashupTransactionsResponseArray = $wecashupTransactionsResponse->getWecashupTransactionsResponseArray();
		if(count($wecashupTransactionsResponseArray) != 1) {
			//Exception
			$msg = "transaction with transactionUid=".$subOpts->getOpt('transaction_uid')." was not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$wecashupTransactionResponse = $wecashupTransactionsResponseArray[0];
		if($wecashupTransactionResponse->getTransactionStatus() != 'success') {
			$msg = "The transaction did not succeed, responseStatus=".$wecashupTransactionResponse->getTransactionStatus();
			config::getLogger()->addError("wecashup subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $msg);
		}*/
		//SUBSCRIPTION CREATE
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
		$db_subscription->setDeleted(false);
		//TRANSACTION CREATE
		/*$country = NULL;
		if($wecashupTransactionResponse->getTransactionSenderCountryCodeIso2() != NULL) {
			$country = $wecashupTransactionResponse->getTransactionSenderCountryCodeIso2();
		} else {
			$country = isset($billingInfo) ? $billingInfo->getCountryCode() : NULL;
		}*/
		$billingsTransaction = new BillingsTransaction();
		$billingsTransaction->setProviderId($user->getProviderId());
		$billingsTransaction->setUserId($user->getId());
		$billingsTransaction->setCouponId(NULL);
		$billingsTransaction->setInvoiceId(NULL);
		$billingsTransaction->setTransactionBillingUuid(guid());
		$billingsTransaction->setTransactionProviderUuid($subOpts->getOpt('transaction_uid'));
		$billingsTransaction->setTransactionCreationDate($api_subscription->getCreationDate());
		$billingsTransaction->setAmountInCents($internalPlan->getAmountInCents());
		$billingsTransaction->setCurrency($internalPlan->getCurrency());
		$billingsTransaction->setCountry(isset($billingInfo) ? $billingInfo->getCountryCode() : NULL);
		$billingsTransaction->setTransactionStatus(BillingsTransactionStatus::waiting);
		$billingsTransaction->setTransactionType(BillingsTransactionType::purchase);
		$billingsTransaction->setInvoiceProviderUuid(NULL);
		$billingsTransaction->setMessage('');
		$billingsTransaction->setUpdateType('api');
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
		//TRANSACTION
		if(isset($billingsTransaction)) {
			$billingsTransaction->setSubId($db_subscription->getId());
			$billingsTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsTransaction);
		}
		//<-- DATABASE -->
		config::getLogger()->addInfo("wecashup dbsubscription creation for userid=".$user->getId().", wecashup_subscription_uuid=".$api_subscription->getSubUid()." done successfully, id=".$db_subscription->getId());
		return($db_subscription);
	}
	
	public function doExpireSubscription(BillingsSubscription $subscription, DateTime $expires_date, $is_a_request = true) {
		try {
			config::getLogger()->addInfo("wecashup subscription expiring...");
			if(
					$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				//
				if($subscription->getSubPeriodEndsDate() <= $expires_date) {
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
			config::getLogger()->addInfo("wecashup subscription expiring done successfully for wecashup_subscription_uuid=".$subscription->getSubUid());
			return($subscription);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while expiring a wecashup subscription for wecashup_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("wecashup subscription expiring failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring a wecashup subscription for wecashup_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("wecashup subscription expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
		config::getLogger()->addInfo("wecashup dbsubscriptions update for userid=".$user->getId()."...");
		//ONLY UPDATE
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		$provider = ProviderDAO::getProviderById($user->getProviderId());
		//
		if($provider == NULL) {
			$msg = "unknown provider id : ".$user->getProviderId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$wecashupClient = new WecashupClient();
		foreach ($db_subscriptions as $db_subscription) {
			try {
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
				$api_subscription = clone $db_subscription;
				//
				$wecashupTransactionRequest = new WecashupTransactionRequest();
				$wecashupTransactionRequest->setTransactionUid($subOpts->getOpt('transaction_uid'));
				$wecashupTransactionsResponse = $wecashupClient->getTransaction($wecashupTransactionRequest);
				$wecashupTransactionsResponseArray = $wecashupTransactionsResponse->getWecashupTransactionsResponseArray();
				if(count($wecashupTransactionsResponseArray) != 1) {
					//Exception
					$msg = "transaction with transactionUid=".$subOpts->getOpt('transaction_uid')." was not found";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$wecashupTransactionResponse = $wecashupTransactionsResponseArray[0];
				if($wecashupTransactionResponse->getTransactionStatus() != 'success') {
					$msg = "The transaction did not succeed, responseStatus=".$wecashupTransactionResponse->getTransactionStatus();
					config::getLogger()->addError("wecashup subscription creation failed : ".$msg);
					throw new BillingsException(new ExceptionType(ExceptionType::provider), $msg);
				}
				$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, 'api', 0);
			} catch(Exception $e) {
				$msg = "wecashup dbsubscription update failed for subscriptionBillingUuid=".$db_subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
				config::getLogger()->addError($msg);
			}	
		}
		config::getLogger()->addInfo("wecashup dbsubscriptions update for userid=".$user->getId()." done successfully");
	}

	public function updateDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscription $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("wecashup dbsubscription update for userid=".$user->getId().", wecashup_subscription_uuid=".$api_subscription->getSubUid().", id=".$db_subscription->getId()."...");
		//UPDATE
		$db_subscription_before_update = clone $db_subscription;
		//
		//$db_subscription->setProviderId($provider->getId());//STATIC
		//$db_subscription->setUserId($user->getId());//STATIC
		$db_subscription->setPlanId($plan->getId());
		$db_subscription = BillingsSubscriptionDAO::updatePlanId($db_subscription);
		//$db_subscription->setSubUid($subscription_uuid);//STATIC
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
		$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
		//
		$db_subscription->setSubActivatedDate($api_subscription->getSubActivatedDate());
		$db_subscription = BillingsSubscriptionDAO::updateSubActivatedDate($db_subscription);
		//
		$db_subscription->setSubCanceledDate($api_subscription->getSubCanceledDate());
		$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
		//
		$db_subscription->setSubExpiresDate($api_subscription->getSubExpiresDate());
		$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
		//
		$start_date = $api_subscription->getSubPeriodStartedDate();
		$db_subscription->setSubPeriodStartedDate($start_date);
		
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
		$api_subscription->setSubPeriodEndsDate($end_date);
		//
		$db_subscription->setSubPeriodStartedDate($api_subscription->getSubPeriodStartedDate());
		$db_subscription = BillingsSubscriptionDAO::updateSubStartedDate($db_subscription);
		//
		$db_subscription->setSubPeriodEndsDate($api_subscription->getSubPeriodEndsDate());
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
		config::getLogger()->addInfo("wecashup dbsubscription update for userid=".$user->getId().", wecashup_subscription_uuid=".$api_subscription->getSubUid().", id=".$db_subscription->getId()." done successfully");
		return($db_subscription);	
	}
	
	protected function doFillSubscription(BillingsSubscription $subscription = NULL) {
		if($subscription == NULL) {
			return;
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
				config::getLogger()->addWarning("wecashup dbsubscription unknown subStatus=".$subscription->getSubStatus().", wecashup_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;
		}
		//done
		$subscription->setIsActive($is_active);
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
		$api_subscription = clone $db_subscription;
		//
		$wecashupClient = new WecashupClient();
		$wecashupTransactionRequest = new WecashupTransactionRequest();
		$wecashupTransactionRequest->setTransactionUid($subOpts->getOpt('transaction_uid'));
		$wecashupTransactionsResponse = $wecashupClient->getTransaction($wecashupTransactionRequest);
		$wecashupTransactionsResponseArray = $wecashupTransactionsResponse->getWecashupTransactionsResponseArray();
		if(count($wecashupTransactionsResponseArray) != 1) {
			//Exception
			$msg = "transaction with transactionUid=".$subOpts->getOpt('transaction_uid')." was not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$paymentTransaction = $wecashupTransactionsResponseArray[0];
		$billingsTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($provider->getId(), $paymentTransaction->getTransactionUid());
		if($billingsTransaction == NULL) {
			$msg = "no transaction with transaction_uid=".$paymentTransaction->getTransactionUid()." found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		switch ($paymentTransaction->getTransactionStatus()) {
			case 'TOVALIDATE' :
				$api_subscription->setSubStatus('future');
				$billingsTransaction->setTransactionStatus(BillingsTransactionStatus::waiting);
				$billingsTransaction->setUpdateType($update_type);
				if($paymentTransaction->getTransactionSenderCountryCodeIso2() != NULL) {
					$billingsTransaction->setCountry($paymentTransaction->getTransactionSenderCountryCodeIso2());
				}
				break;
			case 'PENDING' :
				$api_subscription->setSubStatus('future');
				$billingsTransaction->setTransactionStatus(BillingsTransactionStatus::waiting);
				$billingsTransaction->setUpdateType($update_type);
				if($paymentTransaction->getTransactionSenderCountryCodeIso2() != NULL) {
					$billingsTransaction->setCountry($paymentTransaction->getTransactionSenderCountryCodeIso2());
				}
				break;
			case 'PAID' :
				$api_subscription->setSubStatus('active');
				$api_subscription->setSubActivatedDate($now);
				$api_subscription->setSubPeriodStartedDate($now);
				$billingsTransaction->setTransactionStatus(BillingsTransactionStatus::success);
				$billingsTransaction->setUpdateType($update_type);
				if($paymentTransaction->getTransactionSenderCountryCodeIso2() != NULL) {
					$billingsTransaction->setCountry($paymentTransaction->getTransactionSenderCountryCodeIso2());
				}
				break;
			case 'FAILED' :
				$api_subscription->setSubStatus('expired');
				$api_subscription->setSubExpiresDate($now);
				$billingsTransaction->setTransactionStatus(BillingsTransactionStatus::failed);
				$billingsTransaction->setUpdateType($update_type);
				if($paymentTransaction->getTransactionSenderCountryCodeIso2() != NULL) {
					$billingsTransaction->setCountry($paymentTransaction->getTransactionSenderCountryCodeIso2());
				}
				break;
			default :
				//Exception
				$msg = "unknown transaction_status=".$paymentTransaction->getTransactionStatus();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}		
		$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, 'api', 0);
		$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
		return($db_subscription);
	}
	
}

?>