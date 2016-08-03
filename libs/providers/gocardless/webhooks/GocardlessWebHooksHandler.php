<?php

use GoCardlessPro\Client;
use GoCardlessPro\Core\Exception\GoCardlessProException;

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../subscriptions/GocardlessSubscriptionsHandler.php';
require_once __DIR__ . '/../transactions/GocardlessTransactionsHandler.php';
		
class GocardlessWebHooksHandler {
	
	public function __construct() {
	}
	
	public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing gocardless webHook with id=".$billingsWebHook->getId()."...");
			$notifications_as_array = json_decode($billingsWebHook->getPostData(), true);
			$events_as_array = $notifications_as_array['events'];
			$exception = NULL;
			foreach($events_as_array as $key => $event_as_array) {
				try {
					$this->doProcessNotification($event_as_array, $update_type, $billingsWebHook->getId());
				} catch(Exception $e) {
					$msg = "an unknown exception occurred while processing gocardless notifications with webHook id=".$billingsWebHook->getId().", message=".$e->getMessage();
					config::getLogger()->addError("processing gocardless notifications with webHook id=".$billingsWebHook->getId()." failed : ". $msg);
					if($exception == NULL) {
						$exception = $e;//only throw 1st one, later, so continue anyway. For others => see logs
					}
				}
			}
			if(isset($exception)) {
				throw $exception;
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
			case "refunds" :
				$this->doProcessRefund($notification_as_array, $update_type, $updateId);
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
							//? HOW TO CHECK ?
							$subscriptionsHandler = new SubscriptionsHandler();
							$expires_date = new DateTime($notification_as_array['created_at']);
							$subscriptionsHandler->doExpireSubscriptionByUuid($db_subscription->getSubscriptionBillingUuid(), $expires_date, false);
							break;
						case 'payment_created' :
							//nothing to do
							break;
						case 'cancelled' :
							if($api_subscription->status == 'cancelled') {//CHECK
								if(isset($api_subscription->metadata->status)
										&&
									$api_subscription->metadata->status == 'expired')
								{
									$subscriptionsHandler = new SubscriptionsHandler();
									$expires_date = new DateTime($notification_as_array['created_at']);
									$subscriptionsHandler->doExpireSubscriptionByUuid($db_subscription->getSubscriptionBillingUuid(), $expires_date, false);
								} else {
									$subscriptionsHandler = new SubscriptionsHandler();
									$cancel_date = new DateTime($notification_as_array['created_at']);
									$subscriptionsHandler->doCancelSubscriptionByUuid($db_subscription->getSubscriptionBillingUuid(), $cancel_date, false);
								}
							}
							break;
						default :
							$msg = "unknown action : ".$notification_as_array['action'];
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							//break;
					}
					//COMMIT
					pg_query("COMMIT");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
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
		return(NULL);
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
		$exception = NULL;
		try {
			$this->doProcessPaymentForCheckingValidity($notification_as_array, $update_type, $updateId);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing payment for checking validity, message=".$e->getMessage();
			config::getLogger()->addError($msg);
			if($exception == NULL) { $exception = $e; }
		}
		try {
			$this->doProcessPaymentForBackup($notification_as_array, $update_type, $updateId);
		} catch(Exception $e)
		{
			$msg = "an unknown exception occurred while processing payment for backup, message=".$e->getMessage();
			config::getLogger()->addError($msg);
			if($exception == NULL) { $exception = $e; }
		}
		if(isset($exception)) {
			throw $exception;
		}
	}
	
	private function doProcessPaymentForCheckingValidity(array $notification_as_array, $update_type, $updateId) {
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
					//GOCARDLESS DO NOT KEEP METADATA ALREADY SET
					$subscriptionsHandler = new SubscriptionsHandler();
					$expires_date = new DateTime($notification_as_array['created_at']);
					$subscriptionsHandler->doExpireSubscriptionByUuid($db_subscription->getSubscriptionBillingUuid(), $expires_date, false);
				}
				config::getLogger()->addInfo('Processing gocardless hook payment, action='.$notification_as_array['action'].' done successfully');
				break;
			default :
				config::getLogger()->addInfo('notification type : '.$notification_as_array['resource_type'].', action : '. $notification_as_array['action']. ' is ignored');
				break;
		}
	}
	
	private function doProcessPaymentForBackup(array $notification_as_array, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing gocardless hook payment for backup, action='.$notification_as_array['action'].'...');
		//
		$client = new Client(array(
				'access_token' => getEnv('GOCARDLESS_API_KEY'),
				'environment' => getEnv('GOCARDLESS_API_ENV')
		));
		//
		$payment_provider_uuid = $notification_as_array['links']['payment'];
		config::getLogger()->addInfo('Processing gocardless hook payment for backup, payment_provider_uuid='.payment_provider_uuid);
		$api_payment = NULL;
		$api_customer = NULL;
		try {
			//
			config::getLogger()->addInfo('Processing gocardless hook payment for backup, getting api_payment...');
			$api_payment = $client->payments()->get($payment_provider_uuid);
			config::getLogger()->addInfo('Processing gocardless hook payment for backup, getting api_payment done successfully');
			//
			config::getLogger()->addInfo('Processing gocardless hook payment for backup, getting api_customer...');
			$api_customer = $client->customers()->get($api_payment->links->customer);
			config::getLogger()->addInfo('Processing gocardless hook payment for backup, getting api_customer done successfully');
		} catch (GoCardlessProException $e) {
			$msg = "a GoCardlessProException occurred while getting gocardless informations from api, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("getting gocardless informations failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch (Exception $e) {
			$msg = "an unknown exception occurred while getting gocardless informations from api, message=".$e->getMessage();
			config::getLogger()->addError("getting gocardless informations failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		//provider
		$provider = ProviderDAO::getProviderByName('gocardless');
		if($provider == NULL) {
			$msg = "provider named 'gocardless' not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$user = UserDAO::getUserByUserProviderUuid($provider->getId(), $api_customer->id);
		if($user == NULL) {
			$msg = 'searching user with customer_provider_uuid='.$api_customer->id.' failed, no user found';
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
		$gocardlessTransactionsHandler = new GocardlessTransactionsHandler();
		$gocardlessTransactionsHandler->createOrUpdateChargeFromProvider($user, $userOpts, $api_customer, $api_payment);
		config::getLogger()->addInfo('Processing gocardless hook payment for backup, action='.$notification_as_array['action'].' done successfully');
	}
	
	private function doProcessRefund(array $notification_as_array, $update_type, $updateId) {
		$this->doProcessRefundForBackup($notification_as_array, $update_type, $updateId);
	}
	
	private function doProcessRefundForBackup(array $notification_as_array, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing gocardless hook refund for backup, action='.$notification_as_array['action'].'...');
		//
		$client = new Client(array(
				'access_token' => getEnv('GOCARDLESS_API_KEY'),
				'environment' => getEnv('GOCARDLESS_API_ENV')
		));
		//
		$refund_provider_uuid = $notification_as_array['links']['refund'];
		config::getLogger()->addInfo('Processing gocardless hook refund for backup, refund_provider_uuid='.refund_provider_uuid);
		$api_refund = NULL;
		$api_payment = NULL;
		$api_customer = NULL;
		try {
			//
			config::getLogger()->addInfo('Processing gocardless hook refund for backup, getting api_refund...');
			$api_refund = $client->refunds()->get($refund_provider_uuid);
			config::getLogger()->addInfo('Processing gocardless hook refund for backup, getting api_refund done successfully');			
			//
			config::getLogger()->addInfo('Processing gocardless hook refund for backup, getting api_payment...');
			$api_payment = $client->payments()->get($api_refund->links->payment);
			config::getLogger()->addInfo('Processing gocardless hook refund for backup, getting api_payment done successfully');
			//
			config::getLogger()->addInfo('Processing gocardless hook refund for backup, getting api_customer...');
			$api_customer = $client->customers()->get($api_payment->links->customer);
			config::getLogger()->addInfo('Processing gocardless hook refund for backup, getting api_customer done successfully');			
		} catch (GoCardlessProException $e) {
			$msg = "a GoCardlessProException occurred while getting gocardless informations from api, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("getting gocardless informations failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch (Exception $e) {
			$msg = "an unknown exception occurred while getting gocardless informations from api, message=".$e->getMessage();
			config::getLogger()->addError("getting gocardless informations failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		//provider
		$provider = ProviderDAO::getProviderByName('gocardless');
		if($provider == NULL) {
			$msg = "provider named 'gocardless' not found";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$user = UserDAO::getUserByUserProviderUuid($provider->getId(), $api_customer->id);
		if($user == NULL) {
			$msg = 'searching user with customer_provider_uuid='.$api_customer->id.' failed, no user found';
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
		$gocardlessTransactionsHandler = new GocardlessTransactionsHandler();
		$gocardlessTransactionsHandler->createOrUpdateChargeFromProvider($user, $userOpts, $api_customer, $api_payment);
		config::getLogger()->addInfo('Processing gocardless hook refund for backup, action='.$notification_as_array['action'].' done successfully');
	}
	
}

?>