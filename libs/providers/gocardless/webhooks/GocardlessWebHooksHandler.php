<?php

use GoCardlessPro\Client;
use GoCardlessPro\Core\Exception\GoCardlessProException;

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../subscriptions/GocardlessSubscriptionsHandler.php';

class GocardlessWebHooksHandler {
	
	public function __construct() {
	}
	
	public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing gocardless webHook with id=".$billingsWebHook->getId()."...");
			$notifications_as_array = json_decode($billingsWebHook->getPostData(), true);
			$events_as_array = $notifications_as_array['events'];
			foreach($events_as_array as $key => $event_as_array) {
				$this->doProcessNotification($event_as_array, $update_type, $billingsWebHook->getId());
			}
			config::getLogger()->addInfo("processing gocardless webHook with id=".$billingsWebHook->getId()." done successfully");
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing gocardless webHook with id=".$billingsWebHook->getId().", message=".$e->getMessage();
			config::getLogger()->addError("processing gocardless webHook with id=".$billingsWebHook->getId()." failed : ". $msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	private function doProcessNotification(array $notification_as_array, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing gocardless hook notification...');
		$resource_type = $notification_as_array['resource_type'];
		switch($resource_type) {
			case "subscriptions" :
				$this->doProcessSubscription($notification_as_array, $update_type, $updateId);
				break;
			case "mandates" :
				$this->doProcessMandate($notification_as_array, $update_type, $updateId);
				break;
			case "payments" :
				$this->doProcessPayment($notification_as_array, $update_type, $updateId);
				break;
			default :
				config::getLogger()->addWarning('resource type : '. $resource_type. ' is not yet implemented');
				break;
		}
		config::getLogger()->addInfo('Processing gocardless hook notification done successfully');
	}
	
	private function doProcessSubscription(array $notification_as_array, $update_type, $updateId) {
		switch($notification_as_array['action']) {
			case "created" :
				//NOW IGNORED : since subscription is created through API, just log for information
				config::getLogger()->addInfo('notification type : '.$notification_as_array['resource_type'].', action : '. $notification_as_array['action']. ' is ignored');
			break;
			default :
				config::getLogger()->addInfo('Processing gocardless hook subscription, action='.$notification_as_array['action'].'...');
				//
				$client = new Client(array(
						'access_token' => getEnv('GOCARDLESS_API_KEY'),
						'environment' => getEnv('GOCARDLESS_API_ENV')
				));
				//
				$subscription_provider_uuid = $notification_as_array['links']['subscription'];
				config::getLogger()->addInfo('Processing gocardless hook subscription, sub_uuid='.$subscription_provider_uuid);
				//
				$api_subscription = NULL;
				$api_mandate = NULL;
				$api_customer_bank_account = NULL;
				$api_customer = NULL;
				try {
					//
					config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_subscription...');
					$api_subscription = $client->subscriptions()->get($subscription_provider_uuid);
					config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_subscription done successfully');
					//
					config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_mandate...');
					$api_mandate = $client->mandates()->get($api_subscription->links->mandate);
					config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_mandate done successfully');
					//
					config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_customer_bank_account...');
					$api_customer_bank_account = $client->customerBankAccounts()->get($api_mandate->links->customer_bank_account);
					config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_customer_bank_account done successfully');
					//
					config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_customer...');
					$api_customer = $client->customers()->get($api_customer_bank_account->links->customer);
					config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_customer done successfully');
					//
				} catch (GoCardlessProException $e) {
					$msg = "a GoCardlessProException occurred while getting gocardless subscription with subscription_provider_uuid=".$subscription_provider_uuid." from api, error_code=".$e->getCode().", error_message=".$e->getMessage();
					config::getLogger()->addError("getting gocardless subscription failed : ".$msg);
					throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
				} catch (Exception $e) {
					$msg = "an unknown exception occurred while getting gocardless subscription with subscription_provider_uuid=".$subscription_provider_uuid." from api, message=".$e->getMessage();
					config::getLogger()->addError("getting gocardless subscription failed : ".$msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
				}
				//provider
				$provider = ProviderDAO::getProviderByName('gocardless');
				if($provider == NULL) {
					$msg = "provider named 'gocardless' not found";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				//plan (in metadata !!!)
				/*$plan_uuid = $api_subscription->plan->plan_code;
				if($plan_uuid == NULL) {
					$msg = "plan uuid not found";
					config::getLogger()->addError($msg);
					throw new Exception($msg);
				}
				$plan = PlanDAO::getPlanByUuid($provider->getId(), $plan_uuid);
				if($plan == NULL) {
					$msg = "plan with uuid=".$plan_uuid." not found";
					config::getLogger()->addError($msg);
					throw new Exception($msg);
				}*/
				//$account_code
				$account_code = $api_customer->id;
				if($account_code == NULL) {
					$msg = "account_code not found";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				config::getLogger()->addInfo('searching user with account_code='.$account_code.'...');
				$user = UserDAO::getUserByUserProviderUuid($provider->getId(), $account_code);
				if($user == NULL) {
					$msg = 'searching user with account_code='.$account_code.' failed, no user found';
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
				$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
				$db_subscription = $this->getDbSubscriptionByUuid($db_subscriptions, $subscription_provider_uuid);
				$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler();
				$db_subscription_before_update = NULL;
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					//ADD OR UPDATE
					if($db_subscription == NULL) {
						$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." not found for user with provider_user_uuid=".$user->getUserProviderUuid();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						//DO NOT CREATE ANYMORE : race condition when creating from API + from the webhook
						//WAS :
						//CREATE
						//$db_subscription = $gocardlessSubscriptionsHandler->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, NULL, NULL, $api_subscription, $update_type, $updateId);
					} else {
						//UPDATE
						$db_subscription_before_update = clone $db_subscription;
						$db_subscription = $gocardlessSubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, NULL, NULL, NULL, NULL, $api_subscription, $db_subscription, $update_type, $updateId);
					}
					//WHEN ? (not given by the gocardless API)
					switch($notification_as_array['action']) {
						case 'created' :
							//nothing to do
							break;
						case 'customer_approval_granted' :
							//nothing to do
							break;
						case 'customer_approval_denied' :
							//sub_expires_date = sub_canceled_date
							$db_subscription->setSubExpiresDate(new DateTime($notification_as_array['created_at']));
							$db_subscription->setSubCanceledDate(new DateTime($notification_as_array['created_at']));
							$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
							$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
							break;
						case 'payment_created' :
							//nothing to do
							break;
						case 'cancelled' :
							if($notification_as_array['details']['cause'] == 'subscription_cancelled') {
								if(isset($api_subscription->metadata->status) 
										&&
										$api_subscription->metadata->status == 'expired')
								{
									config::getLogger()->addInfo('Processing gocardless hook subscription, subscription expired, cause=expiration requested');
									//sub_expires_date
									$db_subscription->setSubExpiresDate(new DateTime($notification_as_array['created_at']));
									$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
									//STATUS IS NOT CHANGED IN updateDbSubscriptionFromApiSubscription
									///!\VERIFY STATUS BEFORE FORCING TO EXPIRED/!\
									if($api_subscription->status == 'cancelled') {
										$db_subscription->setSubStatus('expired');
										$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
									}
								} else {
									config::getLogger()->addInfo('Processing gocardless hook subscription, subscription canceled');
									//sub_canceled_date
									$db_subscription->setSubCanceledDate(new DateTime($notification_as_array['created_at']));
									$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
									//STATUS IS CHANGED IN updateDbSubscriptionFromApiSubscription
								}
							} else {
								config::getLogger()->addInfo('Processing gocardless hook subscription, subscription expired, cause='.$notification_as_array['details']['cause']);
								//sub_expires_date = sub_canceled_date
								$db_subscription->setSubExpiresDate(new DateTime($notification_as_array['created_at']));
								$db_subscription->setSubCanceledDate(new DateTime($notification_as_array['created_at']));
								$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
								$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
								//STATUS IS NOT CHANGED IN updateDbSubscriptionFromApiSubscription
								///!\VERIFY STATUS BEFORE FORCING TO EXPIRED/!\
								if($api_subscription->status == 'cancelled') {
									$db_subscription->setSubStatus('expired');
									$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
								}
							}
							break;
						default :
							$msg = "unknown action : ".$notification_as_array['action'];
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							//break;
					}
					//In order to be sure to have a date anyway
					if($db_subscription->getSubStatus() == 'canceled' && $db_subscription->getSubCanceledDate() == NULL) {
						//sub_canceled_date
						config::getLogger()->addInfo('Processing gocardless hook subscription, forcing canceled date');
						$db_subscription->setSubCanceledDate(new DateTime($notification_as_array['created_at']));
						$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);			
					}
					if($db_subscription->getSubStatus() == 'expired' && $db_subscription->getSubExpiresDate() == NULL) {
						//sub_expires_date = sub_canceled_date
						config::getLogger()->addInfo('Processing gocardless hook subscription, forcing expired date');
						$db_subscription->setSubExpiresDate(new DateTime($notification_as_array['created_at']));
						$db_subscription->setSubCanceledDate(new DateTime($notification_as_array['created_at']));
						$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
						$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);				
					}
					//COMMIT
					pg_query("COMMIT");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
				$gocardlessSubscriptionsHandler->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
				//
				config::getLogger()->addInfo('Processing gocardless hook subscription, action='.$notification_as_array['action'].' done successfully');
				break;
		}
	}
	
	private function getDbSubscriptionByUuid(array $db_subscriptions, $subUuid) {
		foreach ($db_subscriptions as $db_subscription) {
			if($db_subscription->getSubUid() == $subUuid) {
				return($db_subscription);
			}
		}
	}
	
	private function doProcessMandate(array $notification_as_array, $update_type, $updateId) {
		switch($notification_as_array['action']) {
			case 'cancelled' :
			case 'failed' :
			case 'expired' :
				//TODO
				break;
			default :
				config::getLogger()->addInfo('notification type : '.$notification_as_array['resource_type'].', action : '. $notification_as_array['action']. ' is ignored');
				break;
		}
	}
	
	private function doProcessPayment(array $notification_as_array, $update_type, $updateId) {
		switch($notification_as_array['action']) {
			case 'cancelled' :
			case 'failed' :
				config::getLogger()->addInfo('Processing gocardless hook payment, action='.$notification_as_array['action'].'...');
				$client = new Client(array(
						'access_token' => getEnv('GOCARDLESS_API_KEY'),
						'environment' => getEnv('GOCARDLESS_API_ENV')
				));
				$api_payment = NULL;
				$api_subscription = NULL;
				try {
					//
					config::getLogger()->addInfo('Processing gocardless hook payment, getting api_payment...');
					$api_payment = $client->payments()->get($notification_as_array['links']['payment']);
					config::getLogger()->addInfo('Processing gocardless hook payment, getting api_payment done successfully');
					//
					config::getLogger()->addInfo('Processing gocardless hook payment, getting api_subscription...');
					$api_subscription = $client->subscriptions()->get($api_payment->links->subscription);
					config::getLogger()->addInfo('Processing gocardless hook payment, getting api_subscription done successfully');
					//
				} catch (GoCardlessProException $e) {
					$msg = "a GoCardlessProException occurred while getting gocardless informations from api, error_code=".$e->getCode().", error_message=".$e->getMessage();
					config::getLogger()->addError("getting gocardless informations failed : ".$msg);
					throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
				} catch (Exception $e) {
					$msg = "an unknown exception occurred while getting gocardless informations from api, message=".$e->getMessage();
					config::getLogger()->addError("getting gocardless informations failed : ".$msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
				}
				//verify status
				if($api_payment->status == 'cancelled' || $api_payment->status == 'failed') {
					//provider
					$provider = ProviderDAO::getProviderByName('gocardless');
					if($provider == NULL) {
						$msg = "provider named 'gocardless' not found";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($provider->getId(), $api_subscription->id);
					if($db_subscription == NULL) {
						$msg = "subscription with subscription_provider_uuid=".$api_subscription->id." not found";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					//
					$sub_params = ['params' =>
							[
									'metadata' => ['status' => 'expired']
							]
					];
					$subscription = $client->subscriptions()->update($api_subscription->id, $sub_params);
					//
					$subscriptionsHandler = new SubscriptionsHandler();
					$cancel_date = new DateTime();
					$subscriptionsHandler->doCancelSubscriptionByUuid($db_subscription->getSubscriptionBillingUuid(), $cancel_date, false);
				}
				config::getLogger()->addInfo('Processing gocardless hook payment, action='.$notification_as_array['action'].' done successfully');
				break;
			default :
				config::getLogger()->addInfo('notification type : '.$notification_as_array['resource_type'].', action : '. $notification_as_array['action']. ' is ignored');
				break;
		}
	}
	
}

?>