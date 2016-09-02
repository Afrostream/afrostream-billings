<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../BillingsWorkers.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/subscriptions/SubscriptionsHandler.php';

class BillingExpireSubscriptionsWorkers extends BillingsWorkers {
	
	public function __construct() {
		parent::__construct();
	}
	
	public function doExpireCanceledSubscriptions() {
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay(NULL, 'subs_expire_canceled', $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("expiring canceled subscriptions bypassed - already done today -");
				exit;
			}
		
			ScriptsConfig::getLogger()->addInfo("expiring canceled subscriptions...");
		
			$processingLog = ProcessingLogDAO::addProcessingLog(NULL, 'subs_expire_canceled');
			//
			$limit = 100;
			//will select all day strictly before today
			$sub_period_ends_date = clone $this->today;
			$sub_period_ends_date->setTime(0, 0, 0);
			//
			$providerIdsToIgnore = array();
			$providerNamesToIgnore = ['recurly', 'stripe'];
			foreach ($providerNamesToIgnore as $providerNameToIgnore) {
				$provider = ProviderDAO::getProviderByName($providerNameToIgnore);
				if($provider == NULL) {
					$msg = "unknown provider named : ".$providerNameToIgnore;
					ScriptsConfig::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$providerIdsToIgnore[] = $provider->getId();
			}
			//
			$idx = 0;
			$lastId = NULL;
			$totalCounter = NULL;
			do {
				$canceledBillingsSubscriptions = BillingsSubscriptionDAO::getEndingBillingsSubscriptions($limit, 0, NULL, $sub_period_ends_date, array('canceled'), array('auto'), $providerIdsToIgnore, $lastId);
				if(is_null($totalCounter)) {$totalCounter = $canceledBillingsSubscriptions['total_counter'];}
				$idx+= count($canceledBillingsSubscriptions['subscriptions']);
				$lastId = $canceledBillingsSubscriptions['lastId'];
				//
				ScriptsConfig::getLogger()->addInfo("processing...total_counter=".$totalCounter.", idx=".$idx);
				foreach($canceledBillingsSubscriptions['subscriptions'] as $canceledBillingsSubscription) {
					try {
						$this->doExpireSubscription($canceledBillingsSubscription);
					} catch(Exception $e) {
						$msg = "an error occurred while calling doExpireSubscription for subscription with billings_subscription_uuid=".$canceledBillingsSubscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
						ScriptsConfig::getLogger()->addError($msg);
					}
				}
			} while ($idx < $totalCounter && count($canceledBillingsSubscriptions['subscriptions']) > 0);
			//DONE
			$processingLog->setProcessingStatus('done');
			ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			ScriptsConfig::getLogger()->addInfo("expiring canceled subscriptions done successfully");
			$processingLog = NULL;
		} catch(Exception $e) {
			$msg = "an error occurred while expiring canceled subscriptions, message=".$e->getMessage();
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
	
	public function doExpireEndedSubscriptions() {
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay(NULL, 'subs_expire_ended', $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("expiring ended subscriptions bypassed - already done today -");
				exit;
			}
		
			ScriptsConfig::getLogger()->addInfo("expiring ended subscriptions...");
		
			$processingLog = ProcessingLogDAO::addProcessingLog(NULL, 'subs_expire_ended');
			//
			$limit = 100;
			//will select all day strictly before today
			$sub_period_ends_date = clone $this->today;
			$sub_period_ends_date->setTime(0, 0, 0);
			//
			$providerIdsToIgnore = array();
			$providerNamesToIgnore = ['recurly', 'braintree'];
			foreach ($providerNamesToIgnore as $providerNameToIgnore) {
				$provider = ProviderDAO::getProviderByName($providerNameToIgnore);
				if($provider == NULL) {
					$msg = "unknown provider named : ".$providerNameToIgnore;
					ScriptsConfig::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$providerIdsToIgnore[] = $provider->getId();
			}
			//
			$idx = 0;
			$lastId = NULL;
			$totalCounter = NULL;
			do {
				$endedBillingsSubscriptions = BillingsSubscriptionDAO::getEndingBillingsSubscriptions($limit, 0, NULL, $sub_period_ends_date, array('active'), array('once'), $providerIdsToIgnore, $lastId);
				if(is_null($totalCounter)) {$totalCounter = $endedBillingsSubscriptions['total_counter'];}
				$idx+= count($endedBillingsSubscriptions['subscriptions']);
				$lastId = $endedBillingsSubscriptions['lastId'];
				//
				ScriptsConfig::getLogger()->addInfo("processing...total_counter=".$totalCounter.", idx=".$idx);
				foreach($endedBillingsSubscriptions['subscriptions'] as $endedBillingsSubscription) {
					try {
						$this->doExpireSubscription($endedBillingsSubscription);
					} catch(Exception $e) {
						$msg = "an error occurred while calling doExpireSubscription for subscription with billings_subscription_uuid=".$endedBillingsSubscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
						ScriptsConfig::getLogger()->addError($msg);
					}
				}
			} while($idx < $totalCounter && count($endedBillingsSubscriptions['subscriptions']) > 0);
			//DONE
			$processingLog->setProcessingStatus('done');
			ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			ScriptsConfig::getLogger()->addInfo("expiring ended subscriptions done successfully");
			$processingLog = NULL;
		} catch(Exception $e) {
			$msg = "an error occurred while expiring ended subscriptions, message=".$e->getMessage();
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
	
	private function doExpireSubscription(BillingsSubscription $subscription) {
		$billingsSubscriptionActionLog = NULL;
		try {
			//
			ScriptsConfig::getLogger()->addInfo("expiring subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()."...");
			try {
				pg_query("BEGIN");
				$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "expire");
				$subscriptionsHandler = new SubscriptionsHandler();
				$subscriptionsHandler->doExpireSubscriptionByUuid($subscription->getSubscriptionBillingUuid(), $subscription->getSubPeriodEndsDate(), false);
				$billingsSubscriptionActionLog->setProcessingStatus('done');
				BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
				//COMMIT
				pg_query("COMMIT");
			} catch (Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			ScriptsConfig::getLogger()->addInfo("expiring subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()." done successfully");
			$billingsSubscriptionActionLog = NULL;
		} catch(Exception $e) {
			$msg = "an error occurred while expiring subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
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