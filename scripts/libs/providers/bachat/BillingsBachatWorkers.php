<?php

require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';

class BillingsBachatWorkers {
	
	public function __construct() {
	}
	
	public function doRequestRenewSubscriptions($force = false) {
		$billingsSubscriptionActionLogs = array();
		$current_par_ren_file_path = NULL;
		$current_par_ren_file_res = NULL;
		try {
			ScriptsConfig::getLogger()->addInfo("requesting bachat subscriptions renewal...");
			if(($current_par_ren_file_path = tempnam('', 'tmp')) === false) {
				throw new BillingsException("PAR_REN file cannot be created");
			}
			if(($current_par_ren_file_res = fopen($current_par_ren_file_path, "w")) === false) {
				throw new BillingsException("PAR_REN file cannot be open");
			}
			ScriptsConfig::getLogger()->addInfo("PAR_REN file successfully created here : ".$current_par_ren_file_path);
			
			$provider_name = "bachat";
			
			$provider = ProviderDAO::getProviderByName($provider_name);
			
			if($provider == NULL) {
				$msg = "unknown provider named : ".$provider_name;
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$offset = 0;
			$limit = 100;
			$today = new DateTime();
			//will select all day strictly before tommorrow (the reason why DateInterval is +1 DAY) 
			$sub_period_ends_date = clone $today;
			$sub_period_ends_date->add(new DateInterval("P1D"));
			$sub_period_ends_date->setTime(0, 0, 0);
			//
			$status_array = array('active');
			if($force) {
				$status_array[] = 'pending_active';
			}
			//
			$billingsSubscriptionsOkToProceed = array();
			while(count($endingBillingsSubscriptions = BillingsSubscriptionDAO::getEndingBillingsSubscriptions($limit, $offset, $provider->getId(), $sub_period_ends_date, $status_array)) > 0) {
				ScriptsConfig::getLogger()->addInfo("processing...current offset=".$offset);
				$offset = $offset + $limit;
				//
				foreach($endingBillingsSubscriptions as $endingBillingsSubscription) {
					//
					$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($endingBillingsSubscription->getId(), "renew_request");
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
			}
			//SEND FILE TO THE SYSTEM WEBDAV (PUT)
			ScriptsConfig::getLogger()->addInfo("PAR_REN uploading...");
			$url = getEnv('BOUYGUES_BILLING_SYSTEM_URL');
			$data = array(
					"filename" => "PAR_REN_".$today->format("Ymd").".csv"
			);
			$curl_options = array(
					CURLOPT_URL => $url,
					CURLOPT_PUT => true,
					CURLOPT_INFILE => $current_par_ren_file_res,
					CURLOPT_INFILESIZE => filesize($current_par_ren_file_path),
					CURLOPT_POSTFIELDS, http_build_query($data),
					CURLOPT_HTTPHEADER => array(
							'Content-Type: text/csv'
					),
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_HEADER  => false
			);
			if(	null !== (getEnv('BOUYGUES_PROXY'))
				&&
				null !== (getEnv('BOUYGUES_PROXY_PORT'))
			) {
				$curl_options[CURLOPT_PROXY] = getEnv('BOUYGUES_PROXY');
				$curl_options[CURLOPT_PROXYPORT] = getEnv('BOUYGUES_PROXY_PORT');
			}
			if(	null !== (getEnv('BOUYGUES_PROXY_USER'))
				&&
				null !== (getEnv('BOUYGUES_PROXY_PWD'))
			) {
				$curl_options[CURLOPT_PROXYUSERPWD] = getEnv('BOUYGUES_PROXY_USER').":".getEnv('BOUYGUES_PROXY_PWD');
			}
			$CURL = curl_init();
			curl_setopt_array($CURL, $curl_options);
			$content = curl_exec($CURL);
			$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
			curl_close($CURL);
			fclose($current_par_ren_file_res);
			unlink($current_par_ren_file_path);
			$current_par_ren_file_res = NULL;
			$current_par_ren_file_path = NULL;
			if($httpCode == 200 || $httpCode == 204) {
				ScriptsConfig::getLogger()->addInfo("PAR_REN uploading done successfully, the httpCode is : ".$httpCode);
			} else {
				$msg = "an error occurred while uploading the PAR_REN file, the httpCode is : ".$httpCode;
				ScriptsConfig::getLogger()->addError($msg);
				throw new Exception($msg);
			}
			//DONE
			self::setBillingsSubscriptionsStatus($billingsSubscriptionsOkToProceed, 'pending_active');
			self::setRequestsAreDone($billingsSubscriptionActionLogs);
			ScriptsConfig::getLogger()->addInfo("requesting bachat subscriptions renewal done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("an error occurred while requesting bachat subscriptions renewal, message=".$e->getMessage());
			self::setRequestsAreFailed($billingsSubscriptionActionLogs, $e->getMessage());
		} finally {
			//START TRANSACTION
			pg_query("BEGIN");
			self::doSaveBillingsSubscriptionsStatus($billingsSubscriptionsOkToProceed);
			self::doSaveBillingsSubscriptionActionLogs($billingsSubscriptionActionLogs);
			//COMMIT
			pg_query("COMMIT");
			if(isset($current_par_ren_file_res)) {
				fclose($current_par_ren_file_res);
				$current_par_ren_file_res = NULL;
			}
			if(isset($current_par_ren_file_path)) {
				unlink($current_par_ren_file_path);
				$current_par_ren_file_path = NULL;
			}
		}
	}
	
	private function doRequestRenewSubscription(BillingsSubscriptionActionLog $billingsSubscriptionActionLog, BillingsSubscription $subscription, $current_par_ren_file_res) {
		try {
			//
			ScriptsConfig::getLogger()->addInfo("preparing bachat subscription renewal for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()."...");
			/*$plan = PlanDAO::getPlanById($subscription->getPlanId());
			if($plan == NULL) {
				$msg = "unknown provider plan with id : ".$subscription->getPlanId();
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}*/
			$fields = array();
			$fields[] = (new DateTime())->format("dmY");//current Day// was : (new DateTime($subscription->getSubPeriodEndsDate()))->format("dmY");//DATE DDMMYYYY
			$fields[] = (new DateTime($subscription->getSubPeriodEndsDate()))->format("His");//TIME HHMMSS
			$fields[] = getEnv("BOUYGUES_SERVICEID");//ServiceId
			$fields[] = $subscription->getSubscriptionBillingUuid();//SubscriptionServiceId
			$fields[] = $subscription->getSubUid();//SubscriptionId
			$fields[] = "20.0";//VAT
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
	
	public function doRequestCancelSubscriptions($force = false) {
		$billingsSubscriptionActionLogs = array();
		$current_par_can_file_path = NULL;
		$current_par_can_file_res = NULL;
		try {
			ScriptsConfig::getLogger()->addInfo("requesting bachat subscriptions cancelling...");
			if(($current_par_can_file_path = tempnam('', 'tmp')) === false) {
				throw new BillingsException("PAR_CAN file cannot be created");
			}
			if(($current_par_can_file_res = fopen($current_par_can_file_path, "w")) === false) {
				throw new BillingsException("PAR_CAN file cannot be open");
			}
			ScriptsConfig::getLogger()->addInfo("PAR_CAN file successfully created here : ".$current_par_can_file_path);
			
			$provider_name = "recurly";//bachat";
			
			$provider = ProviderDAO::getProviderByName($provider_name);
			
			if($provider == NULL) {
				$msg = "unknown provider named : ".$provider_name;
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$offset = 0;
			$limit = 100;
			$today = new DateTime(); 
			//
			$status_array = array('requesting_canceled');
			if($force) {
				$status_array[] = 'pending_canceled';
			}
			//
			$billingsSubscriptionsOkToProceed = array();
			while(count($requestingCanceledBillingsSubscriptions = BillingsSubscriptionDAO::getRequestingCanceledBillingsSubscriptions($limit, $offset, $provider->getId(), $status_array)) > 0) {
				ScriptsConfig::getLogger()->addInfo("processing...current offset=".$offset);
				$offset = $offset + $limit;
				//
				foreach($requestingCanceledBillingsSubscriptions as $requestingCanceledBillingsSubscription) {
					//
					$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($requestingCanceledBillingsSubscription->getId(), "cancel_request");
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
			}
			//SEND FILE TO THE SYSTEM WEBDAV (PUT)
			ScriptsConfig::getLogger()->addInfo("PAR_CAN uploading...");
			$url = getEnv('BOUYGUES_BILLING_SYSTEM_URL');
			$data = array(
					"filename" => "PAR_CAN_".$today->format("Ymd").".csv"
			);
			$curl_options = array(
					CURLOPT_URL => $url,
					CURLOPT_PUT => true,
					CURLOPT_INFILE => $current_par_can_file_res,
					CURLOPT_INFILESIZE => filesize($current_par_can_file_path),
					CURLOPT_POSTFIELDS, http_build_query($data),
					CURLOPT_HTTPHEADER => array(
							'Content-Type: text/csv'
					),
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_HEADER  => false
			);
			if(	null !== (getEnv('BOUYGUES_PROXY'))
				&&
				null !== (getEnv('BOUYGUES_PROXY_PORT'))
			) {
				$curl_options[CURLOPT_PROXY] = getEnv('BOUYGUES_PROXY');
				$curl_options[CURLOPT_PROXYPORT] = getEnv('BOUYGUES_PROXY_PORT');
			}
			if(	null !== (getEnv('BOUYGUES_PROXY_USER'))
				&&
				null !== (getEnv('BOUYGUES_PROXY_PWD'))
			) {
				$curl_options[CURLOPT_PROXYUSERPWD] = getEnv('BOUYGUES_PROXY_USER').":".getEnv('BOUYGUES_PROXY_PWD');
			}
			$CURL = curl_init();
			curl_setopt_array($CURL, $curl_options);
			$content = curl_exec($CURL);
			$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
			curl_close($CURL);
			fclose($current_par_can_file_res);
			unlink($current_par_can_file_path);
			$current_par_can_file_res = NULL;
			$current_par_can_file_path = NULL;
			if($httpCode == 200 || $httpCode == 204) {
				ScriptsConfig::getLogger()->addInfo("PAR_CAN uploading done successfully, the httpCode is : ".$httpCode);
			} else {
				$msg = "an error occurred while uploading the PAR_CAN file, the httpCode is : ".$httpCode;
				ScriptsConfig::getLogger()->addError($msg);
				throw new Exception($msg);
			}
			//DONE
			self::setBillingsSubscriptionsStatus($billingsSubscriptionsOkToProceed, 'pending_canceled');
			self::setRequestsAreDone($billingsSubscriptionActionLogs);
			ScriptsConfig::getLogger()->addInfo("requesting bachat subscriptions cancelling done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("an error occurred while requesting bachat subscriptions cancelling, message=".$e->getMessage());
			self::setRequestsAreFailed($billingsSubscriptionActionLogs, $e->getMessage());
		} finally {
			//START TRANSACTION
			pg_query("BEGIN");
			self::doSaveBillingsSubscriptionsStatus($billingsSubscriptionsOkToProceed);
			self::doSaveBillingsSubscriptionActionLogs($billingsSubscriptionActionLogs);
			//COMMIT
			pg_query("COMMIT");
			if(isset($current_par_can_file_res)) {
				fclose($current_par_can_file_res);
				$current_par_can_file_res = NULL;
			}
			if(isset($current_par_can_file_path)) {
				unlink($current_par_can_file_path);
				$current_par_can_file_path = NULL;
			}
		}
	}
	
	private function doRequestCancelSubscription(BillingsSubscriptionActionLog $billingsSubscriptionActionLog, BillingsSubscription $subscription, $current_par_can_file_res) {
		try {
			//
			ScriptsConfig::getLogger()->addInfo("preparing bachat subscription cancelling for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()."...");
			/*$plan = PlanDAO::getPlanById($subscription->getPlanId());
			if($plan == NULL) {
				$msg = "unknown provider plan with id : ".$subscription->getPlanId();
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}*/
			$fields = array();
			$fields[] = (new DateTime($subscription->getSubCanceledDate()))->format("dmY");//must be in the past//DATE DDMMYYYY
			$fields[] = (new DateTime($subscription->getSubCanceledDate()))->format("His");//TIME HHMMSS
			$fields[] = getEnv("BOUYGUES_SERVICEID");//ServiceId
			$fields[] = $subscription->getSubscriptionBillingUuid();//SubscriptionServiceId
			$fields[] = $subscription->getSubUid();//SubscriptionId
			fputcsv($current_par_can_file_res, $fields);
			ScriptsConfig::getLogger()->addInfo("preparing bachat subscription cancelling for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()." done successfully");
		} catch(Exception $e) {
			$msg = "an error occurred while preparing bachat subscription cancelling for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			$billingsSubscriptionActionLog->setProcessingStatus("error");
			$billingsSubscriptionActionLog->setMessage($msg);
			throw $e;
		}
	}
	
	public function doBlabla() {
		
	}
	
	private function doProcessRenewResultFile($current_par_ren_result_file_res)
	{
		$fields = NULL;
		while(($fields = fgetcsv($current_par_ren_result_file_res)) != NULL) {
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
				throw new Exception(new ExceptionType(ExceptionType::internal), $msg);
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
				throw new Exception(new ExceptionType(ExceptionType::internal), $msg);
			}
			$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "renew_response");
			//check
			$start_date = DateTime::createFromFormat("dmY His", $date_str." ".$time_str);
			if($start_date === false) {
				$msg = "line cannot be processed, date : ".$date_str." ".$time_str." cannot be parsed";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($serviceId != getenv('BOUYGUES_SERVICEID')) {
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
			//START TRANSACTION
			pg_query("BEGIN");
			$subscriptionsHandler = new SubscriptionsHandler();
			$subscriptionsHandler->doRenewSubscription($subscription, $start_date);
			$billingsSubscriptionActionLog->setProcessingStatus('done');
			BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
			//COMMIT
			pg_query("COMMIT");
			ScriptsConfig::getLogger()->addInfo("processing a renew line done successfully");
			$billingsSubscriptionActionLog = NULL;
		} catch(BillingsException $e) {
			$msg = "an error occurred while processing a renew line, error_code=".$e->getCode().", error_message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError("processing a renew line failed : ".$msg);
			$billingsSubscriptionActionLog->setProcessingStatus("error");
			$billingsSubscriptionActionLog->setMessage($msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an error occurred while processing a renew line, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			//$billingsSubscriptionActionLog not initialized !
			throw $e;
		} finally {
			if(isset($billingsSubscriptionActionLog)) {
				BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
			}
		}
	}
	
	public function doToto() {
		
	}
	
	private function doProcessCancelResultFile($current_par_can_result_file_res) {
		$fields = NULL;
		while(($fields = fgetcsv($current_par_can_result_file_res)) != NULL) {
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
				throw new Exception(new ExceptionType(ExceptionType::internal), $msg);
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
				throw new Exception(new ExceptionType(ExceptionType::internal), $msg);
			}
			$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "cancel_response");
			//check
			$cancel_date = DateTime::createFromFormat("dmY His", $date_str." ".$time_str);
			if($cancel_date === false) {
				$msg = "line cannot be processed, date : ".$date_str." ".$time_str." cannot be parsed";
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($serviceId != getenv('BOUYGUES_SERVICEID')) {
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
			//START TRANSACTION
			pg_query("BEGIN");
			$subscriptionsHandler = new SubscriptionsHandler();
			$subscriptionsHandler->doCancelSubscription($subscription, $cancel_date);
			$billingsSubscriptionActionLog->setProcessingStatus('done');
			BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
			//COMMIT
			pg_query("COMMIT");
			ScriptsConfig::getLogger()->addInfo("processing a cancel line done successfully");
			$billingsSubscriptionActionLog = NULL;
		} catch(BillingsException $e) {
			$msg = "an error occurred while processing a cancel line, error_code=".$e->getCode().", error_message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError("processing a cancel line failed : ".$msg);
			$billingsSubscriptionActionLog->setProcessingStatus("error");
			$billingsSubscriptionActionLog->setMessage($msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an error occurred while processing a cancel line, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			//$billingsSubscriptionActionLog not initialized !
			throw $e;
		} finally {
			if(isset($billingsSubscriptionActionLog)) {
				BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
			}
		}
	}

}

?>