<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../client/GoogleClient.php';
require_once __DIR__ . '/../../global/subscriptions/ProviderSubscriptionsHandler.php';
require_once __DIR__ . '/../../global/requests/ExpireSubscriptionRequest.php';
		
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
				checkSubOptsArray($subOpts->getOpts(), $this->provider->getName(), 'get');
				//** in google : user subscription is pre-created **/
				//
				$googleClient = new GoogleClient();
				$getSubscriptionRequest = new GetSubscriptionRequest();
				$getSubscriptionRequest->setSubscriptionId($subscription_provider_uuid);
				$getSubscriptionRequest->setToken($token);
				$result = $googleClient->getSubscription($getSubscriptionRequest);
				config::getLogger()->addError($this->provider->getName()." subscription creation...result=".var_export($result, true));
				$sub_uuid = $subscription_provider_uuid;
			} else {
				$msg = "unsupported feature for provider named ".$this->provider->getName().", subscriptionProviderUuid has to be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
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
		$api_subscription->setSubStatus('active');
		$api_subscription->setSubActivatedDate(new DateTime());
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $billingInfo, $subscription_billing_uuid, $api_subscription, $update_type, $updateId));
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
			BillingsSubscription $api_subscription, 
			$update_type, 
			$updateId) {
		config::getLogger()->addInfo("wecashup dbsubscription creation for userid=".$user->getId().", wecashup_subscription_uuid=".$api_subscription->getSubUid()."...");
		//
		if($subOpts == NULL) {
			//Exception
			$msg = "field 'subOpts' is missing";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
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
		$wecashupTransactionResponse = $wecashupTransactionsResponseArray[0];
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
		$country = NULL;
		if($wecashupTransactionResponse->getTransactionSenderCountryCodeIso2() != NULL) {
			$country = $wecashupTransactionResponse->getTransactionSenderCountryCodeIso2();
		} else {
			$country = isset($billingInfo) ? $billingInfo->getCountryCode() : NULL;
		}
		$billingsTransaction = new BillingsTransaction();
		$billingsTransaction->setProviderId($user->getProviderId());
		$billingsTransaction->setUserId($user->getId());
		$billingsTransaction->setCouponId(NULL);
		$billingsTransaction->setInvoiceId(NULL);
		$billingsTransaction->setTransactionBillingUuid(guid());
		$billingsTransaction->setTransactionProviderUuid($subOpts->getOpt('transaction_uid'));
		$billingsTransaction->setTransactionCreationDate($wecashupTransactionResponse->getTransactionDate());
		$billingsTransaction->setAmountInCents($internalPlan->getAmountInCents());
		$billingsTransaction->setCurrency($internalPlan->getCurrency());
		$billingsTransaction->setCountry($country);
		$billingsTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::waiting));
		$billingsTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::purchase));
		$billingsTransaction->setInvoiceProviderUuid(NULL);
		$billingsTransaction->setMessage('');
		$billingsTransaction->setUpdateType('api');
		//
		$billingsTransactionOpts = new BillingsTransactionOpts();
		$billingsTransactionOpts->setOpt('transaction_token', $subOpts->getOpt('transaction_token'));
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
		//TRANSACTION_OPTS
		if(isset($billingsTransactionOpts)) {
			$billingsTransactionOpts->setTransactionId($billingsTransaction->getId());
			$billingsTransactionOpts = BillingsTransactionOptsDAO::addBillingsTransactionOpts($billingsTransactionOpts);
		}
		//<-- DATABASE -->
		config::getLogger()->addInfo("wecashup dbsubscription creation for userid=".$user->getId().", wecashup_subscription_uuid=".$api_subscription->getSubUid()." done successfully, id=".$db_subscription->getId());
		return($this->doFillSubscription($db_subscription));
	}
	
}

?>