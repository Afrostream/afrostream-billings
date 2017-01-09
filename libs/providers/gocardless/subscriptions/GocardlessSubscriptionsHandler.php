<?php

use GoCardlessPro\Client;
use GoCardlessPro\Resources\Subscription;
use GoCardlessPro\Core\Exception\GoCardlessProException;
use GoCardlessPro\Core\Paginator;

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../plans/GocardlessPlansHandler.php';
require_once __DIR__ . '/../../global/ProviderHandlersBuilder.php';
require_once __DIR__ . '/../../global/subscriptions/ProviderSubscriptionsHandler.php';
require_once __DIR__ . '/../../global/transactions/ProviderTransactionsHandler.php';
require_once __DIR__ . '/../../global/requests/ExpireSubscriptionRequest.php';
require_once __DIR__ . '/../../global/requests/RefundTransactionRequest.php';

class GocardlessSubscriptionsHandler extends ProviderSubscriptionsHandler {
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_billing_uuid, $subscription_provider_uuid, BillingInfo $billingInfo, BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("gocardless subscription creation...");
			if(isset($subscription_provider_uuid)) {
				checkSubOptsArray($subOpts->getOpts(), 'gocardless', 'get');
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
					if($sub_entry->id == $subscription_provider_uuid) {
						$found = true; 
						break;
					}
				}
				if(!$found) {
					$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." not found for user with provider_user_uuid=".$user->getUserProviderUuid();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$sub_uuid = $subscription_provider_uuid;
			} else {
				checkSubOptsArray($subOpts->getOpts(), 'gocardless', 'create');
				$this->checkBillingInfos($billingInfo, $user);
				$amount = $internalPlan->getAmountInCents();
				$currency = $internalPlan->getCurrency();
				if(!array_key_exists($internalPlan->getPeriodUnit()->getValue(), GocardlessPlansHandler::$supported_periods)) {
					$msg = "unsupported period unit, must be in : ".implode(', ', array_keys(GocardlessPlansHandler::$supported_periods));
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$interval_unit = NULL;
				switch ($internalPlan->getPeriodUnit()) {
					case PlanPeriodUnit::day :
						$interval_unit = 'daily';
						break;
					case PlanPeriodUnit::month :
						$interval_unit = 'monthly';
						break;
					case PlanPeriodUnit::year :
						$interval_unit = 'yearly';
						break;
					default :
						$msg = "unsupported period unit, must be in : ".implode(', ', array_keys(GocardlessPlansHandler::$supported_periods));
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						break;
				}
				if(!in_array($internalPlan->getCycle()->getValue(), GocardlessPlansHandler::$supported_cycles)) {
					$msg = "unsupported cycle, must be in : ".implode(', ', GocardlessPlansHandler::$supported_cycles);
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$count = NULL;
				switch($internalPlan->getCycle()) {
					case PlanCycle::once :
						$count = 1;
						break;
					case PlanCycle::auto :
						$count = NULL;
						break;
					default :
						$msg = "unsupported cycle, must be in : ".implode(', ', GocardlessPlansHandler::$supported_cycles);
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						break;
				}
				if(!array_key_exists('customerBankAccountToken', $subOpts->getOpts())) {
					$msg = "subOpts field 'customerBankAccountToken' field is missing";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$customer_bank_account_token = $subOpts->getOpts()['customerBankAccountToken'];
				//
				$client = new Client(array(
						'access_token' => getEnv('GOCARDLESS_API_KEY'),
						'environment' => getEnv('GOCARDLESS_API_ENV')
				));
				//HACK : Disable current Bank Accounts (Create will be enable existing accounts)
				config::getLogger()->addInfo("gocardless subscription creation... bank accounts deactivation...");
				$bank_accounts = $client->customerBankAccounts()->all(
						['params' =>
								[
										'customer' => $user->getUserProviderUuid()
								]
						]);
				foreach ($bank_accounts as $current_bank_account) {
					if($current_bank_account->links->customer == $user->getUserProviderUuid()) //Double CHECK :)
					{
						if($current_bank_account->enabled) {
							$client->customerBankAccounts()->disable($current_bank_account->id);
						}
					}
				}
				config::getLogger()->addInfo("gocardless subscription creation... bank accounts deactivation done successfully");
				//Create a Bank Account
				config::getLogger()->addInfo("gocardless subscription creation... bank account creation...");
				$bank_account = $client->customerBankAccounts()->create(
						['params' =>
								[
										'links' => [
												'customer' => $user->getUserProviderUuid(),
												'customer_bank_account_token' => $customer_bank_account_token
										]
								]
						]);
				config::getLogger()->addInfo("gocardless subscription creation... bank account creation done successfully, bank_acccount_id=".$bank_account->id);
				//Create a Mandate
				config::getLogger()->addInfo("gocardless subscription creation... mandate creation...");
				$mandate = $client->mandates()->create(
						['params' =>
								[
										'links' => ['customer_bank_account' => $bank_account->id]
								]
						]);
				config::getLogger()->addInfo("gocardless subscription creation... mandate creation done successfully, mandate_id=".$mandate->id);
				config::getLogger()->addInfo("gocardless subscription creation... subscription creation...");
				$sub_params = ['params' =>
					 		[
					 				'amount' => $internalPlan->getAmountInCents(),
					 				'currency' => $internalPlan->getCurrency(),
					 				'name' => $plan->getName(),
					 				'interval_unit' => $interval_unit,
					 				'interval' => $internalPlan->getPeriodLength(),
					 				'links' => ['mandate' => $mandate->id],
					 				'metadata' => ['internal_plan_uuid' => $internalPlan->getInternalPlanUuid()]
					 		]
					 ];
				if(isset($count)) {
					$sub_params['params']['count'] = $count;
				}
				$subscription = $client->subscriptions()->create($sub_params);
				config::getLogger()->addInfo("gocardless subscription creation... subscription creation done successfully, subscription_id=".$subscription->id);
				$sub_uuid = $subscription->id;	
			}

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
				$db_subscription = $this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, NULL, NULL, NULL, NULL, NULL, NULL, guid(), $api_subscription, 'api', 0);
			} else {
				//UPDATE
				$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, NULL, NULL, NULL, NULL, $api_subscription, $db_subscription, 'api', 0);
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
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan = NULL, InternalPlanOpts $internalPlanOpts = NULL, Plan $plan = NULL , PlanOpts $planOpts = NULL, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, $sub_uuid, $update_type, $updateId) {
		//
		$client = new Client(array(
			'access_token' => getEnv('GOCARDLESS_API_KEY'),
			'environment' => getEnv('GOCARDLESS_API_ENV')
		));
		//
		$api_subscription = $client->subscriptions()->get($sub_uuid);
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $billingInfo, $subscription_billing_uuid, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan = NULL, InternalPlanOpts $internalPlanOpts = NULL, Plan $plan = NULL, PlanOpts $planOpts = NULL, BillingsSubscriptionOpts $subOpts = NULL, BillingInfo $billingInfo = NULL, $subscription_billing_uuid, Subscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("gocardless dbsubscription creation for userid=".$user->getId().", gocardless_subscription_uuid=".$api_subscription->id."...");
		if($plan == NULL) {
			if(!isset($api_subscription->metadata->internal_plan_uuid)) {
				$msg = "metadata 'internal_plan_uuid' is not field in gocardless subscription with gocardless_subscription_uuid=".$api_subscription->id;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$internal_plan_uuid = $api_subscription->metadata->internal_plan_uuid;
			
			$internalPlan = InternalPlanDAO::getInternalPlanByUuid($internal_plan_uuid);
			
			if($internalPlan == NULL) {
				$msg = "unknown internal_plan_uuid : ".$internal_plan_uuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$plan_id = InternalPlanLinksDAO::getProviderPlanIdFromInternalPlanId($internalPlan->getId(), $provider->getId());
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
		if($internalPlanOpts == NULL) {
			$internalPlanOpts = InternalPlanDAO::getInternalPlanById($internalPlan->getId());
		}
		if($planOpts == NULL) {
			$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
		}
		//CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid($subscription_billing_uuid);
		$db_subscription->setProviderId($provider->getId());
		$db_subscription->setUserId($user->getId());
		$db_subscription->setPlanId($plan->getId());
		$db_subscription->setSubUid($api_subscription->id);
		switch ($api_subscription->status) {
			case 'active' :
				$db_subscription->setSubStatus('active');
				//we do not really know
				$db_subscription->setSubActivatedDate(new DateTime($api_subscription->created_at));
				break;
			case 'cancelled' :
				$db_subscription->setSubStatus('canceled');
				//we do not really know
				$db_subscription->setSubCanceledDate(new DateTime($api_subscription->created_at));
				break;
			case 'pending_customer_approval' :
				$db_subscription->setSubStatus('future');
				break;
			case 'finished' :
				//For more infos : https://developer.gocardless.com/api-reference/#subscriptions-get-a-single-subscription
				//It tells us that we will have no more payments but the subscripion will end later
				$db_subscription->setSubStatus('active');
				//we do not really know
				$db_subscription->setSubActivatedDate(new DateTime($api_subscription->created_at));
				break;
			case 'customer_approval_denied' :
				$db_subscription->setSubStatus('expired');
				//we do not really know
				$db_subscription->setSubExpiresDate(new DateTime($api_subscription->created_at));
				//NC : Because considered as an immediate cancel
				$db_subscription->setSubCanceledDate(new DateTime($api_subscription->created_at));
				break;
			default :
				$msg = "unknown subscription status : ".$api_subscription->status;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		//
		$start_date = new DateTime($api_subscription->created_at);
		$start_date->setTimezone(new DateTimeZone(config::$timezone));
		$end_date = NULL;
		switch($internalPlan->getPeriodUnit()) {
			case PlanPeriodUnit::day :
				$end_date = clone $start_date;
				$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."D"));
				$end_date->setTime(23, 59, 59);//force the time to the end of the day
				break;
			case PlanPeriodUnit::month :
				$end_date = clone $start_date;
				$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."M"));
				$end_date->setTime(23, 59, 59);//force the time to the end of the day
				break;	
			case PlanPeriodUnit::year :
				$end_date = clone $start_date;
				$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."Y"));
				$end_date->setTime(23, 59, 59);//force the time to the end of the day
				break;
			default :
				$msg = "unsupported periodUnit : ".$internalPlan->getPeriodUnit()->getValue();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
		$db_subscription->setSubPeriodStartedDate($start_date);
		$db_subscription->setSubPeriodEndsDate($end_date);
		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription->setDeleted(false);
		//NO MORE TRANSACTION (DONE BY CALLER)
		//<-- DATABASE -->
		//BILLING_INFO
		if(isset($billingInfo)) {
			$billingInfo = BillingInfoDAO::addBillingInfo($billingInfo);
			$db_subscription->setBillingInfoId($billingInfo->getId());
		}
		$db_subscription = BillingsSubscriptionDAO::addBillingsSubscription($db_subscription);
		//SUB_OPTS
		if(isset($subOpts)) {
			$subOpts->setSubId($db_subscription->getId());
			$subOpts = BillingsSubscriptionOptsDAO::addBillingsSubscriptionOpts($subOpts);
		}
		//<-- DATABASE -->
		config::getLogger()->addInfo("gocardless dbsubscription creation for userid=".$user->getId().", gocardless_subscription_uuid=".$api_subscription->id." done successfully, id=".$db_subscription->getId());
		return($this->doFillSubscription($db_subscription));
	}
	
	public function updateDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan = NULL, InternalPlanOpts $internalPlanOpts = NULL, Plan $plan = NULL, PlanOpts $planOpts = NULL, Subscription $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("gocardless dbsubscription update for userid=".$user->getId().", gocardless_subscription_uuid=".$api_subscription->id.", id=".$db_subscription->getId()."...");
		//UPDATE
		$db_subscription_before_update = clone $db_subscription;
		//
		$now = new DateTime();
		//
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
		//$db_subscription->setProviderId($provider->getId());//STATIC
		//$db_subscription->setUserId($user->getId());//STATIC
		$db_subscription->setPlanId($plan->getId());
		$db_subscription = BillingsSubscriptionDAO::updatePlanId($db_subscription);
		//$db_subscription->setSubUid($subscription_uuid);//STATIC
		switch ($api_subscription->status) {
			case 'active' :
				$db_subscription->setSubStatus('active');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				if($db_subscription->getSubActivatedDate() == NULL) {
					$db_subscription->setSubActivatedDate($now);
					$db_subscription = BillingsSubscriptionDAO::updateSubActivatedDate($db_subscription);
				}
				break;
			case 'cancelled' :
				if(
					(isset($api_subscription->metadata->status)
						&&
					($api_subscription->metadata->status == 'expired'))
					|| 
					($db_subscription->getSubStatus() == 'expired')
				)
				{
					//if expired, stay expired
					$db_subscription->setSubStatus('expired');
					$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
					if($db_subscription->getSubExpiresDate() == NULL) {
						$db_subscription->setSubExpiresDate($now);
						$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
					}
				} else {
					//canceled
					$db_subscription->setSubStatus('canceled');
					$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
					if($db_subscription->getSubCanceledDate() == NULL) {
						$db_subscription->setSubCanceledDate($now);
						$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
					}
				}
				break;
			case 'pending_customer_approval' :
				$db_subscription->setSubStatus('future');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				break;
			case 'finished' :
				//For more infos : https://developer.gocardless.com/api-reference/#subscriptions-get-a-single-subscription
				//It tells us that we will have no more payments but the subscripion will end later
				config::getLogger()->addInfo("gocardless dbsubscription update for userid=".$user->getId().", gocardless_subscription_uuid=".$api_subscription->id.", id=".$db_subscription->getId().", gocardless_subscription_status=finished ignored");
				break;
			case 'customer_approval_denied' :
				$db_subscription->setSubStatus('expired');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				if($db_subscription->getSubExpiresDate() == NULL) {
					$db_subscription->setSubExpiresDate($now);
					$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
				}
				//NC : Because considered as an immediate cancel
				if($db_subscription->getSubCanceledDate() == NULL) {
					$db_subscription->setSubCanceledDate($now);
					$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
				}
				break;
			default :
				$msg = "unknown subscription status : ".$api_subscription->status;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		$db_subscription->setUpdateType($update_type);
		$db_subscription = BillingsSubscriptionDAO::updateUpdateType($db_subscription);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription = BillingsSubscriptionDAO::updateUpdateId($db_subscription);
		//$db_subscription->setDeleted(false);//STATIC
		//
		$this->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
		//
		config::getLogger()->addInfo("gocardless dbsubscription update for userid=".$user->getId().", gocardless_subscription_uuid=".$api_subscription->id.", id=".$db_subscription->getId()." done successfully");
		return($this->doFillSubscription($db_subscription));
	}
	
	private function getDbSubscriptionByUuid(array $db_subscriptions, $subUuid) {
		foreach ($db_subscriptions as $db_subscription) {
			if($db_subscription->getSubUid() == $subUuid) {
				return($db_subscription);
			}
		}
		return(NULL);
	}
	
	private function getApiSubscriptionByUuid(Paginator $api_subscriptions, $subUuid) {
		foreach ($api_subscriptions as $api_subscription) {
			if($api_subscription->id == $subUuid) {
				return($api_subscription);
			}
		}
		return(NULL);
	}
	
	public function doRenewSubscription(BillingsSubscription $subscription, DateTime $start_date = NULL, DateTime $end_date = NULL) {
		if($end_date != NULL) {
			$msg = "renewing a gocardless subscription does not support that end_date is already set";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($subscription->getSubStatus() != "active") {
			$msg = "cannot renew because of the current_status=".$subscription->getSubStatus();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$providerPlan = PlanDAO::getPlanById($subscription->getPlanId());
		if($providerPlan == NULL) {
			$msg = "unknown plan with id : ".$subscription->getPlanId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($providerPlan->getId()));
		if($internalPlan == NULL) {
			$msg = "plan with uuid=".$providerPlan->getPlanUuid()." for provider gocardless is not linked to an internal plan";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		
		$today = new DateTime();
		$today->setTimezone(new DateTimeZone(config::$timezone));
		$today->setTime(23, 59, 59);//consider all the day
		
		if($start_date == NULL) {
			$start_date = $subscription->getSubPeriodEndsDate();
		}
		$start_date->setTimezone(new DateTimeZone(config::$timezone));
		
		$end_date = clone $start_date;
		
		$to_be_updated = false;
		
		switch($internalPlan->getPeriodUnit()) {
			case PlanPeriodUnit::day :
				while ($end_date < $today) {
					$to_be_updated = true;
					$start_date = clone $end_date;
					$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."D"));
					$end_date->setTime(23, 59, 59);//force the time to the end of the day
				}
				break;
			case PlanPeriodUnit::month :
				while ($end_date < $today) {
					$to_be_updated = true;
					$start_date = clone $end_date;
					$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."M"));
					$end_date->setTime(23, 59, 59);//force the time to the end of the day
				}
				break;	
			case PlanPeriodUnit::year :
				while ($end_date < $today) {
					$to_be_updated = true;
					$start_date = clone $end_date;
					$end_date->add(new DateInterval("P".$internalPlan->getPeriodLength()."Y"));
					$end_date->setTime(23, 59, 59);//force the time to the end of the day
				}
				break;
			default :
				$msg = "unsupported periodUnit : ".$internalPlan->getPeriodUnit()->getValue();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
		//done
		$start_date->setTime(0, 0, 0);//force start_date to beginning of the day
		if($to_be_updated) {
			$subscription->setSubPeriodStartedDate($start_date);
			$subscription->setSubPeriodEndsDate($end_date);
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				BillingsSubscriptionDAO::updateSubStartedDate($subscription);
				BillingsSubscriptionDAO::updateSubEndsDate($subscription);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
		}
		return($this->doFillSubscription(BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId())));
	}
	
	public function doCancelSubscription(BillingsSubscription $subscription, DateTime $cancel_date, $is_a_request = true) {
		try {
			config::getLogger()->addInfo("gocardless subscription cancel...");
			if(
					$subscription->getSubStatus() == "canceled"
					||
					$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				//
				if($is_a_request == true) {
					$client = new Client(array(
							'access_token' => getEnv('GOCARDLESS_API_KEY'),
							'environment' => getEnv('GOCARDLESS_API_ENV')
					));
					//
					$client->subscriptions()->cancel($subscription->getSubUid());
				}
				$subscription->setSubCanceledDate($cancel_date);
				$subscription->setSubStatus('canceled');
				//
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					BillingsSubscriptionDAO::updateSubCanceledDate($subscription);
					BillingsSubscriptionDAO::updateSubStatus($subscription);
					//COMMIT
					pg_query("COMMIT");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			}
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("gocardless subscription cancel done successfully for gocardless_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while canceling a gocardless subscription for gocardless_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless subscription canceling failed : ".$msg);
			throw $e;
		} catch (GoCardlessProException $e) {
			$msg = "a GoCardlessProException occurred while canceling a gocardless subscription for gocardless_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless subscription canceling failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while canceling a gocardless subscription for gocardless_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless subscription canceling failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
	
	public function doExpireSubscription(BillingsSubscription $subscription, ExpireSubscriptionRequest $expireSubscriptionRequest) {
		try {
			config::getLogger()->addInfo("gocardless subscription expiring...");
			if(
				$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				//
				$expiresDate = $expireSubscriptionRequest->getExpiresDate();
				//
				if(in_array($subscription->getSubStatus(), ['active', 'canceled'])) {
					if($subscription->getSubPeriodEndsDate() > $expiresDate) {
						if($expireSubscriptionRequest->getForceBeforeEndsDate() == false) {
							$msg = "cannot expire a ".$this->provider->getName()." subscription that has not ended yet";
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_EXP_BEFORE_ENDS_DATE_UNSUPPORTED);
						}
					}
				}
				if(in_array($expireSubscriptionRequest->getOrigin(), ['api', 'hook'])) {
					if($subscription->getSubStatus() == "canceled") {
						//already canceled, nothing can be done in gocardless side
					} else {
						$client = new Client(array(
								'access_token' => getEnv('GOCARDLESS_API_KEY'),
								'environment' => getEnv('GOCARDLESS_API_ENV')
						));
						$api_subscription = $client->subscriptions()->get($subscription->getSubUid());
						if($api_subscription->status != 'cancelled') {
							//anyway
							$metadata_array = array();
							foreach ($api_subscription->metadata as $key => $value) {
								$metadata_array[$key] = $value;
							}
							$metadata_array['status'] = 'expired';
							$sub_params = ['params' =>
									[
											'metadata' => $metadata_array
									]
							];
							$api_subscription = $client->subscriptions()->update($api_subscription->id, $sub_params);
							//
							$client->subscriptions()->cancel($api_subscription->id);
						}
						$subscription->setSubCanceledDate($expiresDate);
					}
				}
				$subscription->setSubExpiresDate($expiresDate);
				$subscription->setSubStatus('expired');
				//
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					BillingsSubscriptionDAO::updateSubCanceledDate($subscription);
					BillingsSubscriptionDAO::updateSubExpiresDate($subscription);
					BillingsSubscriptionDAO::updateSubStatus($subscription);
					//COMMIT
					pg_query("COMMIT");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
				if($expireSubscriptionRequest->getIsRefundEnabled() == true) {
					$transactionsResult = BillingsTransactionDAO::getBillingsTransactions(1, 0, NULL, $subscription->getId(), ['purchase']);
					if(count($transactionsResult['transactions']) == 1) {
						$transaction = $transactionsResult['transactions'][0];
						$providerTransactionsHandlerInstance = ProviderHandlersBuilder::getProviderTransactionsHandlerInstance($this->provider);
						$refundTransactionRequest = new RefundTransactionRequest();
						$refundTransactionRequest->setOrigin($expireSubscriptionRequest->getOrigin());
						$refundTransactionRequest->setTransactionBillingUuid($transaction->getTransactionBillingUuid());
						$transaction = $providerTransactionsHandlerInstance->doRefundTransaction($transaction, $refundTransactionRequest);
					}
				}
			}
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("gocardless subscription expiring done successfully for gocardless_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while expiring a gocardless subscription for gocardless_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless subscription expiring failed : ".$msg);
			throw $e;
		} catch (GoCardlessProException $e) {
			$msg = "a GoCardlessProException occurred while expiring a gocardless subscription for gocardless_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless subscription expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring a gocardless subscription for gocardless_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless subscription expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($this->doFillSubscription($subscription));
	}
	
	public function doFillSubscription(BillingsSubscription $subscription = NULL) {
		$subscription = parent::doFillSubscription($subscription);
		if($subscription == NULL) {
			return NULL;
		}
		$is_active = NULL;
		switch($subscription->getSubStatus()) {
			case 'active' :
			case 'canceled' :
				$now = new DateTime();
				//check dates
				if(
						($now < $subscription->getSubPeriodEndsDate())
								&&
						($now >= $subscription->getSubPeriodStartedDate())
				) {
					//inside the period
					$is_active = 'yes';
				} else {
					//outside the period
					$is_active = 'no';
				}
				break;
			case 'future' :
				$is_active = 'no';
				break;
			case 'expired' :
				$is_active = 'no';
				break;
			default :
				$is_active = 'no';
				config::getLogger()->addWarning("gocardless dbsubscription unknown subStatus=".$subscription->getSubStatus().", gocardless_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;		
		}
		$subscription->setIsActive($is_active);
		return($subscription);
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		parent::doSendSubscriptionEvent($subscription_before_update, $subscription_after_update);
	}

	protected function checkBillingInfos(BillingInfo $billingInfo, User $user)
	{
		//if no iban suplied, return true
		$iban = $billingInfo->getIban();
		if(!empty($iban)) {
			$iban = preg_replace('/\s+/','', $iban);//remove all spaces
			$usersIban = UsersIbanDao::getIban($iban);
			if (!is_null($usersIban)) {
				if(!$usersIban->getValid()) {
					//exception
					$msg = "iban is not valid";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SEPA_IBAN_INVALID);
				}
			} else {
				$entity = new UsersIban();
				$entity->setIban($iban)
				->setUserid($user->getId())
				->setCreatedDate('now')
				->setValid(true);
				
				UsersIbanDao::save($entity);
			}
		}
	}
}
