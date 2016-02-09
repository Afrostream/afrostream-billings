<?php

use SebastianBergmann\Money\Money;
use SebastianBergmann\Money\Currency;
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../providers/celery/subscriptions/CelerySubscriptionsHandler.php';
require_once __DIR__ . '/../providers/recurly/subscriptions/RecurlySubscriptionsHandler.php';
require_once __DIR__ . '/../providers/gocardless/subscriptions/GocardlessSubscriptionsHandler.php';
require_once __DIR__ . '/../providers/bachat/subscriptions/BachatSubscriptionsHandler.php';
require_once __DIR__ . '/../db/dbGlobal.php';

class SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doGetSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid) {
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("subscription getting for subscriptionBillingUuid=".$subscriptionBillingUuid."...");
			//
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid);
			if($db_subscription == NULL) {
				$msg = "unknown subscriptionBillingUuid : ".$subscriptionBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			$this->doFillSubscription($db_subscription);
			//
			config::getLogger()->addInfo("subscription getting for subscriptionBillingUuid=".$subscriptionBillingUuid." successfully done");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting a subscription for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscription getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a subscription for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscription getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_subscription);
	}
	
	public function doGetOrCreateSubscription($user_billing_uuid, $internal_plan_uuid, $subscription_provider_uuid, array $billing_info_opts_array, array $sub_opts_array) {
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("subscription creating...");
			$this->checkBillingInfoOptsArray($billing_info_opts_array);
			$billingInfoOpts = new BillingInfoOpts();
			$billingInfoOpts->setOpts($billing_info_opts_array);
			$subOpts = new BillingsSubscriptionOpts();
			$subOpts->setOpts($sub_opts_array);
			$user = UserDAO::getUserByUserBillingUuid($user_billing_uuid);
			if($user == NULL) {
				$msg = "unknown user_billing_uuid : ".$user_billing_uuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			
			$internal_plan = InternalPlanDAO::getInternalPlanByUuid($internal_plan_uuid);
			if($internal_plan == NULL) {
				$msg = "unknown internal_plan_uuid : ".$internal_plan_uuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$internal_plan_opts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internal_plan->getId());
			
			$provider = ProviderDAO::getProviderById($user->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider with id : ".$user->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$provider_plan_id = InternalPlanLinksDAO::getProviderPlanIdFromInternalPlanId($internal_plan->getId(), $provider->getId());
			if($provider_plan_id == NULL) {
				$msg = "unknown plan : ".$internal_plan_uuid." for provider : ".$provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$provider_plan = PlanDAO::getPlanById($provider_plan_id);
			if($provider_plan == NULL) {
				$msg = "unknown plan with id : ".$provider_plan_id;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$provider_plan_opts = PlanOptsDAO::getPlanOptsByPlanId($provider_plan->getId());
			if(isset($subscription_provider_uuid)) {
				//check : Does this subscription_provider_uuid already exist in the Database ?
				$db_tmp_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($provider->getId(), $subscription_provider_uuid);
				if($db_tmp_subscription == NULL) {
					//nothing to do
				} else {
					//check if it is linked to the right user
					if($db_tmp_subscription->getUserId() != $user->getId()) {
						//Exception
						$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." is already linked to another user_reference_uuid";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					//check if it is linked to the right plan
					if($db_tmp_subscription->getPlanId() != $provider_plan->getId()) {
						//Exception
						$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." is not linked to the plan with provider_plan_uuid=".$provider_plan->getPlanUuid();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					//done
					$db_subscription = $db_tmp_subscription;
				}
			}
			if($db_subscription == NULL)
			{
				//subscription creating provider side
				config::getLogger()->addInfo("subscription creating...provider creating...");
				$sub_uuid = NULL;
				switch($provider->getName()) {
					case 'recurly' :
						$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
						$sub_uuid = $recurlySubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_provider_uuid, $billingInfoOpts, $subOpts);
						break;
					case 'gocardless' :
						$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler();
						$sub_uuid = $gocardlessSubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_provider_uuid, $billingInfoOpts, $subOpts);
						break;
					case 'celery' :
						$msg = "unsupported feature for provider named : ".$provider_name;
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						break;
					case 'bachat' :
						$bachatSubscriptionsHandler = new BachatSubscriptionsHandler();
						$sub_uuid = $bachatSubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_provider_uuid, $billingInfoOpts, $subOpts);
						break;
					default:
						$msg = "unsupported feature for provider named : ".$provider_name;
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						break;
				}
				config::getLogger()->addInfo("subscription creating...provider creating done successfully, provider_subscription_uuid=".$sub_uuid);
				//subscription created provider side, save it in billings database
				config::getLogger()->addInfo("subscription creating...database savings...");
				//TODO : should not have yet a switch here (later)
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					switch($provider->getName()) {
						case 'recurly' :
							$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
							$db_subscription = $recurlySubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $sub_uuid, 'api', 0);
							break;
						case 'gocardless' :
							$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler();
							$db_subscription = $gocardlessSubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $sub_uuid, 'api', 0);
							break;
						case 'celery' :
							$msg = "unsupported feature for provider named : ".$provider_name;
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							break;
						case 'bachat' :
							$bachatSubscriptionsHandler = new BachatSubscriptionsHandler();
							$db_subscription = $bachatSubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $sub_uuid, 'api', 0);
							break;
						default:
							$msg = "unsupported feature for provider named : ".$provider_name;
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							break;
					}
					//COMMIT
					pg_query("COMMIT");
					config::getLogger()->addInfo("subscription creating...database savings done successfully");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
				//CREATED
				$this->doSendSubscriptionEvent(NULL, $db_subscription);
			}
			//
			$this->doFillSubscription($db_subscription);
			//
			config::getLogger()->addInfo("subscription creating done successfully, db_subscription_id=".$db_subscription->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a subscription user for user_billing_uuid=".$user_billing_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscription creating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a subscription for user_billing_uuid=".$user_billing_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscription creating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_subscription);
	}
	
	public function doGetUserSubscriptionsByUser(User $user) {
		try {
			config::getLogger()->addInfo("subscriptions getting for userid=".$user->getId()."...");
			$subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
			$this->doFillSubscriptions($subscriptions);
			config::getLogger()->addInfo("subscriptions getting for userid=".$user->getId()." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting subscriptions for userid=".$user->getId().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscriptions getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting subscriptions for userid=".$user->getId().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscriptions getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($subscriptions);
	}
	
	public function doUpdateUserSubscriptionsByUser(User $user) {
		try {
			config::getLogger()->addInfo("dbsubscriptions updating for userid=".$user->getId()."...");
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			
			$provider = ProviderDAO::getProviderById($user->getProviderId());
			
			if($provider == NULL) {
				$msg = "unknown provider id : ".$user->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			switch($provider->getName()) {
				case 'recurly' :
					$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
					$recurlySubscriptionsHandler->doUpdateUserSubscriptions($user, $userOpts);
					break;
				case 'gocardless' :
					$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler();
					$gocardlessSubscriptionsHandler->doUpdateUserSubscriptions($user, $userOpts);
					break;
				case 'celery' :
					//nothing to do (owned)
					break;
				case 'bachat' :
					//nothing to do (owned)
				default:
					//nothing to do (unknown)
					break;
			}
			config::getLogger()->addInfo("dbsubscriptions update for userid=".$user->getId()." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while dbsubscriptions updating for userid=".$user->getId().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscriptions updating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while dbsubscriptions updating for userid=".$user->getId().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscriptions updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	public function doRenewSubscriptionByUuid($subscriptionBillingUuid, DateTime $start_date = NULL) {
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("dbsubscription renewing for subscriptionBillingUuid=".$subscriptionBillingUuid."...");
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid);
			if($db_subscription == NULL) {
				$msg = "unknown subscriptionBillingUuid : ".$subscriptionBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$provider = ProviderDAO::getProviderById($db_subscription->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider with id : ".$user->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			switch($provider->getName()) {
				case 'recurly' :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
				case 'gocardless' :
					$gocardlessSubscriptionsHandler = new GoCardlessSubscriptionsHandler();
					$db_subscription = $gocardlessSubscriptionsHandler->doRenewSubscription($db_subscription, $start_date);
					break;
				case 'celery' :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
				case 'bachat' :
					$bachatSubscriptionsHandler = new BachatSubscriptionsHandler();
					$db_subscription = $bachatSubscriptionsHandler->doRenewSubscription($db_subscription, $start_date);
					break;
				default:
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			//
			$this->doFillSubscription($db_subscription);
			//
			config::getLogger()->addInfo("dbsubscription renewing for subscriptionBillingUuid=".$subscriptionBillingUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while dbsubscription renewing for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription renewing failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while dbsubscription renewing for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription renewing failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_subscription);
	}
	
	public function doCancelSubscriptionByUuid($subscriptionBillingUuid, DateTime $cancel_date, $is_a_request = true) {
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("dbsubscription canceling for subscriptionBillingUuid=".$subscriptionBillingUuid."...");
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid);
			if($db_subscription == NULL) {
				$msg = "unknown subscriptionBillingUuid : ".$subscriptionBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$provider = ProviderDAO::getProviderById($db_subscription->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider with id : ".$user->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			switch($provider->getName()) {
				case 'recurly' :
					$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
					$db_subscription = $recurlySubscriptionsHandler->doCancelSubscription($db_subscription, $cancel_date, $is_a_request = true);
					break;
				case 'gocardless' :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
				case 'celery' :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
				case 'bachat' :
					$bachatSubscriptionsHandler = new BachatSubscriptionsHandler();
					$db_subscription = $bachatSubscriptionsHandler->doCancelSubscription($db_subscription, $cancel_date, $is_a_request = true);
					break;
				default:
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			//
			$this->doFillSubscription($db_subscription);
			//
			config::getLogger()->addInfo("dbsubscription canceling for subscriptionBillingUuid=".$subscriptionBillingUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while dbsubscription canceling for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription canceling failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while dbsubscription canceling for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription canceling failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_subscription);
	}
	
	
	protected function doFillSubscriptions($subscriptions) {
		foreach($subscriptions as $subscription) {
			$this->doFillSubscription($subscription);
		}
	}

	protected function doFillSubscription(BillingsSubscription $subscription = NULL) {
		if($subscription == NULL) {
			return;
		}
		$provider = ProviderDAO::getProviderById($subscription->getProviderId());
		if($provider == NULL) {
			$msg = "unknown provider with id : ".$user->getProviderId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		switch($provider->getName()) {
			case 'recurly' :
				$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler();
				$recurlySubscriptionsHandler->doFillSubscription($subscription);
				break;
			case 'gocardless' :
				$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler();
				$gocardlessSubscriptionsHandler->doFillSubscription($subscription);
				break;
			case 'celery' :
				$celerySubscriptionsHandler = new CelerySubscriptionsHandler();
				$celerySubscriptionsHandler->doFillSubscription($subscription);
				break;
			case 'bachat' :
				$bachatSubscriptionsHandler = new BachatSubscriptionsHandler();
				$bachatSubscriptionsHandler->doFillSubscription($subscription);
				break;
			default:
				$msg = "unsupported feature for provider named : ".$provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
	}
	
	private function checkBillingInfoOptsArray($billing_info_opts_as_array) {
		//TODO
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		try {
			config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid()."...");
			$subscription_is_new_event = false;
			//check subscription_is_new_event
			if($subscription_before_update == NULL) {
				if($subscription_after_update->getSubStatus() == 'active') {
					$subscription_is_new_event = true;
				}
			} else {
				if(
						($subscription_befor_update->getSubStatus() != 'active')
						&&
						($subscription_after_update->getSubStatus() == 'active')
				) {
					$subscription_is_new_event = true;
				}
			}
			if($subscription_is_new_event == true) {
				config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=subscription_is_new, ...");
				if(getEnv('EVENT_EMAIL_ACTIVATED') == true) {
					$eventEmailProvidersExceptionArray = explode(";", getEnv('EVENT_EMAIL_PROVIDERS_EXCEPTION'));
					$provider = ProviderDAO::getProviderById($subscription_after_update->getProviderId());
					if($provider == NULL) {
						$msg = "unknown provider with id : ".$subscription_after_update->getProviderId();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					if(!in_array($provider->getName(), $eventEmailProvidersExceptionArray)) {
						config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=subscription_is_new, sending mail...");
					    //DATA -->
					    $providerPlan = PlanDAO::getPlanById($subscription_after_update->getPlanId());
					    if($providerPlan == NULL) {
					    	$msg = "unknown plan with id : ".$subscription_after_update->getPlanId();
					    	config::getLogger()->addError($msg);
					    	throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					    }
					    $internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($providerPlan->getId()));
					    if($internalPlan == NULL) {
					    	$msg = "plan with uuid=".$provider_plan->getPlanUuid()." for provider ".$provider->getName()." is not linked to an internal plan";
					    	config::getLogger()->addError($msg);
					    	throw new Exception($msg);
					    }
					    $internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
					    $user = UserDAO::getUserById($subscription_after_update->getUserId());
					    if($user == NULL) {
					    	$msg = "unknown user with id : ".$subscription_after_update->getUserId();
					    	config::getLogger()->addError($msg);
					    	throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					    }
					    $userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
					    //DATA <--
					    //DATA SUBSTITUTION -->
					    setlocale(LC_MONETARY, 'fr_FR');//TODO : Forced to French Locale for "," in floats...
					   	$substitions = array();
					   	//provider : nothing
					   	//providerPlan : nothing
					   	//internalPlan :
					   	$substitions['%internalplanname%'] = $internalPlan->getName();
					   	$substitions['%internalplandesc%'] = $internalPlan->getDescription(); 
					   	$substitions['%amountincents%'] = $internalPlan->getAmountInCents();
					   	$amountInMoney = new Money((integer) $internalPlan->getAmountInCents(), new Currency($internalPlan->getCurrency()));
					   	$substitions['%amount%'] = money_format('%!.2n', $amountInMoney->getConvertedAmount());
					   	$substitions['%amountincentsexcltax%'] = $internalPlan->getAmountInCentsExclTax();
					   	$amountExclTaxInMoney = new Money((integer) $internalPlan->getAmountInCentsExclTax(), new Currency($internalPlan->getCurrency()));
					   	$substitions['%amountexcltax%'] = money_format('%!.2n', $amountExclTaxInMoney->getConvertedAmount());
					   	if($internalPlan->getVatRate() == NULL) {
					   		$substitions['%vat%'] = 'N/A'; 
					   	} else {
					   		$substitions['%vat%'] = $internalPlan->getVatRate().'%';
					   	}
					   	$substitions['%amountincentstax%'] = $internalPlan->getAmountInCents() - $internalPlan->getAmountInCentsExclTax();
					   	$amountTaxInMoney = new Money((integer) ($internalPlan->getAmountInCents() - $internalPlan->getAmountInCentsExclTax()), new Currency($internalPlan->getCurrency()));
					   	$substitions['%amounttax%'] = money_format('%!.2n', $amountTaxInMoney->getConvertedAmount());
					   	$substitions['%currency%'] = $internalPlan->getCurrency();
					   	$substitions['%cycle%'] = $internalPlan->getCycle();
					   	$substitions['%periodunit%'] = $internalPlan->getPeriodUnit();
					   	$substitions['%periodlength%'] = $internalPlan->getPeriodLength();
					   	//user : nothing
					   	//userOpts
					   	$substitions['%email%'] = $userOpts->getOpts()['email'];
					   	$firstname = $userOpts->getOpts()['firstName'];
					   	if($firstname == 'firstNameValue') {
					   		$firstname = '';
					   	}
					   	$substitions['%firstname%'] = $firstname;
					   	$lastname = $userOpts->getOpts()['lastName'];
					   	if($lastname == 'lastNameValue') {
					   		$lastname = '';
					   	}
					   	$substitions['%lastname%'] = $lastname;
					   	$username = $firstname;
					   	if($username == '') {
					   		$username = explode('@', $substitions['email'])[0];
					   	}
					   	$substitions['%username%'] = $username;
					   	$fullname = trim($firstname." ".$lastname);
					   	$substitions['%fullname%'] = $fullname;
					   	//DATA SUBSTITUTION <--
						$emailTo = $substitions['%email%'];
						$sendgrid = new SendGrid(getEnv('SENDGRID_API_KEY'));
						$email = new SendGrid\Email();
						$email
						->addTo($emailTo)
						->setFrom(getEnv('SENDGRID_FROM'))
						->setFromName(getEnv('SENDGRID_FROM_NAME'))
						->setSubject(' ')
						->setText(' ')
						->setHtml(' ')
						->setTemplateId(getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_NEW_ID'));
						foreach($substitions as $var => $val) {
							$email->addSubstitution($var, array($val));
						}
						if( (null !== (getEnv('SENGRID_BCC'))) && ('' !== (getEnv('SENGRID_BCC')))) {
							$email->setBcc(getEnv('SENGRID_BCC'));	
						}
						$sendgrid->send($email);
						config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=subscription_is_new, sending mail done successfully");
					}
				}
				config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=subscription_is_new, done successfully");
			}
			config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid()." done successfully");
		} catch(\SendGrid\Exception $e) {
			$msg = 'an error occurred while sending email for a new subscription event for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", error_code='.$e->getCode().", error_message=";
			$firstLoop = true;
			foreach($e->getErrors() as $er) {
				if($firstLoop == true) {
					$firstLoop = false;
					$msg.= $er;
				} else {
					$msg.= ", ".$er;
				}
			}
			config::getLogger()->addError($msg);
		} catch(Exception $e) {
			config::getLogger()->addError("an error occurred while processing subscription event for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", message=".$e->getMessage());
		}
	}
	
}

?>