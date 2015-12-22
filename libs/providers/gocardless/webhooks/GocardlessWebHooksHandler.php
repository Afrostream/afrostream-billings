<?php

use GoCardlessPro\Client;
use GoCardlessPro\Core\Exception\GoCardlessProException;

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/providers/gocardless/subscriptions/GocardlessSubscriptionsHandler.php';

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
			default :
				config::getLogger()->addWarning('resource type : '. $resource_type. ' is not yet implemented');
				break;
		}
		config::getLogger()->addInfo('Processing gocardless hook notification done successfully');
	}
	
	private function doProcessSubscription(array $notification_as_array, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing gocardless hook subscription, action='.$notification_as_array['action'].'...');
		//
		$client = new Client(array(
				'access_token' => getEnv('GOCARDLESS_API_KEY'),
				'environment' => getEnv('GOCARDLESS_API_ENV')
		));
		//
		$subscription_uuid = $notification_as_array['links']['subscription'];
		config::getLogger()->addInfo('Processing gocardless hook subscription, sub_uuid='.$subscription_uuid);
		//
		try {
			//
			config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_subscription...');
			$api_subscription = $client->subscriptions()->get($subscription_uuid);
			config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_subscription done successfully');
			config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_mandate...');
			$api_mandate = $client->mandates()->get($api_subscription->links->mandate);
			config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_mandate done successfully');
			config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_customer_bank_account...');
			$api_customer_bank_account = $client->customerBankAccounts()->get($api_mandate->links->customer_bank_account);
			config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_customer_bank_account done successfully');
			config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_customer...');
			$api_customer = $client->customers()->get($api_customer_bank_account->links->customer);
			config::getLogger()->addInfo('Processing gocardless hook subscription, getting api_customer done sucessfully');
			//
		} catch (GoCardlessProException $e) {
			$msg = "a GoCardlessProException occurred while getting gocardless subscription with uuid=".$subscription_uuid." from api, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("getting gocardless subscription failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch (Exception $e) {
			$msg = "an unknown exception occurred while getting gocardless subscription with uuid=".$subscription_uuid." from api, message=".$e->getMessage();
			config::getLogger()->addError("getting gocardless subscription failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
			
		}
		//provider
		$provider = ProviderDAO::getProviderByName('gocardless');
		if($provider == NULL) {
			$msg = "provider named 'gocardless' not found";
			config::getLogger()->addError($msg);
			throw new Exception($msg);
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
			throw new Exception($msg);
		}
		config::getLogger()->addInfo('searching user with account_code='.$account_code.'...');
		$user = UserDAO::getUserByUserProviderUuid($provider->getId(), $account_code);
		if($user == NULL) {
			$msg = 'searching user with account_code='.$account_code.' failed, no user found';
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		$db_subscription = $this->getDbSubscriptionByUuid($db_subscriptions, $subscription_uuid);
		$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler();
		//ADD OR UPDATE
		if($db_subscription == NULL) {
			//CREATE
			$db_subscription = $gocardlessSubscriptionsHandler->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, NULL, NULL, $api_subscription, 'api', 0);
		} else {
			//UPDATE
			$db_subscription = $gocardlessSubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, NULL, NULL, $api_subscription, $db_subscription, 'api', 0);
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
				//sub_expires_date
				$db_subscription->setSubExpiresDate(new DateTime($notification_as_array['created_at']));
				$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
				break;
			case 'payment_created' :
				//nothing to do
				break;
			case 'cancelled' :
				//sub_canceled_date
				$db_subscription->setSubCanceledDate(new DateTime($notification_as_array['created_at']));
				$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
				break;
			default :
				$msg = "unknown action : ".$notification_as_array['action'];
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		//
		config::getLogger()->addInfo('Processing gocardless hook subscription, action='.$notification_as_array['action'].' done successfully');
	}
	
	private function getDbSubscriptionByUuid(array $db_subscriptions, $subUuid) {
		foreach ($db_subscriptions as $db_subscription) {
			if($db_subscription->getSubUid() == $subUuid) {
				return($db_subscription);
			}
		}
	}
	
}

?>