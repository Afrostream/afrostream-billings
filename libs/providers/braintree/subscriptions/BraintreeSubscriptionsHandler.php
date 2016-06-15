<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';

class BraintreeSubscriptionsHandler extends SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_provider_uuid, BillingInfoOpts $billingInfoOpts, BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("braintree subscription creation...");
			if(isset($subscription_provider_uuid)) {
				checkSubOptsArray($subOpts->getOpts(), 'braintree', 'get');
				// in braintree : user subscription is pre-created
				Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
				Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
				Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
				Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
				//
				$subscription = NULL;
				$customer = Braintree\Customer::find($user->getUserProviderUuid());
				foreach ($customer->paymentMethods as $paymentMethod) {
					foreach ($paymentMethod->subscriptions as $customer_subscription) {
						if($customer_subscription->id == $subscription_provider_uuid) {
							$subscription = $customer_subscription;
							break;
						}
					}
				}
				if($subscription == NULL) {
					$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." not found for user with provider_user_uuid=".$user->getUserProviderUuid();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);					
				}
			} else {
				checkSubOptsArray($subOpts->getOpts(), 'braintree', 'create');
				// in braintree : user subscription is NOT pre-created
				Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
				Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
				Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
				Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
				//
				$paymentMethod = Braintree\PaymentMethod::find($subOpts->getOpts()['customerBankAccountToken']);
				if($paymentMethod->customerId != $user->getUserProviderUuid()) {
					$msg = "customerBankAccountToken =".$subOpts->getOpts()['customerBankAccountToken']." not found for user with provider_user_uuid=".$user->getUserProviderUuid();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);					
				}
				//
				$attribs = array();
				$attribs['planId'] = $plan->getPlanUuid();
				$attribs['paymentMethodToken'] = $subOpts->getOpts()['customerBankAccountToken'];				
				
				if(array_key_exists('couponCode', $subOpts->getOpts())) {
					$couponCode = $subOpts->getOpts()['couponCode'];
					if(strlen($couponCode) > 0) {
						$attribs[] = [
							'discounts' =>	[
								'add' =>		[
													[
														'inheritedFromId' => $couponCode,
													]
												]
							 				]
						 ];					
					}
				}
				$result = Braintree\Subscription::create($attribs);
				if ($result->success) {
					$subscription = $result->subscription;
				} else {
					$msg = 'a braintree api error occurred : ';
					$errorString = $result->message;
					foreach($result->errors->deepAll() as $error) {
						$errorString.= '; Code=' . $error->code . ", msg=" . $error->message;
					}
					throw new Exception($msg.$errorString);
				}
			}
			//
			$sub_uuid = $subscription->id;
			config::getLogger()->addInfo("braintree subscription creation done successfully, braintree_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a braintree subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription creation failed : ".$msg);
			throw $e;
		} catch(Braintree\Exception\NotFound $e) {
			$msg = "a not found error exception occurred while creating a braintree subscription for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);	
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a braintree subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($sub_uuid);
	}
	
	public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
		config::getLogger()->addInfo("braintree dbsubscriptions update for userid=".$user->getId()."...");
		//
		Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
		Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
		Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
		Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
		//
		$provider = ProviderDAO::getProviderById($user->getProviderId());
		//
		if($provider == NULL) {
			$msg = "unknown provider id : ".$user->getProviderId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$api_subscriptions = array();
		try {
			$customer = Braintree\Customer::find($user->getUserProviderUuid());
			foreach ($customer->paymentMethods as $paymentMethod) {
				foreach ($paymentMethod->subscriptions as $customer_subscription) {
					$api_subscriptions[] = $customer_subscription;
				}
			}
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting subscriptions for user_provider_uuid=".$user->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$db_subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		//ADD OR UPDATE
		foreach ($api_subscriptions as $api_subscription) {
			//plan
			$plan_uuid = $api_subscription->planId;
			$plan = PlanDAO::getPlanByUuid($provider->getId(), $plan_uuid);
			if($plan == NULL) {
				$msg = "plan with uuid=".$plan_uuid." not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
			$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($plan->getId()));
			if($internalPlan == NULL) {
				$msg = "plan with uuid=".$plan_uuid." for provider ".$provider->getName()." is not linked to an internal plan";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
			$db_subscription = $this->getDbSubscriptionByUuid($db_subscriptions, $api_subscription->id);
			if($db_subscription == NULL) {
				//CREATE
				$db_subscription = $this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, NULL, $api_subscription, 'api', 0);
			} else {
				//UPDATE
				$db_subscription = $this->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription, 'api', 0);
			}
		}
		//DELETE UNUSED SUBSCRIPTIONS (DELETED FROM THIRD PARTY)
		foreach ($db_subscriptions as $db_subscription) {
			$api_subscription = $this->getApiSubscriptionByUuid($api_subscriptions, $db_subscription->getSubUid());
			if($api_subscription == NULL) {
				BillingsSubscriptionDAO::deleteBillingsSubscriptionById($db_subscription->getId());
			}
		}
		config::getLogger()->addInfo("braintree dbsubscriptions update for userid=".$user->getId()." done successfully");
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, $sub_uuid, $update_type, $updateId) {
		//
		Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
		Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
		Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
		Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
		//
		$api_subscription = Braintree\Subscription::find($sub_uuid);
		//
		return($this->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $subOpts, $api_subscription, $update_type, $updateId));
	}
	
	public function createDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, BillingsSubscriptionOpts $subOpts = NULL, Braintree\Subscription $api_subscription, $update_type, $updateId) {
		config::getLogger()->addInfo("braintree dbsubscription creation for userid=".$user->getId().", braintree_subscription_uuid=".$api_subscription->id."...");
		//CREATE
		$db_subscription = new BillingsSubscription();
		$db_subscription->setSubscriptionBillingUuid(guid());
		$db_subscription->setProviderId($provider->getId());
		$db_subscription->setUserId($user->getId());
		$db_subscription->setPlanId($plan->getId());
		$db_subscription->setSubUid($api_subscription->id);
		switch ($api_subscription->status) {
			case Braintree\Subscription::ACTIVE :
				$db_subscription->setSubStatus('active');
				$db_subscription->setSubActivatedDate($api_subscription->createdAt);
				break;
			case Braintree\Subscription::CANCELED :
				$db_subscription->setSubStatus('canceled');
				$db_subscription->setSubCanceledDate($api_subscription->updatedAt);
				break;
			case Braintree\Subscription::EXPIRED :
				$db_subscription->setSubStatus('expired');
				$db_subscription->setSubExpiresDate($api_subscription->updatedAt);
				break;
			case Braintree\Subscription::PAST_DUE :
				$db_subscription->setSubStatus('active');//TODO : check
				break;
			case Braintree\Subscription::PENDING :
				$db_subscription->setSubStatus('future');
				break;
			default :
				$msg = "unknown subscription state : ".$api_subscription->status;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		$db_subscription->setSubPeriodStartedDate($api_subscription->billingPeriodStartDate);
		$db_subscription->setSubPeriodEndsDate($api_subscription->billingPeriodEndDate);
		$db_subscription->setUpdateType($update_type);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription->setDeleted('false');
		//NO MORE TRANSACTION (DONE BY CALLER)
		//<-- DATABASE -->
		$db_subscription = BillingsSubscriptionDAO::addBillingsSubscription($db_subscription);
		//SUB_OPTS
		if(isset($subOpts)) {
			$subOpts->setSubId($db_subscription->getId());
			$subOpts = BillingsSubscriptionOptsDAO::addBillingsSubscriptionOpts($subOpts);
		}
		//<-- DATABASE -->
		config::getLogger()->addInfo("braintree dbsubscription creation for userid=".$user->getId().", braintree_subscription_uuid=".$api_subscription->id." done successfully, id=".$db_subscription->getId());
		return($db_subscription);
	}
	
	public function updateDbSubscriptionFromApiSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, Braintree\Subscription $api_subscription, BillingsSubscription $db_subscription, $update_type, $updateId) {		
		config::getLogger()->addInfo("braintree dbsubscription update for userid=".$user->getId().", braintree_subscription_uuid=".$api_subscription->id.", id=".$db_subscription->getId()."...");
		//UPDATE
		$now = new DateTime();
		//$db_subscription->setProviderId($provider->getId());//STATIC
		//$db_subscription->setUserId($user->getId());//STATIC
		$db_subscription->setPlanId($plan->getId());
		$db_subscription = BillingsSubscriptionDAO::updatePlanId($db_subscription);
		//$db_subscription->setSubUid($subscription_uuid);//STATIC
		switch ($api_subscription->status) {
			case Braintree\Subscription::ACTIVE :
				$db_subscription->setSubStatus('active');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				if($db_subscription->getSubActivatedDate() == NULL) {
					$db_subscription->setSubActivatedDate($now);//assume it's now only if not already set
					$db_subscription = BillingsSubscriptionDAO::updateSubActivatedDate($db_subscription);
				}
				break;
			case Braintree\Subscription::CANCELED :
				$db_subscription->setSubStatus('canceled');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				$db_subscription->setSubCanceledDate($api_subscription->updatedAt);
				$db_subscription = BillingsSubscriptionDAO::updateSubCanceledDate($db_subscription);
				break;
			case Braintree\Subscription::EXPIRED :
				$db_subscription->setSubStatus('expired');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				$db_subscription->setSubExpiresDate($api_subscription->updatedAt);
				$db_subscription = BillingsSubscriptionDAO::updateSubExpiresDate($db_subscription);
				break;
			case Braintree\Subscription::PAST_DUE :
				$db_subscription->setSubStatus('active');//TODO : check
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				break;
			case Braintree\Subscription::PENDING :
				$db_subscription->setSubStatus('future');
				$db_subscription = BillingsSubscriptionDAO::updateSubStatus($db_subscription);
				break;
			default :
				$msg = "unknown subscription state : ".$api_subscription->status;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				//break;
		}
		//
		$db_subscription->setSubPeriodStartedDate($api_subscription->billingPeriodStartDate);
		$db_subscription->setSubPeriodEndsDate($api_subscription->billingPeriodEndDate);
		//
		$db_subscription->setUpdateType($update_type);
		$db_subscription = BillingsSubscriptionDAO::updateUpdateType($db_subscription);
		//
		$db_subscription->setUpdateId($updateId);
		$db_subscription = BillingsSubscriptionDAO::updateUpdateId($db_subscription);
		//$db_subscription->setDeleted('false');//STATIC
		// 
		config::getLogger()->addInfo("braintree dbsubscription update for userid=".$user->getId().", braintree_subscription_uuid=".$api_subscription->id.", id=".$db_subscription->getId()." done successfully");
		return($db_subscription);
	}
	
	public function doCancelSubscription(BillingsSubscription $subscription, DateTime $cancel_date, $is_a_request = true) {
		try {
			config::getLogger()->addInfo("braintree subscription canceling...");
			if(
					$subscription->getSubStatus() == "canceled"
					||
					$subscription->getSubStatus() == "expired"
			)
			{
				//nothing todo : already done or in process
			} else {
				//
				Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
				Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
				Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
				Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
				//
				$result = Braintree\Subscription::cancel($subscription->getSubUid());
				if (!$result->success) {
					$msg = 'a braintree api error occurred : ';
					$errorString = $result->message;
					foreach($result->errors->deepAll() as $error) {
						$errorString.= '; Code=' . $error->code . ", msg=" . $error->message;;
					}
					throw new Exception($msg.$errorString);
				}
				//
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
			config::getLogger()->addInfo("braintree subscription canceling done successfully for braintree_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while canceling a braintree subscription for braintree_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription canceling failed : ".$msg);
			throw $e;
		} catch(Braintree\Exception\NotFound $e) {
			$msg = "a not found error exception occurred while canceling a braintree subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription canceling failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);	
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while canceling a braintree subscription for braintree_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription canceling failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($subscription);
	}
	
	protected function doFillSubscription(BillingsSubscription $subscription = NULL) {
		//TODO : check what happens at the end of the subscription
		if($subscription == NULL) {
			return;
		}
		$is_active = NULL;
		switch($subscription->getSubStatus()) {
			case 'active' :
				$is_active = 'yes';//always 'yes' ?
				break;
			case 'canceled' :
				$is_active = 'yes';//always 'yes' ?
				break;
			case 'future' :
				$is_active = 'no';
				break;
			case 'expired' :
				$is_active = 'no';
				break;
			default :
				$is_active = 'no';
				config::getLogger()->addWarning("braintree dbsubscription unknown subStatus=".$subscription->getSubStatus().", braintree_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;		
		}
		$subscription->setIsActive($is_active);
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		parent::doSendSubscriptionEvent($subscription_before_update, $subscription_after_update);
	}
	
	public function doUpdateInternalPlan(BillingsSubscription $subscription, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts) {
		try {
			config::getLogger()->addInfo("braintree subscription updating Plan...");
			//
			Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
			Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
			Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
			Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
			//
			Braintree\Subscription::update($subscription->getSubUid(), 
					[
							'planId' => $plan->getPlanUuid(),
							'price' => $internalPlan->getAmount(),	//Braintree does not change the price !!!
							'options' => [
									prorateCharges => true
							]
					]);
			
			//
			$subscription->setPlanId($plan->getId());
			//
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				BillingsSubscriptionDAO::updatePlanId($subscription);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($subscription->getId());
			config::getLogger()->addInfo("braintree subscription updating Plan done successfully for braintree_subscription_uuid=".$subscription->getSubUid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating a Plan braintree subscription for braintree_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription reactivating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating a Plan braintree subscription for braintree_subscription_uuid=".$subscription->getSubUid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree subscription updating Plan failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($subscription);
	}
	
	private function getDbSubscriptionByUuid(array $db_subscriptions, $subUuid) {
		foreach ($db_subscriptions as $db_subscription) {
			if($db_subscription->getSubUid() == $subUuid) {
				return($db_subscription);
			}
		}
		return(NULL);
	}
	
	private function getApiSubscriptionByUuid(array $api_subscriptions, $subUuid) {
		foreach ($api_subscriptions as $api_subscription) {
			if($api_subscription->id == $subUuid) {
				return($api_subscription);
			}
		}
		return(NULL);
	}
}

?>