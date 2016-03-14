<?php

require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';

class BillingsGocardlessWorkers extends BillingsWorkers {
	
	public function __construct() {
		parent::__construct();
	}
	
	public function doRefreshSubscriptions() {
		$processingLog  = NULL;
		try {
			$provider_name = "gocardless";
			
			$provider = ProviderDAO::getProviderByName($provider_name);
		
			if($provider == NULL) {
				$msg = "unknown provider named : ".$provider_name;
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($provider->getId(), 'subs_refresh', $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("refreshing gocardless subscriptions bypassed - already done today -");
				exit;
			}
				
			ScriptsConfig::getLogger()->addInfo("refreshing gocardless subscriptions...");
				
			$processingLog = ProcessingLogDAO::addProcessingLog($provider->getId(), 'subs_refresh');
			//
			$offset = 0;
			$limit = 100;
			//will select all day strictly before today
			$sub_period_ends_date = clone $this->today;
			$sub_period_ends_date->setTime(0, 0, 0);
			//
			$status_array = array('active');
			//
			while(count($endingBillingsSubscriptions = BillingsSubscriptionDAO::getEndingBillingsSubscriptions($limit, $offset, $provider->getId(), $sub_period_ends_date, $status_array)) > 0) {
				ScriptsConfig::getLogger()->addInfo("processing...current offset=".$offset);
				$offset = $offset + $limit;
				//
				foreach($endingBillingsSubscriptions as $endingBillingsSubscription) {
					try {
						$this->doRefreshSubscription($endingBillingsSubscription);
					} catch(Exception $e) {
						$msg = "an error occurred while calling doRefreshSubscription for subscription with billings_subscription_uuid=".$endingBillingsSubscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
						ScriptsConfig::getLogger()->addError($msg);
					}
				}
			}
			//DONE
			$processingLog->setProcessingStatus('done');
			ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			ScriptsConfig::getLogger()->addInfo("refreshing gocardless subscriptions done successfully");
			$processingLog = NULL;
		} catch(Exception $e) {
			$msg = "an error occurred while refreshing gocardless subscriptions, message=".$e->getMessage();
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
			ScriptsConfig::getLogger()->addInfo("refreshing gocardless subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()."...");
			try {
				pg_query("BEGIN");
				$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "refresh_renew");
				$subscriptionsHandler = new SubscriptionsHandler();
				$subscriptionsHandler->doRenewSubscriptionByUuid($subscription->getSubscriptionBillingUuid(), NULL);
				$billingsSubscriptionActionLog->setProcessingStatus('done');
				BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
				//COMMIT
				pg_query("COMMIT");
			} catch (Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			ScriptsConfig::getLogger()->addInfo("refreshing gocardless subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()." done successfully");
			$billingsSubscriptionActionLog = NULL;
		} catch(Exception $e) {
			$msg = "an error occurred while refreshing gocardless subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($billingsSubscriptionActionLog)) {
				$billingsSubscriptionActionLog->setProcessingStatus("error");
				$billingsSubscriptionActionLog->setMessage($msg);
			}
			throw $e;
		} finally {
			if(isset($billingsSubscriptionActionLog)) {
				BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
			}
		}
	}
	
	
}

?>