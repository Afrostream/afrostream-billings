<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../../../../libs/providers/global/requests/CancelSubscriptionRequest.php';
require_once __DIR__ . '/../../../../libs/providers/global/requests/RenewSubscriptionRequest.php';

ini_set("auto_detect_line_endings", true);

class BillingsBachatWorkers extends BillingsWorkers {
	
	private $provider = NULL;
	private $processingTypeSubsRequestRenew = 'subs_request_renew';
	private $processingTypeSubsResponseRenew = 'subs_response_renew';
	private $processingTypeSubsRequestCancel = 'subs_request_cancel';
	private $processingTypeSubsResponseCancel = 'subs_response_cancel';
	
	public function __construct(Provider $provider) {
		parent::__construct();
		$this->provider = $provider;
	}
	
	public function doRequestRenewSubscriptions($force = true) {
		$starttime = microtime(true);
		$processingLog  = NULL;
		$billingsSubscriptionActionLogs = array();
		$current_par_ren_file_path = NULL;
		$current_par_ren_file_res = NULL;
		$billingsSubscriptionsOkToProceed = array();
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($this->provider->getId(), $this->processingTypeSubsRequestRenew, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("requesting bachat subscriptions renewal bypassed - already done today -");
				return;
			}
			$processingLog = ProcessingLogDAO::addProcessingLog($this->provider->getId(), $this->processingTypeSubsRequestRenew);
			$now = (new DateTime())->setTimezone(new DateTimeZone(self::$timezone));
			$lastAttemptDate = clone $now;
			$lastAttemptDate->setTime(getEnv('BOUYGUES_STORE_LAST_TIME_HOUR'), getEnv('BOUYGUES_STORE_LAST_TIME_MINUTE'));
			if($lastAttemptDate > $now) {
				BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsRequestRenew.'.hit');
				ScriptsConfig::getLogger()->addInfo("requesting bachat subscriptions renewal...");
				
				if(($current_par_ren_file_path = tempnam('', 'tmp')) === false) {
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "PAR_REN file cannot be created");
				}
				if(($current_par_ren_file_res = fopen($current_par_ren_file_path, "w")) === false) {
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "PAR_REN file cannot be open (for write)");
				}
				ScriptsConfig::getLogger()->addInfo("PAR_REN file successfully created here : ".$current_par_ren_file_path);
				$limit = 100;
				//will select all day strictly before today
				$sub_period_ends_date = clone $this->today;
				$sub_period_ends_date->setTime(0, 0, 0);
				//
				$status_array = array('active');
				if($force) {
					$status_array[] = 'pending_active';
				}
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
						//
						$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($endingBillingsSubscription->getId(), "request_renew");
						$billingsSubscriptionActionLogs[] = $billingsSubscriptionActionLog;
						//
						try {
							$this->doRequestRenewSubscription($billingsSubscriptionActionLog, $endingBillingsSubscription, $current_par_ren_file_res);
							$billingsSubscriptionsOkToProceed[] = $endingBillingsSubscription;
						} catch(Exception $e) {
							$msg = "an error occurred while calling doRequestRenewSubscription for subscription with billings_subscription_uuid=".$endingBillingsSubscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
							ScriptsConfig::getLogger()->addError($msg);
						}
					}
				} while ($idx < $totalCounter && count($endingBillingsSubscriptions['subscriptions']) > 0);
				fclose($current_par_ren_file_res);
				$current_par_ren_file_res = NULL;
				if(($current_par_ren_file_res = fopen($current_par_ren_file_path, "r")) === false) {
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "PAR_REN file cannot be open (for read)");
				}
				//SEND FILE TO THE SYSTEM WEBDAV (PUT)
				ScriptsConfig::getLogger()->addInfo("PAR_REN uploading...");
				$url = $this->getBouyguesBillingSystemUrl()."/"."PAR_REN_".$this->today->format("Ymd").".csv";
				$curl_options = array(
						CURLOPT_URL => $url,
						CURLOPT_PUT => true,
						CURLOPT_INFILE => $current_par_ren_file_res,
						CURLOPT_INFILESIZE => filesize($current_par_ren_file_path),
						CURLOPT_HTTPHEADER => array(
								'Content-Type: text/csv'
						),
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_HEADER  => false
				);
				if(	null !== (getEnv('BOUYGUES_PROXY_HOST'))
					&&
					null !== (getEnv('BOUYGUES_PROXY_PORT'))
				) {
					$curl_options[CURLOPT_PROXY] = getEnv('BOUYGUES_PROXY_HOST');
					$curl_options[CURLOPT_PROXYPORT] = getEnv('BOUYGUES_PROXY_PORT');
				}
				if(	null !== (getEnv('BOUYGUES_PROXY_USER'))
					&&
					null !== (getEnv('BOUYGUES_PROXY_PWD'))
				) {
					$curl_options[CURLOPT_PROXYUSERPWD] = getEnv('BOUYGUES_PROXY_USER').":".getEnv('BOUYGUES_PROXY_PWD');
				}
				if(	null !== ($this->provider->getApiKey())
						&&
					null !== ($this->provider->getApiSecret())
				) {			
					$curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
					$curl_options[CURLOPT_USERPWD] = $this->provider->getApiKey().":".$this->provider->getApiSecret();
				}
				$curl_options[CURLOPT_VERBOSE] = true;
				$CURL = curl_init();
				curl_setopt_array($CURL, $curl_options);
				$content = curl_exec($CURL);
				$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
				curl_close($CURL);
				fclose($current_par_ren_file_res);
				$current_par_ren_file_res = NULL;
				unlink($current_par_ren_file_path);
				$current_par_ren_file_path = NULL;
				if($httpCode == 200 || $httpCode == 201 || $httpCode == 204) {
					ScriptsConfig::getLogger()->addInfo("PAR_REN uploading done successfully, the httpCode is : ".$httpCode);
				} else {
					$msg = "an error occurred while uploading the PAR_REN file, the httpCode is : ".$httpCode;
					ScriptsConfig::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				//DONE
				self::setBillingsSubscriptionsStatus($billingsSubscriptionsOkToProceed, 'pending_active');
				self::setRequestsAreDone($billingsSubscriptionActionLogs);
				$processingLog->setProcessingStatus('done');
				ScriptsConfig::getLogger()->addInfo("requesting bachat subscriptions renewal done successfully");
				BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsRequestRenew.'.success');
			} else {
				//NOTHING TO DO YET
				$processingLog->setProcessingStatus('postponed');
				ScriptsConfig::getLogger()->addInfo("requesting bachat subscriptions renewal postponed successfully");
			}
		} catch(Exception $e) {
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsRequestRenew.'.error');
			$msg = "an error occurred while requesting bachat subscriptions renewal, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			self::setRequestsAreFailed($billingsSubscriptionActionLogs, $e->getMessage());
			if(isset($processingLog)) {
				$processingLog->setProcessingStatus('error');
				$processingLog->setMessage($msg);
			}
		} finally {
			$timingInMillis = round((microtime(true) - $starttime) * 1000);
			BillingStatsd::timing('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsRequestRenew.'.timing', $timingInMillis);
			if(isset($current_par_ren_file_res)) {
				fclose($current_par_ren_file_res);
				$current_par_ren_file_res = NULL;
			}
			if(isset($current_par_ren_file_path)) {
				unlink($current_par_ren_file_path);
				$current_par_ren_file_path = NULL;
			}
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				self::doSaveBillingsSubscriptionsStatus($billingsSubscriptionsOkToProceed);
				self::doSaveBillingsSubscriptionActionLogs($billingsSubscriptionActionLogs);
				if(isset($processingLog)) {
					ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
				}
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
		}
	}
	
	private function doRequestRenewSubscription(BillingsSubscriptionActionLog $billingsSubscriptionActionLog, BillingsSubscription $subscription, $current_par_ren_file_res) {
		try {
			//
			ScriptsConfig::getLogger()->addInfo("preparing bachat subscription renewal for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()."...");
			$providerPlan = PlanDAO::getPlanById($subscription->getPlanId());
			if($providerPlan == NULL) {
				$msg = "unknown provider plan with id : ".$subscription->getPlanId();
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($providerPlan->getId()));
			if($internalPlan == NULL) {
				$msg = "plan with uuid=".$providerPlan->getPlanUuid()." for provider bachat is not linked to an internal plan";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$fields = array();
			$day = new DateTime();
			$day->setTimezone(new DateTimeZone(self::$timezone));
			$day_str = $day->format("dmY");
			$fields[] = $day_str;
			$time = $subscription->getSubPeriodEndsDate();
			$time->setTimezone(new DateTimeZone(self::$timezone));
			$time_str = $time->format("His");
			$fields[] = $time_str;
			$fields[] = $this->provider->getServiceId();//ServiceId
			$fields[] = $subscription->getSubscriptionBillingUuid();//SubscriptionServiceId
			$fields[] = $subscription->getSubUid();//SubscriptionId
			$fields[] = (string) number_format($internalPlan->getVatRate(), 2, '.', '');//VAT : "." for BACHAT not ","
			fputcsv($current_par_ren_file_res, $fields);
			ScriptsConfig::getLogger()->addInfo("preparing bachat subscription renewal for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()." done successfully");
		} catch(Exception $e) {
			$msg = "an error occurred while preparing bachat subscription renewal for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			$billingsSubscriptionActionLog->setProcessingStatus("error");
			$billingsSubscriptionActionLog->setMessage($msg);
			throw $e;
		}
	}
	
	private static function setBillingsSubscriptionsStatus(array $billingsSubscriptions, $status) {
		foreach($billingsSubscriptions as $billingsSubscription) {
			$billingsSubscription->setSubStatus($status);
		}
	}
	
	private static function doSaveBillingsSubscriptionsStatus(array $billingsSubscriptions) {
		foreach($billingsSubscriptions as $billingsSubscription) {
			BillingsSubscriptionDAO::updateSubStatus($billingsSubscription);
		}
	}
	
	private static function setRequestsAreDone(array $billingsSubscriptionActionLogs) {
		foreach($billingsSubscriptionActionLogs as $billingsSubscriptionActionLog) {
			if($billingsSubscriptionActionLog->getProcessingStatus() == 'running') {
				$billingsSubscriptionActionLog->setProcessingStatus('done');
			}
		}
	}
	
	private static function setRequestsAreFailed(array $billingsSubscriptionActionLogs, $msg) {
		foreach($billingsSubscriptionActionLogs as $billingsSubscriptionActionLog) {
			if($billingsSubscriptionActionLog->getProcessingStatus() == 'running') {
				$billingsSubscriptionActionLog->setProcessingStatus('error');
				$billingsSubscriptionActionLog->setMessage($msg);
			}
		}	
	}
	
	private static function doSaveBillingsSubscriptionActionLogs(array $billingsSubscriptionActionLogs) {
		foreach($billingsSubscriptionActionLogs as $billingsSubscriptionActionLog) {
			BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
		}
	}
	
	public function doRequestCancelSubscriptions($force = true) {
		$starttime = microtime(true);
		$processingLog  = NULL;
		$billingsSubscriptionActionLogs = array();
		$current_par_can_file_path = NULL;
		$current_par_can_file_res = NULL;
		$billingsSubscriptionsOkToProceed = array();
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($this->provider->getId(), $this->processingTypeSubsRequestCancel, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("requesting bachat subscriptions canceling bypassed - already done today -");
				return;
			}
			$processingLog = ProcessingLogDAO::addProcessingLog($this->provider->getId(), $this->processingTypeSubsRequestCancel);
			$now = (new DateTime())->setTimezone(new DateTimeZone(self::$timezone));
			$lastAttemptDate = clone $now;
			$lastAttemptDate->setTime(getEnv('BOUYGUES_STORE_LAST_TIME_HOUR'), getEnv('BOUYGUES_STORE_LAST_TIME_MINUTE'));
			if($lastAttemptDate > $now) {
				BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsRequestCancel.'.hit');
				ScriptsConfig::getLogger()->addInfo("requesting bachat subscriptions canceling...");
				if(($current_par_can_file_path = tempnam('', 'tmp')) === false) {
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "PAR_CAN file cannot be created");
				}
				if(($current_par_can_file_res = fopen($current_par_can_file_path, "w")) === false) {
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "PAR_CAN file cannot be open (for write)");
				}
				ScriptsConfig::getLogger()->addInfo("PAR_CAN file successfully created here : ".$current_par_can_file_path);
				$limit = 100;
				//
				$status_array = array('requesting_canceled');
				if($force) {
					$status_array[] = 'pending_canceled';
				}
				//
				$idx = 0;
				$lastId = NULL;
				$totalCounter = NULL;
				do {
					$requestingCanceledBillingsSubscriptions = BillingsSubscriptionDAO::getRequestingCanceledBillingsSubscriptions($limit, 0, $this->provider->getId(), $status_array, $lastId);
					if(is_null($totalCounter)) {$totalCounter = $requestingCanceledBillingsSubscriptions['total_counter'];}
					$idx+= count($requestingCanceledBillingsSubscriptions['subscriptions']);
					$lastId = $requestingCanceledBillingsSubscriptions['lastId'];
					//
					ScriptsConfig::getLogger()->addInfo("processing...total_counter=".$totalCounter.", idx=".$idx);
					foreach($requestingCanceledBillingsSubscriptions['subscriptions'] as $requestingCanceledBillingsSubscription) {
						//
						$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($requestingCanceledBillingsSubscription->getId(), "request_cancel");
						$billingsSubscriptionActionLogs[] = $billingsSubscriptionActionLog;
						//
						try {
							$this->doRequestCancelSubscription($billingsSubscriptionActionLog, $requestingCanceledBillingsSubscription, $current_par_can_file_res);
							$billingsSubscriptionsOkToProceed[] = $requestingCanceledBillingsSubscription;
						} catch(Exception $e) {
							$msg = "an error occurred while calling doRequestCancelSubscription for subscription with billings_subscription_uuid=".$requestingCanceledBillingsSubscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
							ScriptsConfig::getLogger()->addError($msg);
						}
					}
				} while($idx < $totalCounter && count($requestingCanceledBillingsSubscriptions['subscriptions']) > 0);
				fclose($current_par_can_file_res);
				$current_par_can_file_res = NULL;
				if(($current_par_can_file_res = fopen($current_par_can_file_path, "r")) === false) {
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "PAR_CAN file cannot be open (for read)");
				}
				//SEND FILE TO THE SYSTEM WEBDAV (PUT)
				ScriptsConfig::getLogger()->addInfo("PAR_CAN uploading...");
				$url = $this->getBouyguesBillingSystemUrl()."/"."PAR_CAN_".$this->today->format("Ymd").".csv";
				$curl_options = array(
						CURLOPT_URL => $url,
						CURLOPT_PUT => true,
						CURLOPT_INFILE => $current_par_can_file_res,
						CURLOPT_INFILESIZE => filesize($current_par_can_file_path),
						CURLOPT_HTTPHEADER => array(
								'Content-Type: text/csv'
						),
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_HEADER  => false
				);
				if(	null !== (getEnv('BOUYGUES_PROXY_HOST'))
					&&
					null !== (getEnv('BOUYGUES_PROXY_PORT'))
				) {
					$curl_options[CURLOPT_PROXY] = getEnv('BOUYGUES_PROXY_HOST');
					$curl_options[CURLOPT_PROXYPORT] = getEnv('BOUYGUES_PROXY_PORT');
				}
				if(	null !== (getEnv('BOUYGUES_PROXY_USER'))
					&&
					null !== (getEnv('BOUYGUES_PROXY_PWD'))
				) {
					$curl_options[CURLOPT_PROXYUSERPWD] = getEnv('BOUYGUES_PROXY_USER').":".getEnv('BOUYGUES_PROXY_PWD');
				}
				if(	null !== ($this->provider->getApiKey())
					&&
					null !== ($this->provider->getApiSecret())
				) {
					$curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
					$curl_options[CURLOPT_USERPWD] = $this->provider->getApiKey().":".$this->provider->getApiSecret();
				}
				$curl_options[CURLOPT_VERBOSE] = true;
				$CURL = curl_init();
				curl_setopt_array($CURL, $curl_options);
				$content = curl_exec($CURL);
				$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
				curl_close($CURL);
				fclose($current_par_can_file_res);
				$current_par_can_file_res = NULL;
				unlink($current_par_can_file_path);
				$current_par_can_file_path = NULL;
				if($httpCode == 200 || $httpCode == 201 || $httpCode == 204) {
					ScriptsConfig::getLogger()->addInfo("PAR_CAN uploading done successfully, the httpCode is : ".$httpCode);
				} else {
					$msg = "an error occurred while uploading the PAR_CAN file, the httpCode is : ".$httpCode;
					ScriptsConfig::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				//DONE
				self::setBillingsSubscriptionsStatus($billingsSubscriptionsOkToProceed, 'pending_canceled');
				self::setRequestsAreDone($billingsSubscriptionActionLogs);
				$processingLog->setProcessingStatus('done');
				ScriptsConfig::getLogger()->addInfo("requesting bachat subscriptions canceling done successfully");
				BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsRequestCancel.'.success');
			} else {
				//NOTHING TO DO YET
				$processingLog->setProcessingStatus('postponed');
				ScriptsConfig::getLogger()->addInfo("requesting bachat subscriptions canceling postponed successfully");
			}
		} catch(Exception $e) {
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsRequestCancel.'.error');
			$msg = "an error occurred while requesting bachat subscriptions canceling, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			self::setRequestsAreFailed($billingsSubscriptionActionLogs, $e->getMessage());
			if(isset($processingLog)) {
				$processingLog->setProcessingStatus('error');
				$processingLog->setMessage($msg);
			}
		} finally {
			$timingInMillis = round((microtime(true) - $starttime) * 1000);
			BillingStatsd::timing('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsRequestCancel.'.timing', $timingInMillis);
			if(isset($current_par_can_file_res)) {
				fclose($current_par_can_file_res);
				$current_par_can_file_res = NULL;
			}
			if(isset($current_par_can_file_path)) {
				unlink($current_par_can_file_path);
				$current_par_can_file_path = NULL;
			}
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				self::doSaveBillingsSubscriptionsStatus($billingsSubscriptionsOkToProceed);
				self::doSaveBillingsSubscriptionActionLogs($billingsSubscriptionActionLogs);
				if(isset($processingLog)) {
					ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
				}
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
		}
	}
	
	private function doRequestCancelSubscription(BillingsSubscriptionActionLog $billingsSubscriptionActionLog, BillingsSubscription $subscription, $current_par_can_file_res) {
		try {
			//
			ScriptsConfig::getLogger()->addInfo("preparing bachat subscription canceling for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()."...");
			/*$plan = PlanDAO::getPlanById($subscription->getPlanId());
			if($plan == NULL) {
				$msg = "unknown provider plan with id : ".$subscription->getPlanId();
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}*/
			$fields = array();
			$day = $subscription->getSubCanceledDate();
			$day->setTimezone(new DateTimeZone(self::$timezone));
			$day_str = $day->format("dmY");
			$fields[] = $day_str;
			$time = $subscription->getSubCanceledDate();
			$time->setTimezone(new DateTimeZone(self::$timezone));
			$time_str = $time->format("His");
			$fields[] = $time_str;
			$fields[] = $this->provider->getServiceId();//ServiceId
			$fields[] = $subscription->getSubscriptionBillingUuid();//SubscriptionServiceId
			$fields[] = $subscription->getSubUid();//SubscriptionId
			fputcsv($current_par_can_file_res, $fields);
			ScriptsConfig::getLogger()->addInfo("preparing bachat subscription canceling for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()." done successfully");
		} catch(Exception $e) {
			$msg = "an error occurred while preparing bachat subscription canceling for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			$billingsSubscriptionActionLog->setProcessingStatus("error");
			$billingsSubscriptionActionLog->setMessage($msg);
			throw $e;
		}
	}
	
	public function doCheckRenewResultFile() {
		$starttime = microtime(true);
		$processingLog  = NULL;
		$current_ren_file_path = NULL;
		$current_ren_file_res = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($this->provider->getId(), $this->processingTypeSubsResponseRenew, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("checking bachat subscriptions renewal bypassed - already done today -");
				return;
			}
			
			ScriptsConfig::getLogger()->addInfo("checking bachat subscriptions renewal file...");
			
			$processingLog = ProcessingLogDAO::addProcessingLog($this->provider->getId(), $this->processingTypeSubsResponseRenew);
			
			if(($current_ren_file_path = tempnam('', 'tmp')) === false) {
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "REN file cannot be created");
			}
			if(($current_ren_file_res = fopen($current_ren_file_path, "w")) === false) {
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "REN file cannot be open (for write)");
			}
			ScriptsConfig::getLogger()->addInfo("REN file successfully created here : ".$current_ren_file_path);
			
			$file_found = false;
			
			//GET FILE FROM THE SYSTEM WEBDAV (GET)
			ScriptsConfig::getLogger()->addInfo("REN downloading...");
			$url = $this->getBouyguesBillingSystemUrl()."/"."REN_".$this->today->format("Ymd").".csv";
			$curl_options = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER  => false,
				CURLOPT_FOLLOWLOCATION => true
			);
			if(	null !== (getEnv('BOUYGUES_PROXY_HOST'))
				&&
				null !== (getEnv('BOUYGUES_PROXY_PORT'))
			) {
				$curl_options[CURLOPT_PROXY] = getEnv('BOUYGUES_PROXY_HOST');
				$curl_options[CURLOPT_PROXYPORT] = getEnv('BOUYGUES_PROXY_PORT');
			}
			if(	null !== (getEnv('BOUYGUES_PROXY_USER'))
				&&
				null !== (getEnv('BOUYGUES_PROXY_PWD'))
			) {
				$curl_options[CURLOPT_PROXYUSERPWD] = getEnv('BOUYGUES_PROXY_USER').":".getEnv('BOUYGUES_PROXY_PWD');
			}
			if(	null !== ($this->provider->getApiKey())
				&&
				null !== ($this->provider->getApiSecret())
			) {
				$curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
				$curl_options[CURLOPT_USERPWD] = $this->provider->getApiKey().":".$this->provider->getApiSecret();
			}
			$curl_options[CURLOPT_VERBOSE] = true;
			$CURL = curl_init();
			curl_setopt_array($CURL, $curl_options);
			$content = curl_exec($CURL);
			$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
			curl_close($CURL);
			if($httpCode == 200) {
				$file_found = true;
				fwrite($current_ren_file_res, $content);
			} else if($httpCode == 404) {
				$file_found = false;
			} else {
				$msg = "an error occurred while downloading the REN file, the httpCode is : ".$httpCode;
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//anyway
			fclose($current_ren_file_res);
			$current_ren_file_res = NULL;
			if($file_found) {
				BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsResponseRenew.'.hit');
				if(($current_ren_file_res = fopen($current_ren_file_path, "r")) === false) {
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "REN file cannot be open (for read)");
				}
				$this->doProcessRenewResultFile($current_ren_file_res);
				fclose($current_ren_file_res);
				$current_ren_file_res = NULL;
				unlink($current_ren_file_path);
				$current_ren_file_path = NULL;
				$processingLog->setProcessingStatus('done');
				ScriptsConfig::getLogger()->addInfo("checking bachat subscriptions renewal file done successfully");
				BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsResponseRenew.'.success');
			} else {
				//NOTHING TO DO YET
				$processingLog->setProcessingStatus('postponed');
				ScriptsConfig::getLogger()->addInfo("checking bachat subscriptions renewal file postponed successfully");
			}
		} catch(Exception $e) {
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsResponseRenew.'.error');
			$msg = "an error occurred while checking bachat subscriptions renewal file, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($processingLog)) {
				$processingLog->setProcessingStatus('error');
				$processingLog->setMessage($msg);
			}
		} finally {
			$timingInMillis = round((microtime(true) - $starttime) * 1000);
			BillingStatsd::timing('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsResponseRenew.'.timing', $timingInMillis);
			if(isset($current_ren_file_res)) {
				fclose($current_ren_file_res);
				$current_ren_file_res = NULL;
			}
			if(isset($current_ren_file_path)) {
				unlink($current_ren_file_path);
				$current_ren_file_path = NULL;
			}
			if(isset($processingLog)) {
				ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			}
		}
	}
	
	private function doProcessRenewResultFile($current_ren_file_res)
	{
		$fields = NULL;
		while(($fields = fgetcsv($current_ren_file_res)) !== false) {
			$this->doProcessRenewResultLine($fields);
		}
	}
	
	private function doProcessRenewResultLine($current_line_fields) {
		$billingsSubscriptionActionLog = NULL;
		try {
			ScriptsConfig::getLogger()->addInfo("processing a renew line...");
			if(count($current_line_fields) < 7) {
				$msg = "line cannot be processed, it contains only ".count($current_line_fields)." fields, 7 minimum are expected";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$date_str = $current_line_fields[0];
			$time_str = $current_line_fields[1];
			$serviceId = $current_line_fields[2];
			$subscriptionServiceId = $current_line_fields[3];
			$subscriptionId = $current_line_fields[4];
			$transactionId = $current_line_fields[5];
			$state = $current_line_fields[6];
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionServiceId);
			//check subscription does or not exist
			if($subscription == NULL) {
				$msg = "subscription with subscriptionServiceId=".$subscriptionServiceId." could not been found";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "response_renew");
			//check
			$start_date = DateTime::createFromFormat("dmY His", $date_str." ".$time_str, new DateTimeZone(self::$timezone));
			if($start_date === false) {
				$msg = "line cannot be processed, date : ".$date_str." ".$time_str." cannot be parsed";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($serviceId != $this->provider->getServiceId()) {
				$msg = "serviceId ".$serviceId." is unknown";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($subscription->getSubUid() != $subscriptionId) {
				$msg = "subscription uuid are different in both sides (billingsapi=".$subscription->getSubUid().", subscriptionId=".$subscriptionId.")";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$cause = $state;
			$renew_done = false;
			switch($state) {
				case "renewed" :
					$renew_done = true;
					break;
				case "missing_subscriptionid" :
					$renew_done = false;
					break;
				case "renewal_failure" :
					$renew_done = false;
					break;					
				case "data_check_error" :
					$renew_done = false;
					break;
				case "VAT_not_allowed" :
					$renew_done = false;
					break;
				case "automatic_retry" :
					$renew_done = false;
					break;
				case "renewal_error" :
					$renew_done = false;
					break;
				default :
					$renew_done = false;
					break;
			}
			if(!$renew_done) {
				$msg = "subscription could not be renewed, cause=".$cause;
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			try {
				//START TRANSACTION
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
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			ScriptsConfig::getLogger()->addInfo("processing a renew line done successfully");
			$billingsSubscriptionActionLog = NULL;
		} catch(BillingsException $e) {
			$msg = "an error occurred while processing a renew line, error_code=".$e->getCode().", error_message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError("processing a renew line failed : ".$msg);
			if(isset($billingsSubscriptionActionLog)) {
				$billingsSubscriptionActionLog->setProcessingStatus("error");
				$billingsSubscriptionActionLog->setMessage($msg);
			}
			/*throw $e;*/
		} catch(Exception $e) {
			$msg = "an error occurred while processing a renew line, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($billingsSubscriptionActionLog)) {
				$billingsSubscriptionActionLog->setProcessingStatus("error");
				$billingsSubscriptionActionLog->setMessage($msg);
			}
			/*throw $e;*/
		} finally {
			if(isset($billingsSubscriptionActionLog)) {
				$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
			}
		}
	}
	
	public function doCheckCancelResultFile() {
		$starttime = microtime(true);
		$processingLog  = NULL;
		$current_can_file_path = NULL;
		$current_can_file_res = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($this->provider->getId(), $this->processingTypeSubsResponseCancel, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("checking bachat subscriptions canceling bypassed - already done today -");
				return;
			}
			
			ScriptsConfig::getLogger()->addInfo("checking bachat subscriptions cancel file...");
			
			$processingLog = ProcessingLogDAO::addProcessingLog($this->provider->getId(), $this->processingTypeSubsResponseCancel);
			
			if(($current_can_file_path = tempnam('', 'tmp')) === false) {
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "CAN file cannot be created");
			}
			if(($current_can_file_res = fopen($current_can_file_path, "w")) === false) {
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "CAN file cannot be open (for write)");
			}
			ScriptsConfig::getLogger()->addInfo("CAN file successfully created here : ".$current_can_file_path);
			
			$file_found = false;
			
			//GET FILE FROM THE SYSTEM WEBDAV (GET)
			ScriptsConfig::getLogger()->addInfo("CAN downloading...");
			$url = $this->getBouyguesBillingSystemUrl()."/"."CAN_".$this->today->format("Ymd").".csv";
			$curl_options = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER  => false,
				CURLOPT_FOLLOWLOCATION => true
			);
			if(	null !== (getEnv('BOUYGUES_PROXY_HOST'))
				&&
				null !== (getEnv('BOUYGUES_PROXY_PORT'))
			) {
				$curl_options[CURLOPT_PROXY] = getEnv('BOUYGUES_PROXY_HOST');
				$curl_options[CURLOPT_PROXYPORT] = getEnv('BOUYGUES_PROXY_PORT');
			}
			if(	null !== (getEnv('BOUYGUES_PROXY_USER'))
				&&
				null !== (getEnv('BOUYGUES_PROXY_PWD'))
			) {
				$curl_options[CURLOPT_PROXYUSERPWD] = getEnv('BOUYGUES_PROXY_USER').":".getEnv('BOUYGUES_PROXY_PWD');
			}
			if(	null !== ($this->provider->getApiKey())
				&&
				null !== ($this->provider->getApiSecret())
			) {
				$curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
				$curl_options[CURLOPT_USERPWD] = $this->provider->getApiKey().":".$this->provider->getApiSecret();
			}
			$curl_options[CURLOPT_VERBOSE] = true;
			$CURL = curl_init();
			curl_setopt_array($CURL, $curl_options);
			$content = curl_exec($CURL);
			$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
			curl_close($CURL);
			if($httpCode == 200) {
				$file_found = true;
				fwrite($current_can_file_res, $content);
			} else if($httpCode == 404) {
				$file_found = false;
			} else {
				$msg = "an error occurred while downloading the CAN file, the httpCode is : ".$httpCode;
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//anyway
			fclose($current_can_file_res);
			$current_can_file_res = NULL;
			if($file_found) {
				BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsResponseCancel.'.hit');
				if(($current_can_file_res = fopen($current_can_file_path, "r")) === false) {
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "CAN file cannot be open (for read)");
				}
				$this->doProcessCancelResultFile($current_can_file_res);
				fclose($current_can_file_res);
				$current_can_file_res = NULL;
				unlink($current_can_file_path);
				$current_can_file_path = NULL;
				$processingLog->setProcessingStatus('done');
				ScriptsConfig::getLogger()->addInfo("checking bachat subscriptions cancel file done successfully");
				BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsResponseCancel.'.success');
			} else {
				//NOTHING TO DO YET
				$processingLog->setProcessingStatus('postponed');
				ScriptsConfig::getLogger()->addInfo("checking bachat subscriptions cancel file postponed successfully");
			}
		} catch(Exception $e) {
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsResponseCancel.'.error');
			$msg = "an error occurred while checking bachat subscriptions cancel file, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($processingLog)) {
				$processingLog->setProcessingStatus('error');
				$processingLog->setMessage($msg);
			}
		} finally {
			$timingInMillis = round((microtime(true) - $starttime) * 1000);
			BillingStatsd::timing('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingTypeSubsResponseCancel.'.timing', $timingInMillis);
			if(isset($processingLog)) {
				ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			}
			if(isset($current_can_file_res)) {
				fclose($current_can_file_res);
				$current_can_file_res = NULL;
			}
			if(isset($current_can_file_path)) {
				unlink($current_can_file_path);
				$current_can_file_path = NULL;
			}
		}
	}
	
	private function doProcessCancelResultFile($current_can_file_res) {
		$fields = NULL;
		while(($fields = fgetcsv($current_can_file_res)) !== false) {
			$this->doProcessCancelResultLine($fields);
		}
	}
	
	private function doProcessCancelResultLine($current_line_fields) {
		$billingsSubscriptionActionLog = NULL;
		try {
			ScriptsConfig::getLogger()->addInfo("processing a cancel line...");
			if(count($current_line_fields) < 6) {
				$msg = "line cannot be processed, it contains only ".count($current_line_fields)." fields, 6 minimum are expected";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$date_str = $current_line_fields[0];
			$time_str = $current_line_fields[1];
			$serviceId = $current_line_fields[2];
			$subscriptionServiceId = $current_line_fields[3];
			$subscriptionId = $current_line_fields[4];
			$state = $current_line_fields[5];
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionServiceId);
			//check subscription does or not exist
			if($subscription == NULL) {
				$msg = "subscription with subscriptionServiceId=".$subscriptionServiceId." could not been found";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "response_cancel");
			//check
			$cancel_date = DateTime::createFromFormat("dmY His", $date_str." ".$time_str, new DateTimeZone(self::$timezone));
			if($cancel_date === false) {
				$msg = "line cannot be processed, date : ".$date_str." ".$time_str." cannot be parsed";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($serviceId != $this->provider->getServiceId()) {
				$msg = "serviceId ".$serviceId." is unknown";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($subscription->getSubUid() != $subscriptionId) {
				$msg = "subscription uuid are different in both sides (billingsapi=".$subscription->getSubUid().", subscriptionId=".$subscriptionId.")";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$cause = $state;
			$cancel_done = false;
			switch($state) {
				case "cancelled_via_customercare" :
				case "cancelled_via_partner" :
				case "cancelled_no_renewal" :
				case "cancelled_billing_err" :
					$cancel_done = true;
					break;
				case "missing_subscriptionid" :
					$cancel_done = false;
					break;
				case "data_check_error" :
					$cancel_done = false;
					break;
				case "automatic_retry" :
					$cancel_done = false;
					break;
				case "cancel_error" :
					$cancel_done = false;
					break;
				default :
					$cancel_done = false;
					break;
			}
			if(!$cancel_done) {
				$msg = "subscription could not be canceled, cause=".$cause;
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				$subscriptionsHandler = new SubscriptionsHandler();
				$cancelSubscriptionRequest = new CancelSubscriptionRequest();
				$cancelSubscriptionRequest->setSubscriptionBillingUuid($subscription->getSubscriptionBillingUuid());
				$cancelSubscriptionRequest->setOrigin('script');
				$cancelSubscriptionRequest->setCancelDate($cancel_date);
				$subscriptionsHandler->doCancelSubscription($cancelSubscriptionRequest);
				$billingsSubscriptionActionLog->setProcessingStatus('done');
				$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			ScriptsConfig::getLogger()->addInfo("processing a cancel line done successfully");
			$billingsSubscriptionActionLog = NULL;
		} catch(BillingsException $e) {
			$msg = "an error occurred while processing a cancel line, error_code=".$e->getCode().", error_message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError("processing a cancel line failed : ".$msg);
			if(isset($billingsSubscriptionActionLog)) {
				$billingsSubscriptionActionLog->setProcessingStatus("error");
				$billingsSubscriptionActionLog->setMessage($msg);
			}
			/*throw $e;*/
		} catch(Exception $e) {
			$msg = "an error occurred while processing a cancel line, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($billingsSubscriptionActionLog)) {
				$billingsSubscriptionActionLog->setProcessingStatus("error");
				$billingsSubscriptionActionLog->setMessage($msg);
			}
			/*throw $e;*/
		} finally {
			if(isset($billingsSubscriptionActionLog)) {
				BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
			}
		}
	}
	
	private function getBouyguesBillingSystemUrl() {
		return(getEnv('BOUYGUES_BILLING_SYSTEM_URL_PREFIX').$this->provider->getMerchantId().'_'.$this->provider->getServiceId());
	}
	
}

?>