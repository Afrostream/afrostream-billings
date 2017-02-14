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
				$api_subscription = $googleClient->getSubscription($getSubscriptionRequest);
				config::getLogger()->addError($this->provider->getName()." subscription creation...result=".var_export($api_subscription, true));
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
		$googleClient = new GoogleClient();
		$getSubscriptionRequest = new GetSubscriptionRequest();
		$getSubscriptionRequest->setSubscriptionId($sub_uuid);
		$getSubscriptionRequest->setToken($subOpts->getOpts()['customerBankAccountToken']);
		$api_subscription = $googleClient->getSubscription($getSubscriptionRequest);
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
			Google_Service_AndroidPublisher_SubscriptionPurchase $api_subscription, 
			$update_type, 
			$updateId) {
		//TODO : FIXME
		config::getLogger()->addInfo($this->provider->getName()." dbsubscription creation for userid=".$user->getId().", ".$this->provider->getName()."_subscription_uuid=".$api_subscription->__get('subscriptionId')."...");
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
		//TODO : FIXME
		$db_subscription->setSubUid($api_subscription->__get('subscriptionId'));
		$db_subscription->setSubStatus('active');
		$start_date = new DateTime();
		$start_date->setTimestamp($api_subscription->getStartTimeMillis());
		$end_date = new DateTime();
		$end_date->setTimestamp($api_subscription->getExpiryTimeMillis());
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
		$start_date->setTimestamp($api_subscription->getStartTimeMillis());
		$end_date = new DateTime();
		$end_date->setTimestamp($api_subscription->getExpiryTimeMillis());
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
		
}

?>