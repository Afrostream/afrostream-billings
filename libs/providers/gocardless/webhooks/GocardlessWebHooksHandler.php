<?php

use GoCardlessPro\Client;
use GoCardlessPro\Resources\Subscription;
use GoCardlessPro\Core\Exception\GoCardlessProException;
use GoCardlessPro\Core\Paginator;

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
			foreach($notifications_as_array as $notification_as_array) {
				//TODO : don't work yet
				$this->doProcessNotification($notification_as_array, $update_type, $billingsWebHook->getId());
			}
			config::getLogger()->addInfo("processing gocardless webHook with id=".$billingsWebHook->getId()." done successully");
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing gocardless webHook with id=".$billingsWebHook->getId().", message=".$e->getMessage();
			config::getLogger()->addError("processing gocardless webHook with id=".$billingsWebHook->getId()." failed : ". $msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	private function doProcessNotification(array $notification_as_array, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing gocardless hook notificiation...');
		
		config::getLogger()->addInfo("id=".$notification_as_array['id']);
		config::getLogger()->addInfo("action=".$notification_as_array['action']);
		config::getLogger()->addInfo("resource_type=".$notification_as_array['resource_type']);
		
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
		config::getLogger()->addInfo('Processing gocardless hook subscription, update_type='.$notification->type.'...');
		//
		$client = new Client(array(
				'access_token' => getEnv('GOCARDLESS_API_KEY'),
				'environment' => getEnv('GOCARDLESS_API_ENV')
		));
		//
		$subscription_uuid = $notification_as_array['id'];
		//
		try {
			//
			$api_subscription = $client->subscriptions()->get($subscription_uuid);
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
		$plan_uuid = $api_subscription->plan->plan_code;
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
		}
		//$account_code
		$account_code = $api_subscription->account->get()->account_code;
		if($account_code == NULL) {
			$msg = "account_code not found";
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		config::getLogger()->addInfo('searching user with account_code='.$account_code.'...');
		$user = UserDAO::getUserByAccountCode($account_code);
		if($user == NULL) {
			$msg = 'searching user with account_code='.$account_code.' failed, no user found';
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		//
		$subscription = SubscriptionDAO::getSubscriptionBySubUuid($provider->getId(), $subscription_uuid);
		$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler();
		if($subscription == NULL) {
			//CREATE
			$subscription = $gocardlessSubscriptionsHandler->createDbSubscriptionFromApiSubscription($user, $provider, $plan, $api_subscription, $update_type, $updateId);
		} else {
			//UPDATE
			$subscription = $gocardlessSubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, $provider, $plan, $api_subscription, $subscription, $update_type, $updateId);
		}
		//
		config::getLogger()->addInfo('Processing gocardless hook subscription, update_type='.$notification->type.' done successfully');
	}
	
	private static function getNodeByName(SimpleXMLElement $node, $name) {
		$out = null;
		foreach ($node->children() as $children) {
			if($children->getName() == $name) {
				$out = $children;
				break;
			}
		}
		return($out);
	}
}

?>