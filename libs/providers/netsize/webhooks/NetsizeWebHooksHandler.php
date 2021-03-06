<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../subscriptions/NetsizeSubscriptionsHandler.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../../global/requests/RenewSubscriptionRequest.php';
require_once __DIR__ . '/../../global/webhooks/ProviderWebHooksHandler.php';

class NetsizeWebHooksHandler extends ProviderWebHooksHandler {
	
	public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing netsize webHook with id=".$billingsWebHook->getId()."...");
			$xml = simplexml_load_string($billingsWebHook->getPostData());
			if($xml === false) {
				config::getLogger()->addError("XML cannot be loaded, post=".(string) $billingsWebHook->getPostData());
				throw new Exception("XML cannot be loaded, post=".(string) $billingsWebHook->getPostData());
			}
			$exception = NULL;
			foreach($xml->children() as $notificationNode) {
				try {
					$this->doProcessNotification($notificationNode, $update_type, $billingsWebHook->getId());
				} catch(Exception $e) {
					$msg = "an unknown exception occurred while processing netsize notifications with webHook id=".$billingsWebHook->getId().", message=".$e->getMessage();
					config::getLogger()->addError("processing netsize notifications with webHook id=".$billingsWebHook->getId()." failed : ". $msg);
					if($exception == NULL) {
						$exception = $e;//only throw 1st one, later, so continue anyway. For others => see logs
					}
				}
			}
			if(isset($exception)) {
				throw $exception;
			}
			config::getLogger()->addInfo("processing netsize webHook with id=".$billingsWebHook->getId()." done successfully");
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing netsize webHook with id=".$billingsWebHook->getId().", message=".$e->getMessage();
			config::getLogger()->addError("processing netsize webHook with id=".$billingsWebHook->getId()." failed : ". $msg);
			//HACK : no more errors returned to netsize
			//throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	private function doProcessNotification(SimpleXMLElement $notificationNode, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing netsize hook notification...');
		$notification_name = $notificationNode->getName();
		switch($notification_name) {
			case "transaction-changed" :
				$this->doProcessTransactionChanged($notificationNode, $update_type, $updateId);
				break;
			case "subscription-renewed" :
				$this->doProcessSubscriptionRenewed($notificationNode, $update_type, $updateId);
				break;
			default :
				config::getLogger()->addWarning('notification_name : '. $notification_name. ' is not yet implemented');
				break;
		}
		config::getLogger()->addInfo('Processing netsize hook notification done successfully');
	}
	
	private function doProcessTransactionChanged(SimpleXMLElement $notificationNode, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing netsize hook subscription, notification_name='.$notificationNode->getName().'...');
		//check Attribute : subscription-transaction-id. If NOT HERE : IGNORE
		$subscription_provider_uuid = $notificationNode["subscription-transaction-id"];
		if($subscription_provider_uuid == NULL) {
			//ignore notification
			config::getLogger()->addWarning('notification_name : '. $notificationNode->getName(). ', no subscription-transaction-id attribute found, notification is ignored');
		} else {
			
			$netsizeClient = new NetsizeClient($this->provider->getApiSecret(), $this->provider->getServiceId());
			
			$getStatusRequest = new GetStatusRequest();
			$getStatusRequest->setTransactionId($subscription_provider_uuid);
			
			$getStatusResponse = $netsizeClient->getStatus($getStatusRequest);
			$api_subscription = $getStatusResponse; 
			
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($this->provider->getId(), $subscription_provider_uuid);
			if($db_subscription == NULL) {
				$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$user = UserDAO::getUserById($db_subscription->getUserId());
			if($user == NULL) {
				$msg = "unknown user with id : ".$db_subscription->getUserId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			$plan = PlanDAO::getPlanById($db_subscription->getPlanId());
			if($plan == NULL) {
				$msg = "unknown provider plan with id : ".$db_subscription->getPlanId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
			$internalPlan = InternalPlanDAO::getInternalPlanById($plan->getInternalPlanId());
			if($internalPlan == NULL) {
				$msg = "plan with uuid=".$plan->getPlanUuid()." for provider ".$this->provider->getName()." is not linked to an internal plan";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
			$netsizeSubscriptionsHandler = new NetsizeSubscriptionsHandler($this->provider);
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				$db_subscription = $netsizeSubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, $userOpts, $this->provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, $update_type, $updateId);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
		}
		config::getLogger()->addInfo('Processing netsize hook subscription, notification_name='.$notificationNode->getName().' done successfully');
	}
	
	private function doProcessSubscriptionRenewed(SimpleXMLElement $notificationNode, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing netsize hook subscription, notification_name='.$notificationNode->getName().'...');
		//check Attribute : subscription-transaction-id. If NOT HERE : FAIL
		$subscription_provider_uuid = $notificationNode["subscription-transaction-id"];
		if($subscription_provider_uuid == NULL) {
			//exception
			$msg = 'notification_name : '. $notificationNode->getName(). ', no subscription-transaction-id attribute found';
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$expirationDateStr = $notificationNode["expiration-date"];
		if($expirationDateStr == NULL) {
			//exception
			$msg = 'notification_name : '. $notificationNode->getName(). ', no expiration-date attribute found';
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);			
		}
		//PHP cannot parse such dates (ex: '2016-05-16T08:29:07.9497653Z') - 7 digits for microseconds so here is a quick workaround -
		$expirationDateStr = substr($expirationDateStr, 0, strrpos($expirationDateStr, '.'));
		$expirationDate = DateTime::createFromFormat('Y-m-d\TH:i:s', $expirationDateStr, new DateTimeZone(config::$timezone));
		if($expirationDate === false) {
			$msg = "expiration-date date : ".$expirationDateStr." cannot be parsed";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($this->provider->getId(), $subscription_provider_uuid);
		if($db_subscription == NULL) {
			$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$subscriptionsHandler = new SubscriptionsHandler();
		//NC : For the moment, Netsize renewing does not support to force $end_date, so we are not using $expirationDate.
		$renewSubscriptionRequest = new RenewSubscriptionRequest();
		$renewSubscriptionRequest->setPlatform($this->platform);
		$renewSubscriptionRequest->setSubscriptionBillingUuid($db_subscription->getSubscriptionBillingUuid());
		$renewSubscriptionRequest->setStartDate(NULL);
		$renewSubscriptionRequest->setEndDate(NULL);//NC : should be $expirationDate, but date given by Netsize is not good
		$renewSubscriptionRequest->setOrigin('hook');
		$subscriptionsHandler->doRenewSubscription($renewSubscriptionRequest);
		config::getLogger()->addInfo('Processing netsize hook subscription, notification_name='.$notificationNode->getName().' done successfully');
	}
	
}

?>