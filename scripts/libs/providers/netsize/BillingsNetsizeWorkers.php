<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../../../../libs/providers/global/requests/ExpireSubscriptionRequest.php';
require_once __DIR__ . '/../../../../libs/providers/global/requests/CancelSubscriptionRequest.php';
require_once __DIR__ . '/../../../../libs/providers/global/requests/RenewSubscriptionRequest.php';

class BillingsNetsizeWorkers extends BillingsWorkers {
	
	private $provider = NULL;
	private $processingType = 'subs_refresh';
	
	public function __construct() {
		parent::__construct();
		$this->provider = ProviderDAO::getProviderByName('netsize');
	}
	
	public function doRefreshSubscriptions() {
		$starttime = microtime(true);
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($this->provider->getId(), $this->processingType, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("refreshing netsize subscriptions bypassed - already done today -");
				return;
			}
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.hit');
				
			ScriptsConfig::getLogger()->addInfo("refreshing netsize subscriptions...");
				
			$processingLog = ProcessingLogDAO::addProcessingLog($this->provider->getId(), $this->processingType);
			//
			$limit = 100;
			//will select all day strictly before today
			//$sub_period_ends_date = clone $this->today;
			//$sub_period_ends_date->setTime(0, 0, 0);
			//
			$status_array = array('active', 'future', 'canceled');
			//
			$idx = 0;
			$lastId = NULL;
			$totalCounter = NULL;
			do {
				$endingBillingsSubscriptions = BillingsSubscriptionDAO::getEndingBillingsSubscriptions($limit, 0, $this->provider->getId(), NULL, $status_array, NULL, NULL, $lastId);
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
			ScriptsConfig::getLogger()->addInfo("refreshing netsize subscriptions done successfully");
			$processingLog = NULL;
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.success');
		} catch(Exception $e) {
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.error');
			$msg = "an error occurred while refreshing netsize subscriptions, message=".$e->getMessage();
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
			ScriptsConfig::getLogger()->addInfo("refreshing netsize subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()."...");
			$netsizeClient = new NetsizeClient();
			
			$getStatusRequest = new GetStatusRequest();
			$getStatusRequest->setTransactionId($subscription->getSubUid());
			
			$getStatusResponse = $netsizeClient->getStatus($getStatusRequest);
			//420 - Activated
			//421 - Activated (Auto Billed)
			$array_sub_is_open = [420, 421];
			//422 - Activated (Termination in Progress)
			//432 - Cancelled
			$array_sub_is_canceled = [422, 432];
			//430 - Expired
			//431 - Suspended
			//433 - Failed
			$array_sub_is_expired = [430, 431, 433];
			if(in_array($getStatusResponse->getTransactionStatusCode(), $array_sub_is_open)) {
				$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "refresh_renew");
				try {
					pg_query("BEGIN");
					$subscriptionsHandler = new SubscriptionsHandler();
					$renewSubscriptionRequest = new RenewSubscriptionRequest();
					$renewSubscriptionRequest->setSubscriptionBillingUuid($subscription->getSubscriptionBillingUuid());
					$renewSubscriptionRequest->setStartDate(NULL);
					$renewSubscriptionRequest->setEndDate(NULL);
					$renewSubscriptionRequest->setOrigin('script');
					$subscriptionsHandler->doRenewSubscription($renewSubscriptionRequest);
					$billingsSubscriptionActionLog->setProcessingStatus('done');
					$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
					//COMMIT
					pg_query("COMMIT");
				} catch (Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			} else if(in_array($getStatusResponse->getTransactionStatusCode(), $array_sub_is_canceled)) {
				$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "refresh_cancel");
				try {
					pg_query("BEGIN");
					$subscriptionsHandler = new SubscriptionsHandler();
					$cancelSubscriptionRequest = new CancelSubscriptionRequest();
					$cancelSubscriptionRequest->setSubscriptionBillingUuid($subscription->getSubscriptionBillingUuid());
					$cancelSubscriptionRequest->setOrigin('script');
					$cancelSubscriptionRequest->setCancelDate(new DateTime());
					$subscriptionsHandler->doCancelSubscription($cancelSubscriptionRequest);
					$billingsSubscriptionActionLog->setProcessingStatus('done');
					$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
					//COMMIT
					pg_query("COMMIT");
				} catch (Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			} else if(in_array($getStatusResponse->getTransactionStatusCode(), $array_sub_is_expired)) {
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
				$msg = "transaction-status/@code ".$getStatusResponse->getTransactionStatusCode()." is unknown";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::NETSIZE_SUBSCRIPTION_BAD_STATUS);
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
				$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
			}
		}
	}
	
}

?>