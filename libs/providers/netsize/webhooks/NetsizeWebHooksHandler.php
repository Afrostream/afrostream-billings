<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';

class NetsizeWebHooksHandler {
	
	public function __construct() {
	}
	
	public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing netsize webHook with id=".$billingsWebHook->getId()."...");
			$xml = simplexml_load_string($billingsWebHook->getPostData());
			if($xml === false) {
				config::getLogger()->addError("XML cannot be loaded, post=".(string) $billingsWebHook->getPostData());
				throw new Exception("XML cannot be loaded, post=".(string) $billingsWebHook->getPostData());
			}
			$exception = NULL;
			foreach($xml->children() as $notificationNode) {
				try {
					$this->doProcessNotification($notificationNode, $update_type, $billingsWebHook->getId());
				} catch(Exception $e) {
					$msg = "an unknown exception occurred while processing netsize notifications with webHook id=".$billingsWebHook->getId().", message=".$e->getMessage();
					config::getLogger()->addError("processing netsize notifications with webHook id=".$billingsWebHook->getId()." failed : ". $msg);
					if($exception == NULL) {
						$exception = $e;//only throw 1st one, later, so continue anyway. For others => see logs
					}
				}
			}
			if(isset($exception)) {
				throw $exception;
			}
			config::getLogger()->addInfo("processing netsize webHook with id=".$billingsWebHook->getId()." done successfully");
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing netsize webHook with id=".$billingsWebHook->getId().", message=".$e->getMessage();
			config::getLogger()->addError("processing netsize webHook with id=".$billingsWebHook->getId()." failed : ". $msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	private function doProcessNotification(SimpleXMLElement $notificationNode, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing netsize hook notification...');
		$notification_name = $notificationNode->getName();
		switch($notification_name) {
			case "transaction-changed" :
				$this->doProcessTransactionChanged($notificationNode, $update_type, $updateId);
				break;
			case "subscription-renewed" :
				$this->doProcessSubscriptionRenewed($notificationNode, $update_type, $updateId);
				break;
			default :
				config::getLogger()->addWarning('notification_name : '. $notification_name. ' is not yet implemented');
				break;
		}
		config::getLogger()->addInfo('Processing netsize hook notification done successfully');
	}

	private function doProcessTransactionChanged(SimpleXMLElement $notificationNode, $update_type, $updateId) {
		//check Attribute : subscription-transaction-id. If NOT HERE : IGNORE
		$subscription_provider_uuid = self::getXmlNodeAttributeValue($notificationNode, "subscription-transaction-id");
		if($subscription_provider_uuid == NULL) {
			//ignore
		}
		//TODO
	}
	
	private function doProcessSubscriptionRenewed(SimpleXMLElement $notificationNode, $update_type, $updateId) {
		//check Attribute : subscription-transaction-id. If NOT HERE : FAIL
		$subscription_provider_uuid = self::getXmlNodeAttributeValue($notificationNode, "subscription-transaction-id");
		if($subscription_provider_uuid == NULL) {
			//exception
		}
		//TODO
	}
	
	private static function getXmlNodeAttributeValue(SimpleXMLElement $node, $attributeName) {
		foreach($node->attributes() as $key => $value) {
			if($key == $attributeName) {
				return  $value;
			}
		}
		return NULL;
	}
	
	/*
	
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
	}*/
	
}

?>