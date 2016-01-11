<?php

use GoCardlessPro\Client;
use GoCardlessPro\Resources\Subscription;
use GoCardlessPro\Core\Exception\GoCardlessProException;
use GoCardlessPro\Core\Paginator;

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/BillingsException.php';
require_once __DIR__ . '/../../../../libs/utils/DateRange.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';
		
class GocardlessSubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, Plan $plan, PlanOpts $planOpts, $subscription_provider_uuid, BillingInfoOpts $billingInfoOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("gocardless subscription creation...");
			/** in gocardless : user subscription is pre-created **/
			//pre-requisite
			if(!isset($subscription_provider_uuid)) {
				$msg = "field 'subscription_provider_uuid' was not provided";
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
				$msg = "subscription not found for the current user";
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
	
	public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
		config::getLogger()->addInfo("gocardless dbsubscriptions update for userid=".$user->getId()."...");
		//
		$client = new Client(array(
			'access_token' => getEnv('GOCARDLESS_API_KEY'),
			'environment' => getEnv('GOCARDLESS_API_ENV')
		));
		//
		$provider = ProviderDAO::getProviderById($user->getProviderId());
		//
		if($provider == NULL) {
			$msg = "unknown provider id : ".$user->getProviderId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		try {
			//
			$api_subscriptions = $client->subscriptions()->all(
					['params' =>
							[
									'customer' => $user->getUserProviderUuid()
							]
					]);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting gocardless subscriptions for user_provider_uuid=".$user->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless subscription creation failed : ".$msg);
			throw $e;
		} catch (GoCardlessProException $e) {
			$msg = "a GoCardlessProException occurred while getting gocardless subscriptions for user_provider_uuid=".$user->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting gocardless subscriptions for user_provider_uuid=".$user->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		//ADD OR UPDATE
		foreach ($api_subscriptions as $api_subscription) {
			//plan
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
			$db_subscription = $this->getDbSubscriptionByUuid($db_subscriptions, $api_subscription->id);
			if($db_subscription == NULL) {
				//CREATE
				$db_subscription = $this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, NULL, NULL, $api_subscription, 'api', 0);
			} else {
				//UPDATE
				$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, NULL, NULL, $api_subscription, $db_subscription, 'api', 0);
			}
		}
		//DELETE UNUSED SUBSCRIPTIONS (DELETED FROM THIRD PARTY)
		foreach ($db_subscriptions as $db_subscription) {
			$api_subscription = $this->getApiSubscriptionByUuid($api_subscriptions, $db_subscription->getSubUid());
			if($api_subscription == NULL) {
				BillingsSubscriptionDAO::deleteBillingsSubscriptionById($db_subscription->getId());
			}
		}
		config::getLogger()->addInfo("gocardless dbsubscriptions update for userid=".$user->getId()." done successfully");
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, Plan $plan = NULL , PlanOpts $planOpts = NULL, $sub_uuid, $update_type, $updateId) {
		//
		$client = new Client(array(
			'access_token' => getEnv('GOCARDLESS_API_KEY'),
			'environment' => getEnv('GOCARDLESS_API_ENV')
		));
		//
		$api_subscription = $client->subscriptions()->get($sub_uuid);
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $plan, $planOpts, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, Plan $plan = NULL, PlanOpts $planOpts = NULL, Subscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("gocardless dbsubscription creation for userid=".$user->getId().", gocardless_subscription_uuid=".$api_subscription->id."...");
		if($plan == NULL) {
			if(!isset($api_subscription->metadata->internal_plan_uuid)) {
				$msg = "metadata 'internal_plan_uuid' is not field in gocardless subscription with gocardless_subscription_uuid=".$api_subscription->id;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$internal_plan_uuid = $api_subscription->metadata->internal_plan_uuid;
			
			$internal_plan = InternalPlanDAO::getInternalPlanByUuid($internal_plan_uuid);
			
			if($internal_plan == NULL) {
				$msg = "unknown internal_plan_uuid : ".$internal_plan_uuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$plan_id = InternalPlanLinksDAO::getProviderPlanIdFromInternalPlanId($internal_plan->getId(), $provider->getId());
			if($plan_id == NULL) {
				$msg = "unknown plan : ".$internal_plan_uuid." for provider : ".$provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$plan = PlanDAO::getPlanById($plan_id);
			if($plan == NULL) {
				$msg = "unknown plan with id : ".$plan_id;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
		}
		if($planOpts == NULL) {
			$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
		}
		//CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid(guid());
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
		config::getLogger()->addInfo("gocardless dbsubscription creation for userid=".$user->getId().", gocardless_subscription_uuid=".$api_subscription->id." done successfully, id=".$db_subscription->getId());
		return($db_subscription);
	}
	
	public function updateDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, Plan $plan = NULL, PlanOpts $planOpts = NULL, Subscription $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("gocardless dbsubscription update for userid=".$user->getId().", gocardless_subscription_uuid=".$api_subscription->id.", id=".$db_subscription->getId()."...");
		if($plan == NULL) {
			if(!isset($api_subscription->metadata->internal_plan_uuid)) {
				$msg = "metadata 'internal_plan_uuid' is not field in gocardless subscription with gocardless_subscription_uuid=".$api_subscription->id;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$internal_plan_uuid = $api_subscription->metadata->internal_plan_uuid;
			
			$internal_plan = InternalPlanDAO::getInternalPlanByUuid($internal_plan_uuid);
			
			if($internal_plan == NULL) {
				$msg = "unknown internal_plan_uuid : ".$internal_plan_uuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$plan_id = InternalPlanLinksDAO::getProviderPlanIdFromInternalPlanId($internal_plan->getId(), $provider->getId());
			if($plan_id == NULL) {
				$msg = "unknown plan : ".$internal_plan_uuid." for provider : ".$provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$plan = PlanDAO::getPlanById($plan_id);
			if($plan == NULL) {
				$msg = "unknown plan with id : ".$plan_id;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
		}
		if($planOpts == NULL) {
			$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
		}
		//UPDATE
		//$db_subscription->setProviderId($provider->getId());//STATIC
		//$db_subscription->setUserId($user->getId());//STATIC
		$db_subscription->setPlanId($plan->getId());
		$db_subscription = BillingsSubscriptionDAO::updatePlanId($db_subscription);
		//$db_subscription->setSubUid($subscription_uuid);//STATIC
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
		$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
		//
		$db_subscription->setSubActivatedDate(new DateTime($api_subscription->created_at));
		$db_subscription = BillingsSubscriptionDAO::updateSubActivatedDate($db_subscription);
		//
		//NOT GIVEN : $db_subscription->setSubCanceledDate(NULL);
		//NOT GIVEN : $db_subscription->setSubExpiresDate(NULL);
		//To be calculated from billings api
		//NOT GIVEN : $db_subscription->setSubPeriodStartedDate(NULL);
		//NOT GIVEN : To be calculated from billings api
		//$db_subscription->setSubPeriodEndsDate(NULL);
		//NOT GIVEN : collection_mode
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
		$db_subscription = BillingsSubscriptionDAO::updateUpdateType($db_subscription);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription = BillingsSubscriptionDAO::updateUpdateId($db_subscription);
		//$db_subscription->setDeleted('false');//STATIC
		//
		//$db_subscription = BillingsSubscriptionDAO::updateBillingsSubscription($db_subscription);
		config::getLogger()->addInfo("gocardless dbsubscription update for userid=".$user->getId().", gocardless_subscription_uuid=".$api_subscription->id.", id=".$db_subscription->getId()." done successfully");
		return($db_subscription);
	}
	
	private function getDbSubscriptionByUuid(array $db_subscriptions, $subUuid) {
		foreach ($db_subscriptions as $db_subscription) {
			if($db_subscription->getSubUid() == $subUuid) {
				return($db_subscription);
			}
		}
	}
	
	private function getApiSubscriptionByUuid(Paginator $api_subscriptions, $subUuid) {
		foreach ($api_subscriptions as $api_subscription) {
			if($api_subscription->id == $subUuid) {
				return($api_subscription);
			}
		}
	}
	
	public function doFillSubscription(BillingsSubscription $subscription) {
		//TODO : later, is_active will be changed when a subscription is postponed
		//TODO :
		/*$oRange = new DateRange(new DateTime(), new DateTime($subscription->getSubActivatedDate()), DateInterval::createFromDateString("-1 month") );
		foreach ($oRange as $oDate)
		{
			echo $oDate->format("Y-m-d") . "<br />";
		}*/
		
		//find period : monthly / yearly / what about : one shot ?
		/*$activated_date = $subscription->getSubActivatedDate();
		$activated_day_of_month = (new DateTime($activated_date))->format('d');
		$now = new DateTime();
		echo "activated_date=".$activated_date." dayOfMonth=".$activated_day_of_month;
		$today_day_of_month = $now->format('d');
		echo "now=".dbGlobal::toISODate($now)." dayOfMonth=".$today_day_of_month;
		$diff = $today_day_of_month - $activated_day_of_month;
		$periodStartedDate = NULL;
		$periodEndedDate = NULL;
		if($diff >= 0) {
			$oRange = new DateRange();
		} else {
			
		}
		//To be calculated from billings api
		$db_subscription->setSubPeriodStartedDate(NULL);
		//To be calculated from billings api
		$db_subscription->setSubPeriodEndsDate(NULL);
		*/
	}
}

?>