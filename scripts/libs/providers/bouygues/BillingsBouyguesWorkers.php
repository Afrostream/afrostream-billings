<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../../../../libs/providers/bouygues/client/BouyguesTVClient.php';
require_once __DIR__ . '/../../../../libs/providers/global/requests/ExpireSubscriptionRequest.php';

class BillingsBouyguesWorkers extends BillingsWorkers {
	
	private $provider = NULL;
	private $processingType = 'subs_refresh';
	
	public function __construct() {
		parent::__construct();
		$this->provider = ProviderDAO::getProviderByName('bouygues');
	}
	
	public function doRefreshSubscriptions() {
		$starttime = microtime(true);
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($this->provider->getId(), $this->processingType, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("refreshing bouygues subscriptions bypassed - already done today -");
				return;
			}
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.hit');
				
			ScriptsConfig::getLogger()->addInfo("refreshing bouygues subscriptions...");
				
			$processingLog = ProcessingLogDAO::addProcessingLog($this->provider->getId(), $this->processingType);
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
			ScriptsConfig::getLogger()->addInfo("refreshing bouygues subscriptions done successfully");
			$processingLog = NULL;
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.success');
		} catch(Exception $e) {
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.error');
			$msg = "an error occurred while refreshing bouygues subscriptions, message=".$e->getMessage();
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
			ScriptsConfig::getLogger()->addInfo("refreshing bouygues subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()."...");
			$user = UserDAO::getUserById($subscription->getUserId());
			if($user == NULL) {
				$msg = "unknown user with id : ".$subscription->getUserId();
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerPlan = PlanDAO::getPlanById($subscription->getPlanId());
			if($providerPlan == NULL) {
				$msg = "unknown plan with id : ".$subscription->getPlanId();
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$bouyguesTVClient = new BouyguesTVClient($user->getUserProviderUuid());
			$bouyguesSubscriptionResponse = $bouyguesTVClient->getSubscription($providerPlan->getPlanUuid());
			$bouyguesSubscription = $bouyguesSubscriptionResponse->getBouyguesSubscription();
			if($bouyguesSubscription->getResultMessage() == 'SubscribedNotCoupled') {
				$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "refresh_renew");
				try {
					pg_query("BEGIN");
					$subscriptionsHandler = new SubscriptionsHandler();
					$subscriptionsHandler->doRenewSubscriptionByUuid($subscription->getSubscriptionBillingUuid(), NULL, NULL);
					$billingsSubscriptionActionLog->setProcessingStatus('done');
					$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
					//COMMIT
					pg_query("COMMIT");
				} catch (Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			} else if($bouyguesSubscription->getResultMessage() == 'NotSubscribedNotCoupled') {
				$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "refresh_expire");
				try {
					pg_query("BEGIN");
					$subscriptionsHandler = new SubscriptionsHandler();
					$expireSubscriptionRequest = new ExpireSubscriptionRequest();
					$expireSubscriptionRequest->setOrigin('script');
					$expireSubscriptionRequest->setSubscriptionBillingUuid($subscription->getSubscriptionBillingUuid());
					$expireSubscriptionRequest->setExpiresDate(new DateTime());
					$subscriptionsHandler->doExpireSubscription($expireSubscriptionRequest);
					$billingsSubscriptionActionLog->setProcessingStatus('done');
					$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
					//COMMIT
					pg_query("COMMIT");
				} catch (Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			} else {
				$msg = "BouyguesSubscription resultMessage not in (SubscribedNotCoupled, NotSubscribedNotCoupled), resultMessage=".$bouyguesSubscription->getResultMessage();
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::BOUYGUES_SUBSCRIPTION_BAD_STATUS);
			}
			ScriptsConfig::getLogger()->addInfo("refreshing bouygues subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()." done successfully");
			$billingsSubscriptionActionLog = NULL;
		} catch(BillingsException $e) {
			$msg = "an error occurred while refreshing bouygues subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($billingsSubscriptionActionLog)) {
				$billingsSubscriptionActionLog->setProcessingStatus("error");
				$billingsSubscriptionActionLog->setProcessingStatusCode($e->getCode());
				$billingsSubscriptionActionLog->setMessage($msg);
			}
			throw $e;
		} catch(Exception $e) {
			$msg = "an error occurred while refreshing bouygues subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
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