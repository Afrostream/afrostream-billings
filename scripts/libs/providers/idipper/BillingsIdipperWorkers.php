<?php

require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../../../../libs/providers/idipper/client/IdipperClient.php';

class BillingsIdipperWorkers extends BillingsWorkers {
	
	public function __construct() {
		parent::__construct();
	}
	
	public function doRefreshSubscriptions() {
		$processingLog  = NULL;
		try {
			$provider_name = "idipper";
			
			$provider = ProviderDAO::getProviderByName($provider_name);
		
			if($provider == NULL) {
				$msg = "unknown provider named : ".$provider_name;
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($provider->getId(), 'subs_refresh', $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("refreshing idipper subscriptions bypassed - already done today -");
				exit;
			}
				
			ScriptsConfig::getLogger()->addInfo("refreshing idipper subscriptions...");
				
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
			ScriptsConfig::getLogger()->addInfo("refreshing idipper subscriptions done successfully");
			$processingLog = NULL;
		} catch(Exception $e) {
			$msg = "an error occurred while refreshing idipper subscriptions, message=".$e->getMessage();
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
			ScriptsConfig::getLogger()->addInfo("refreshing idipper subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()."...");
			$user = UserDAO::getUserById($subscription->getUserId());
			if($user == NULL) {
				$msg = "unknown user with id : ".$subscription->getUserId();
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$plan = PlanDAO::getPlanById($subscription->getPlanId());
			if($plan == NULL) {
				$msg = "unknown plan with id : ".$subscription->getPlanId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$idipperClient = new IdipperClient();
			$utilisateurRequest = new UtilisateurRequest();
			$utilisateurRequest->setExternalUserID($user->getUserReferenceUuid());
			$utilisateurReponse = $idipperClient->getUtilisateur($utilisateurRequest);
			$current_rubrique = NULL;
			$rubriqueFound = false;
			$hasSubscribed = false;
			foreach ($utilisateurResponse->getRubriques() as $rubrique) {
				if($rubrique->getIDRubrique() == $plan->getPlanUuid()) {
					$current_rubrique = $rubrique;
					$rubriqueFound = true;
					if($rubrique->getAbonne() == '1') {
						$hasSubscribed = true;
					}
					break;
				}
			}
			if(!$rubriqueFound) {
				$msg = "rubrique with id=".$plan->getPlanUuid()." was not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$now = (new DateTime())->setTimezone(new DateTimeZone(self::$timezone));
			$to_be_canceled = false;
			$cancel_date = NULL;
			$to_be_renewed = false;
			$renewed_start_date = NULL;
			$renewed_until_date = NULL;
			if($hasSubscribed) {
				$end_date_str = $current_rubrique->getCreditExpiration();
				$end_date = DateTime::createFromFormat("Y-m-d H:i:s", $end_date_str, new DateTimeZone(config::$timezone));
				if($end_date === false) {
					$msg = "rubrique credit expiration date cannot be processed, date : ".$end_date_str." cannot be parsed";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($end_date > $now) {
					//RENEW
					$to_be_renewed = true;
					$renewed_start_date = NULL;//auto
					$renewed_until_date = $end_date;
				} else {
					$grace_date_str = $current_rubrique->getCreditExpirationWithGracePeriod();
					$grace_date = DateTime::createFromFormat("Y-m-d H:i:s", $grace_date_str, new DateTimeZone(config::$timezone));
					if($grace_date === false) {
						$msg = "rubrique credit expiration with grace period date cannot be processed, date : ".$grace_date_str." cannot be parsed";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					if($grace_date > $now) {
						//RENEW
						$to_be_renewed = true;
						$renewed_start_date = $subscription->getSubPeriodStartedDate();//KEEP
						$renewed_until_date = $now->add(new DateInterval("P1D"));//give 1 more day
					} else {
						//CANCEL
						$to_be_canceled = true;
						$cancel_date = $now;
					}
				}
			} else {
				$to_be_canceled = true;
				$cancel_date = $now;
			}
			if($to_be_renewed) {
				//RENEW
				try {
					pg_query("BEGIN");
				 	$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "refresh_renew");
				 	$subscriptionsHandler = new SubscriptionsHandler();
				 	$subscriptionsHandler->doRenewSubscriptionByUuid($subscription->getSubscriptionBillingUuid(), $renewed_start_date, $renewed_until_date);
				 	$billingsSubscriptionActionLog->setProcessingStatus('done');
				 	BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
				 	//COMMIT
				 	pg_query("COMMIT");
				 } catch (Exception $e) {
					pg_query("ROLLBACK");
				 	throw $e;
				 }
			} else if($to_be_canceled) {
				//CANCEL
				try {
					pg_query("BEGIN");
					$billingsSubscriptionActionLog = BillingsSubscriptionActionLogDAO::addBillingsSubscriptionActionLog($subscription->getId(), "refresh_cancel");
					$subscriptionsHandler = new SubscriptionsHandler();
					$subscriptionsHandler->doCancelSubscriptionByUuid($subscription->getSubscriptionBillingUuid(), $cancel_date, false);
					$billingsSubscriptionActionLog->setProcessingStatus('done');
					BillingsSubscriptionActionLogDAO::updateBillingsSubscriptionActionLogProcessingStatus($billingsSubscriptionActionLog);
					//COMMIT
					pg_query("COMMIT");
				} catch (Exception $e) {
				 	pg_query("ROLLBACK");
				 	throw $e;
				}
			}
			ScriptsConfig::getLogger()->addInfo("refreshing idipper subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid()." done successfully");
			$billingsSubscriptionActionLog = NULL;
		} catch(Exception $e) {
			$msg = "an error occurred while refreshing idipper subscription for billings_subscription_uuid=".$subscription->getSubscriptionBillingUuid().", message=".$e->getMessage();
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