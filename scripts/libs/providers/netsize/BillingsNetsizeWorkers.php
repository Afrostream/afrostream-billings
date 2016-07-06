<?php

require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';

class BillingsNetsizeWorkers extends BillingsWorkers {
	
	public function __construct() {
		parent::__construct();
	}
	
	public function doRefreshSubscriptions() {
		$processingLog  = NULL;
		try {
			$provider_name = "netsize";
			
			$provider = ProviderDAO::getProviderByName($provider_name);
		
			if($provider == NULL) {
				$msg = "unknown provider named : ".$provider_name;
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($provider->getId(), 'subs_refresh', $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("refreshing netsize subscriptions bypassed - already done today -");
				exit;
			}
				
			ScriptsConfig::getLogger()->addInfo("refreshing netsize subscriptions...");
				
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
			ScriptsConfig::getLogger()->addInfo("refreshing netsize subscriptions done successfully");
			$processingLog = NULL;
		} catch(Exception $e) {
			$msg = "an error occurred while refreshing netsize subscriptions, message=".$e->getMessage();
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
			ScriptsConfig::getLogger()->addInfo("refreshing netsize subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()."...");
			try {
				$netsizeClient = new NetsizeClient();
				
				$getStatusRequest = new GetStatusRequest();
				$getStatusRequest->setTransactionId($subscription->getSubUid());
				
				$getStatusResponse = $netsizeClient->getStatus($getStatusRequest);
				//420 - Activated
				//421 - Activated (Auto Billed)
				//422 - Activated (Termination in Progress)
				//432 - Cancelled
				$array_sub_is_open = [420, 421, 422, 432];
				//430 - Expired
				//431 - Suspended
				//433 - Failed
				$array_sub_is_expired = [430, 431, 433];
				if(array_key_exists($getStatusResponse->getTransactionStatusCode(), $array_sub_is_open))
				{
					pg_query("BEGIN");
					$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "refresh_renew");
					$subscriptionsHandler = new SubscriptionsHandler();
					$subscriptionsHandler->doRenewSubscriptionByUuid($subscription->getSubscriptionBillingUuid(), NULL, NULL);
					$billingsSubscriptionActionLog->setProcessingStatus('done');
					BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
					//COMMIT
					pg_query("COMMIT");
				} else if(array_key_exists($getStatusResponse->getTransactionStatusCode(), $array_sub_is_expired)) {
					pg_query("BEGIN");
					$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "refresh_expire");
					$subscriptionsHandler = new SubscriptionsHandler();
					$subscriptionsHandler->doExpireSubscriptionByUuid($subscription->getSubscriptionBillingUuid(), new DateTime(), false);
					$billingsSubscriptionActionLog->setProcessingStatus('done');
					BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
					//COMMIT
					pg_query("COMMIT");
				} else {
					$msg = "transaction-status/@code ".$getStatusResponse->getTransactionStatusCode()." is unknown";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::NETSIZE_SUBSCRIPTION_BAD_STATUS);
				}
			} catch (BillingsException $e) {
				pg_query("ROLLBACK");
				throw $e;
			} catch (Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			ScriptsConfig::getLogger()->addInfo("refreshing netsize subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()." done successfully");
			$billingsSubscriptionActionLog = NULL;
		} catch(BillingsException $e) {
			$msg = "an error occurred while refreshing netsize subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($billingsSubscriptionActionLog)) {
				$billingsSubscriptionActionLog->setProcessingStatus("error");
				$billingsSubscriptionActionLog->setProcessingStatusCode($e->getCode());
				$billingsSubscriptionActionLog->setMessage($msg);
			}
			throw $e;
		} catch(Exception $e) {
			$msg = "an error occurred while refreshing netsize subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
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