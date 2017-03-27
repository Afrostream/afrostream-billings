<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../../../../libs/providers/global/requests/ExpireSubscriptionRequest.php';
require_once __DIR__ . '/../../../../libs/providers/google/client/GoogleClient.php';
require_once __DIR__ . '/../../../../libs/providers/google/subscriptions/GoogleSubscriptionsHandler.php';

class BillingsGoogleWorkers extends BillingsWorkers {
	
	private $provider = NULL;
	private $processingType = 'subs_refresh';
	
	public function __construct(Provider $provider) {
		parent::__construct();
		$this->provider = $provider;
	}
	
	public function doRefreshSubscriptions() {
		$starttime = microtime(true);
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($this->provider->getPlatformId(), $this->provider->getId(), $this->processingType, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("refreshing ".$this->provider->getName()." subscriptions bypassed - already done today -");
				return;
			}
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.hit');
				
			ScriptsConfig::getLogger()->addInfo("refreshing ".$this->provider->getName()." subscriptions...");
				
			$processingLog = ProcessingLogDAO::addProcessingLog($this->provider->getPlatformId(), $this->provider->getId(), $this->processingType);
			//
			$limit = 100;
			//will select all day strictly before today
			$sub_period_ends_date = clone $this->today;
			$sub_period_ends_date->setTime(0, 0, 0);
			//
			$status_array = array('active');
			//
			$idx = 0;
			$lastId = NULL;
			$totalCounter = NULL;
			do {
				$endingBillingsSubscriptions = BillingsSubscriptionDAO::getEndingBillingsSubscriptions($limit, 0, $this->provider->getId(), $sub_period_ends_date, $status_array, NULL, NULL, $lastId);
				if(is_null($totalCounter)) {$totalCounter = $endingBillingsSubscriptions['total_counter'];}
				$idx+= count($endingBillingsSubscriptions['subscriptions']);
				$lastId = $endingBillingsSubscriptions['lastId'];
				//
				ScriptsConfig::getLogger()->addInfo("processing...total_counter=".$totalCounter.", idx=".$idx);
				foreach($endingBillingsSubscriptions['subscriptions'] as $endingBillingsSubscription) {
					try {
						$this->doRefreshSubscription($endingBillingsSubscription);
					} catch(Exception $e) {
						$msg = "an error occurred while calling doRefreshSubscription for subscription with billings_subscription_uuid=".$endingBillingsSubscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
						ScriptsConfig::getLogger()->addError($msg);
					}
				}
			} while ($idx < $totalCounter && count($endingBillingsSubscriptions['subscriptions']) > 0);
			//DONE
			$processingLog->setProcessingStatus('done');
			ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			ScriptsConfig::getLogger()->addInfo("refreshing ".$this->provider->getName()." subscriptions done successfully");
			$processingLog = NULL;
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.success');
		} catch(Exception $e) {
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.error');
			$msg = "an error occurred while refreshing ".$this->provider->getName()." subscriptions, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($processingLog)) {
				$processingLog->setProcessingStatus('error');
				$processingLog->setMessage($msg);
			}
		} finally {
			$timingInMillis = round((microtime(true) - $starttime) * 1000);
			BillingStatsd::timing('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.timing', $timingInMillis);			
			if(isset($processingLog)) {
				ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			}
		}
	}
	
	private function doRefreshSubscription(BillingsSubscription $subscription) {
		$billingsSubscriptionActionLog = NULL;
		try {
			//
			ScriptsConfig::getLogger()->addInfo("refreshing ".$this->provider->getName()." subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()."...");
			$plan = PlanDAO::getPlanById($subscription->getPlanId());
			//check plan
			if($plan == NULL) {
				$msg = "unknown plan with id : ".$subscription->getPlanId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$subOpts = BillingsSubscriptionOptsDAO::getBillingsSubscriptionOptsBySubId($subscription->getId());
			$googleClient = new GoogleClient($this->provider->getConfigFile());
			$googleGetSubscriptionRequest = new GoogleGetSubscriptionRequest();
			$googleGetSubscriptionRequest->setPackageName($this->provider->getOpts()["packageName"]);
			$googleGetSubscriptionRequest->setSubscriptionId($plan->getPlanUuid());
			$googleGetSubscriptionRequest->setToken($subOpts->getOpts()['customerBankAccountToken']);
			$api_subscription = $googleClient->getSubscription($googleGetSubscriptionRequest);
			//
			$googleSubscriptionsHandler = new GoogleSubscriptionsHandler($this->provider);
			$user = UserDAO::getUserById($subscription->getUserId());
			//check user
			if($user == NULL) {
				$msg = "unknown user with id : ".$subscription->getUserId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
			$internalPlan = InternalPlanDAO::getInternalPlanById($plan->getInternalPlanId());
			//check internalPlan
			if($internalPlan == NULL) {
				$msg = "plan with uuid=".$plan->getPlanUuid()." is not linked to an internal plan";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
			$subscription = $googleSubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, 
					$userOpts, 
					$this->provider, 
					$internalPlan, 
					$internalPlanOpts, 
					$plan, 
					$planOpts, 
					$api_subscription, 
					$subscription, 
					'sync', 
					0);
			ScriptsConfig::getLogger()->addInfo("refreshing ".$this->provider->getName()." subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()." done successfully");
			$billingsSubscriptionActionLog = NULL;
		} catch(BillingsException $e) {
			$msg = "an error occurred while refreshing ".$this->provider->getName()." subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($billingsSubscriptionActionLog)) {
				$billingsSubscriptionActionLog->setProcessingStatus("error");
				$billingsSubscriptionActionLog->setProcessingStatusCode($e->getCode());
				$billingsSubscriptionActionLog->setMessage($msg);
			}
			throw $e;
		} catch(Exception $e) {
			$msg = "an error occurred while refreshing ".$this->provider->getName()." subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($billingsSubscriptionActionLog)) {
				$billingsSubscriptionActionLog->setProcessingStatus("error");
				$billingsSubscriptionActionLog->setMessage($msg);
			}
			throw $e;
		} finally {
			if(isset($billingsSubscriptionActionLog)) {
				$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
			}
		}
	}
	
}

?>