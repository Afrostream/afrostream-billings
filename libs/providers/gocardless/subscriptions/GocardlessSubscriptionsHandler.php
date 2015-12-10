<?php

use GoCardlessPro\Client;
use GoCardlessPro\Resources\Subscription;
use GoCardlessPro\Core\Exception\GoCardlessProException;

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/BillingsException.php';

class GocardlessSubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, Plan $plan, PlanOpts $planOpts, BillingInfoOpts $billingInfoOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("gocardless subscription creation...");
			/** in gocardless : user subscription is pre-created **/
			//pre-requisite
			if(!isset($billingInfoOpts->getOpts()['subscription_uuid'])) {
				$msg = "field 'subscription_uuid' was not provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			/** LATER **/
			/**
			//<-- FOR TESTING PURPOSE
			$planOpts->setOpt('gocardless_amount', 10);
			//FOR TESTING PURPOSE -->
			//pre-requisite
			if(!isset($planOpts->getOpts()['gocardless_amount'])) {
				$msg = "field 'gocardless_amount' was not provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			$client = new Client(array(
				'access_token' => getEnv('GOCARDLESS_API_KEY'),
				'environment' => getEnv('GOCARDLESS_API_ENV')
			));
			//Create a Bank Account
			config::getLogger()->addInfo("gocardless subscription creation... bank account creation...");
			$bank_account = $client->customerBankAccounts()->create(
					['params' =>
					[
							'iban' => 'FR2420041010111224481S03274',
							'account_holder_name' => 'COELHO NELSON',//TODO
							'country_code' => 'FR',//TODO
							'links' => ['customer' => $user->getUserProviderUuid()]
					]
					]);
			config::getLogger()->addInfo("gocardless subscription creation... bank account creation done successfully, bank_acccount_id=".$bank_account->id);
			//Create a Mandate
			config::getLogger()->addInfo("gocardless subscription creation... mandate creation...");
			$mandate = $client->mandates()->create(
					['params' =>
					[
							'links' => ['customer_bank_account' => $bank_account->id],
					]
					]);
			config::getLogger()->addInfo("gocardless subscription creation... mandate creation done successfully, mandate_id=".$mandate->id);
			//Create a Subscription
			config::getLogger()->addInfo("gocardless subscription creation... subscription creation...");
			$subscription = $client->subscriptions()->create(
					['params' => 
							[
							'amount' => $planOpts->getOpts()['gocardless_amount'],
							'currency' => 'EUR',//TODO 
							'name' => $plan->getPlanUuid(),
						    'interval_unit' => 'monthly', //TODO
						    'day_of_month' => 1,//TODO
						    'links' => ['mandate' => $mandate->id]
							]
					]);
			config::getLogger()->addInfo("gocardless subscription creation... subscription creation done successfully, subscription_id=".$subscription->id);
			$sub_uuid = $subscription->id;
			**/
			//Verify that subscription belongs to the current customer
			//
			$client = new Client(array(
					'access_token' => getEnv('GOCARDLESS_API_KEY'),
					'environment' => getEnv('GOCARDLESS_API_ENV')
			));
			//
			$paginator = $client->subscriptions()->all(
					['params' =>
						[
						'customer' => $user->getUserProviderUuid()
						]
					]);
			//
			$found = false;
			foreach($paginator as $sub_entry) {
				if($sub_entry->id == $billingInfoOpts->getOpts()['subscription_uuid']) {
					$found = true; 
					break;
				}
			}
			if(!$found) {
				$msg = "subscription not found for the current customer";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$sub_uuid = $billingInfoOpts->getOpts()['subscription_uuid'];
			config::getLogger()->addInfo("gocardless subscription creation done successfully, gocardless_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a gocardless subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless subscription creation failed : ".$msg);
			throw $e;
		} catch (GoCardlessProException $e) {
			$msg = "a GoCardlessProException occurred while creating a gocardless subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a gocardless subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($sub_uuid);
	}
	
	public function doUpdateUserSubscriptions(User $user) {
		//
		Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
		Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
		//
		//provider
		$provider = ProviderDAO::getProviderByName('gocardless');
		if($provider == NULL) {
			$msg = "provider named 'gocardless' not found";
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		try {
			$api_subscriptions = Recurly_SubscriptionList::getForAccount($user->getAccountCode());
		} catch (Recurly_NotFoundError $e) {
			$msg = "an account not found exception occurred while getting subscriptions for account_code=".$user->getAccountCode().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting subscriptions for account_code=".$user->getAccountCode().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionByUserId($user->getId());
		//ADD OR UPDATE
		foreach ($api_subscriptions as $api_subscription) {
			//plan
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
			$db_subscription = self::getDbSubscriptionByUuid($db_subscriptions, $api_subscription->uuid);
			if($db_subscription == NULL) {
				//CREATE
				$db_subscription = self::createDbSubscriptionFromApiSubscription($user, $provider, $plan, $api_subscription, 'api', 0);
			} else {
				//UPDATE
				$db_subscription = self::updateDbSubscriptionFromApiSubscription($user, $provider, $plan, $api_subscription, $db_subscription, 'api', 0);
			}
		}
		//DELETE UNUSED SUBSCRIPTIONS (DELETED FROM THIRD PARTY)
		foreach ($db_subscriptions as $db_subscription) {
			$api_subscription = self::getApiSubscriptionByUuid($api_subscriptions, $db_subscription->getSubUid());
			if($api_subscription == NULL) {
				BillingsSubscriptionDAO::deleteBillingsSubscriptionById($db_subscription->getId());
			}
		}
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, Plan $plan, PlanOpts $planOpts, $sub_uuid, $update_type, $updateId) {
		//
		$client = new Client(array(
			'access_token' => getEnv('GOCARDLESS_API_KEY'),
			'environment' => getEnv('GOCARDLESS_API_ENV')
		));
		//
		$api_subscription = $client->subscriptions()->get($sub_uuid);
		//
		echo $api_subscription;
		//
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $plan, $planOpts, $api_subscription, $update_type, $updateId));
	}
	
	private function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, Plan $plan, PlanOpts $planOpts, Subscription $api_subscription, $update_type, $updateId) {
		//CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setProviderId($provider->getId());
		$db_subscription->setUserId($user->getId());
		$db_subscription->setPlanId($plan->getId());
		$db_subscription->setSubUid($api_subscription->id);
		switch ($api_subscription->status) {
			case 'active' :
				$db_subscription->setSubStatus('active');
				break;
			case 'cancelled' :
				$db_subscription->setSubStatus('canceled');
				break;
			case 'pending_customer_approval' :
				$db_subscription->setSubStatus('future');
				break;
			case 'finished' :
				$db_subscription->setSubStatus('expired');
				break;
			case 'customer_approval_denied' :
				$db_subscription->setSubStatus('expired');
				break;
			default :
				$msg = "unknown subscription status : ".$api_subscription->status;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		
		$db_subscription->setSubActivatedDate(new DateTime($api_subscription->created_at));
		//
		$db_subscription->setSubCanceledDate(NULL);//TODO
		$db_subscription->setSubExpiresDate(NULL);//TODO
		//To be calculated from billings api
		$db_subscription->setSubPeriodStartedDate(NULL);
		//To be calculated from billings api
		$db_subscription->setSubPeriodEndsDate(NULL);
		//The information is in the PLAN
		/*switch ($api_subscription->collection_mode) {
			case 'automatic' :
				$db_subscription->setSubCollectionMode('automatic');
				break;
			case 'manual' :
				$db_subscription->setSubCollectionMode('manual');
				break;
			default :
				$db_subscription->setSubCollectionMode('manual');//it is the default says recurly
				break;
		}*/
		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription->setDeleted('false');
		//
		$db_subscription = BillingsSubscriptionDAO::addBillingsSubscription($db_subscription);
		return($db_subscription);
	}
	
	public function updateDbSubscriptionFromApiSubscription($user, $provider, $plan, Recurly_Subscription $api_subscription, Subscription $db_subscription, $update_type, $updateId) {
		//UPDATE
		//$db_subscription->setProviderId($provider->getId());//STATIC
		//$db_subscription->setUserId($user->getId());//STATIC
		$db_subscription->setPlanId($plan->getId());
		//$db_subscription->setSubUid($subscription_uuid);//STATIC
		switch ($api_subscription->state) {
			case 'active' :
				$db_subscription->setSubStatus('active');
				break;
			case 'canceled' :
				$db_subscription->setSubStatus('canceled');
				break;
			case 'future' :
				$db_subscription->setSubStatus('future');
				break;
			case 'expired' :
				$db_subscription->setSubStatus('expired');
				break;
			default :
				$msg = "unknown subscription state : ".$api_subscription->state;
				config::getLogger()->addError($msg);
				throw new Exception($msg);
				//break;
		}
		$db_subscription->setSubActivatedDate($api_subscription->activated_at);
		$db_subscription->setSubCanceledDate($api_subscription->canceled_at);
		$db_subscription->setSubExpiresDate($api_subscription->expires_at);
		$db_subscription->setSubPeriodStartedDate($api_subscription->current_period_started_at);
		$db_subscription->setSubPeriodEndsDate($api_subscription->current_period_ends_at);
		switch ($api_subscription->collection_mode) {
			case 'automatic' :
				$db_subscription->setSubCollectionMode('automatic');
				break;
			case 'manual' :
				$db_subscription->setSubCollectionMode('manual');
				break;
			default :
				$db_subscription->setSubCollectionMode('manual');//it is the default says recurly
				break;
		}
		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		//$db_subscription->setDeleted('false');//STATIC
		//
		$db_subscription = BillingsSubscriptionDAO::updateBillingsSubscription($db_subscription);
		return($db_subscription);
	}
	
	private function getDbSubscriptionByUuid(array $db_subscriptions, $subUuid) {
		foreach ($db_subscriptions as $db_subscription) {
			if($db_subscription->getSubUid() == $subUuid) {
				return($db_subscription);
			}
		}
	}
	
	private function getApiSubscriptionByUuid(Recurly_SubscriptionList $api_subscriptions, $subUuid) {
		foreach ($api_subscriptions as $api_subscription) {
			if($api_subscription->uuid == $subUuid) {
				return($api_subscription);
			}
		}
	}
	
	public function doFillSubscription(BillingsSubscription $subscription) {
		//find period : monthly / yearly / what about : one shot ?
		$activated_date = $subscription->getSubActivatedDate();
		$activated_day_of_month = $activated_date->format('w');
		echo "day=".$activated_day_of_month;
		//To be calculated from billings api
		$db_subscription->setSubPeriodStartedDate(NULL);
		//To be calculated from billings api
		$db_subscription->setSubPeriodEndsDate(NULL);
	}
}

?>