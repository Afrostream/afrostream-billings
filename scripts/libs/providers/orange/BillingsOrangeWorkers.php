<?php

require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';

class BillingsOrangeWorkers extends BillingsWorkers {
	
	public function __construct() {
		parent::__construct();
	}
	
	public function doRefreshSubscriptions() {
		$processingLog  = NULL;
		try {
			$provider_name = "orange";
			
			$provider = ProviderDAO::getProviderByName($provider_name);
		
			if($provider == NULL) {
				$msg = "unknown provider named : ".$provider_name;
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($provider->getId(), 'subs_refresh', $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("refreshing orange subscriptions bypassed - already done today -");
				return;
			}
				
			ScriptsConfig::getLogger()->addInfo("refreshing orange subscriptions...");
				
			$processingLog = ProcessingLogDAO::addProcessingLog($provider->getId(), 'subs_refresh');
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
				$endingBillingsSubscriptions = BillingsSubscriptionDAO::getEndingBillingsSubscriptions($limit, $offset, $provider->getId(), $sub_period_ends_date, $status_array, $lastId);
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
			ScriptsConfig::getLogger()->addInfo("refreshing orange subscriptions done successfully");
			$processingLog = NULL;
		} catch(Exception $e) {
			$msg = "an error occurred while refreshing orange subscriptions, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($processingLog)) {
				$processingLog->setProcessingStatus('error');
				$processingLog->setMessage($msg);
			}
		} finally {
			if(isset($processingLog)) {
				ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			}
		}
	}
	
	private function doRefreshSubscription(BillingsSubscription $subscription) {
		$billingsSubscriptionActionLog = NULL;
		try {
			//
			ScriptsConfig::getLogger()->addInfo("refreshing orange subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()."...");
			$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "refresh_renew");
			try {
				pg_query("BEGIN");
				$subscriptionsHandler = new SubscriptionsHandler();
				$subscriptionsHandler->doRenewSubscriptionByUuid($subscription->getSubscriptionBillingUuid(), NULL, NULL);
				$billingsSubscriptionActionLog->setProcessingStatus('done');
				$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
				//COMMIT
				pg_query("COMMIT");
			} catch (BillingsException $e) {
				pg_query("ROLLBACK");
				throw $e;
			} catch (Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			ScriptsConfig::getLogger()->addInfo("refreshing orange subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()." done successfully");
			$billingsSubscriptionActionLog = NULL;
		} catch(BillingsException $e) {
			$msg = "an error occurred while refreshing orange subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($billingsSubscriptionActionLog)) {
				$billingsSubscriptionActionLog->setProcessingStatus("error");
				$billingsSubscriptionActionLog->setProcessingStatusCode($e->getCode());
				$billingsSubscriptionActionLog->setMessage($msg);
			}
			throw $e;
		} catch(Exception $e) {
			$msg = "an error occurred while refreshing orange subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
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