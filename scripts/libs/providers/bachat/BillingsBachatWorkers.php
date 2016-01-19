<?php

require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';

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
			$provider_name = "recurly";//"bachat";
			
			$provider = ProviderDAO::getProviderByName($provider_name);
			
			if($provider == NULL) {
				$msg = "unknown provider named : ".$provider_name;
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$offset = 0;
			$limit = 100;
			$today = new DateTime(); 
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
			$plan = PlanDAO::getPlanById($subscription->getPlanId());
			if($plan == NULL) {
				$msg = "unknown provider plan with id : ".$subscription->getPlanId();
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$fields = array();
			$fields[] = (new DateTime($subscription->getSubPeriodEndsDate()))->format("dmY");//DATE DDMMYYYY
			$fields[] = (new DateTime($subscription->getSubPeriodEndsDate()))->format("His");//TIME HHMMSS
			$fields[] = getEnv("BOUYGUES_SERVICEID");//ServiceId
			$fields[] = $plan->getPlanUuid();//TODO : NOT SURE ON WHAT IT IS//SubscriptionServiceId
			$fields[] = $subscription->getSubUid();//SubscriptionId
			$fields[] = "20.00";//VAT
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
	
	public function doRequestCancelSubscriptions() {
		//TODO
	}
	
	private function doRequestCancelSubscription(BillingsSubscription $subscription, $current_par_ren_file_res) {
		//TODO	
	}
}

?>