<?php

use Money\Money;
use Money\Currency;
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../providers/celery/subscriptions/CelerySubscriptionsHandler.php';
require_once __DIR__ . '/../providers/recurly/subscriptions/RecurlySubscriptionsHandler.php';
require_once __DIR__ . '/../providers/gocardless/subscriptions/GocardlessSubscriptionsHandler.php';
require_once __DIR__ . '/../providers/bachat/subscriptions/BachatSubscriptionsHandler.php';
require_once __DIR__ . '/../providers/afr/subscriptions/AfrSubscriptionsHandler.php';
require_once __DIR__ . '/../providers/cashway/subscriptions/CashwaySubscriptionsHandler.php';
require_once __DIR__ . '/../providers/orange/subscriptions/OrangeSubscriptionsHandler.php';
require_once __DIR__ . '/../providers/bouygues/subscriptions/BouyguesSubscriptionsHandler.php';
require_once __DIR__ . '/../providers/stripe/subscriptions/StripeSubscriptionsHandler.php';
require_once __DIR__ . '/../providers/braintree/subscriptions/BraintreeSubscriptionsHandler.php';
require_once __DIR__ . '/../providers/netsize/subscriptions/NetsizeSubscriptionsHandler.php';
require_once __DIR__ . '/../providers/wecashup/subscriptions/WecashupSubscriptionsHandler.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../utils/BillingsException.php';
require_once __DIR__ . '/../utils/utils.php';
require_once __DIR__ . '/../providers/global/requests/ExpireSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/ProviderHandlersBuilder.php';

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
	
	public function doGetOrCreateSubscription($user_billing_uuid, $internal_plan_uuid, $subscription_provider_uuid, array $billing_info_array, array $sub_opts_array) {
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("subscription creating...");
			$billingInfo = BillingInfo::getInstance($billing_info_array);
			$billingInfo->setBillingInfoBillingUuid(guid());
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
				$subscription_billing_uuid = guid();
				$sub_uuid = NULL;
				switch($provider->getName()) {
					case 'recurly' :
						$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler($provider);
						$sub_uuid = $recurlySubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_billing_uuid, $subscription_provider_uuid, $billingInfo, $subOpts);
						break;
					case 'gocardless' :
						$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler($provider);
						$sub_uuid = $gocardlessSubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_billing_uuid, $subscription_provider_uuid, $billingInfo, $subOpts);
						break;
					case 'stripe':
						$stripeSubscriptionHandler = new StripeSubscriptionsHandler($provider);
						$billingSubscription = $stripeSubscriptionHandler->doCreateUserSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_billing_uuid, $subscription_provider_uuid, $billingInfo, $subOpts);
						$sub_uuid = $billingSubscription->getSubUid();
						break;
					case 'celery' :
						$msg = "unsupported feature for provider named : ".$provider->getName();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						break;
					case 'bachat' :
						$bachatSubscriptionsHandler = new BachatSubscriptionsHandler($provider);
						$sub_uuid = $bachatSubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_billing_uuid, $subscription_provider_uuid, $billingInfo, $subOpts);
						break;
					case 'afr' :
						$afrSubscriptionsHandler = new AfrSubscriptionsHandler($provider);
						$sub_uuid = $afrSubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_billing_uuid, $subscription_provider_uuid, $billingInfo, $subOpts);						
						break;
					case 'cashway' :
						$cashwaySubscriptionsHandler = new CashwaySubscriptionsHandler($provider);
						$sub_uuid = $cashwaySubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_billing_uuid, $subscription_provider_uuid, $billingInfo, $subOpts);
						break;
					case 'orange' :
						$msg = "unsupported feature for provider named : ".$provider->getName();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						break;
					case 'bouygues' :
						$msg = "unsupported feature for provider named : ".$provider->getName();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						break;
					case 'braintree' :
						$braintreeSubscriptionsHandler = new BraintreeSubscriptionsHandler($provider);
						$sub_uuid = $braintreeSubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_billing_uuid, $subscription_provider_uuid, $billingInfo, $subOpts);
						break;
					case 'netsize' : 
						$netsizeSubscriptionsHandler = new NetsizeSubscriptionsHandler($provider);
						$sub_uuid = $netsizeSubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_billing_uuid, $subscription_provider_uuid, $billingInfo, $subOpts);
						break;						
					case 'wecashup' :
						$wecashupSubscriptionsHandler = new WecashupSubscriptionsHandler($provider);
						$sub_uuid = $wecashupSubscriptionsHandler->doCreateUserSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_billing_uuid, $subscription_provider_uuid, $billingInfo, $subOpts);
						break;
					default:
						$msg = "unsupported feature for provider named : ".$provider->getName();
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
							$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler($provider);
							$db_subscription = $recurlySubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $billingInfo, $subscription_billing_uuid, $sub_uuid, 'api', 0);
							break;
						case 'gocardless' :
							$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler($provider);
							$db_subscription = $gocardlessSubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $billingInfo, $subscription_billing_uuid, $sub_uuid, 'api', 0);
							break;
						case 'celery' :
							$msg = "unsupported feature for provider named : ".$provider->getName();
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							break;
						case 'bachat' :
							$bachatSubscriptionsHandler = new BachatSubscriptionsHandler($provider);
							$db_subscription = $bachatSubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $billingInfo, $subscription_billing_uuid, $sub_uuid, 'api', 0);
							break;
						case 'afr' :
							$afrSubscriptionsHandler = new AfrSubscriptionsHandler($provider);
							$db_subscription = $afrSubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $billingInfo, $subscription_billing_uuid, $sub_uuid, 'api', 0);
							break;
						case 'cashway' :
							$cashwaySubscriptionsHandler = new CashwaySubscriptionsHandler($provider);
							$db_subscription = $cashwaySubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $billingInfo, $subscription_billing_uuid, $sub_uuid, 'api', 0);
							break;
						case 'orange' :
							$orangeSubscriptionsHandler = new OrangeSubscriptionsHandler($provider);
							$db_subscription = $orangeSubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $billingInfo, $subscription_billing_uuid, $sub_uuid, 'api', 0);
							break;
						case 'bouygues' :
							$bouyguesSubscriptionsHandler = new BouyguesSubscriptionsHandler($provider);
							$db_subscription = $bouyguesSubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $billingInfo, $subscription_billing_uuid, $sub_uuid, 'api', 0);
							break;
						case 'stripe':
							$stripeSubscriptionHandler = new StripeSubscriptionsHandler($provider);
							$db_subscription = $stripeSubscriptionHandler->createDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts,  $billingInfo, $subscription_billing_uuid, $billingSubscription, 'api', 0);
							break;
						case 'braintree' :
							$braintreeSubscriptionsHandler = new BraintreeSubscriptionsHandler($provider);
							$db_subscription = $braintreeSubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $billingInfo, $subscription_billing_uuid, $sub_uuid, 'api', 0);
							break;
						case 'netsize' :
							$netsizeSubscriptionsHandler = new NetsizeSubscriptionsHandler($provider);
							$db_subscription = $netsizeSubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $billingInfo, $subscription_billing_uuid, $sub_uuid, 'api', 0);
							break;
						case 'wecashup' :
							$wecashupSubscriptionsHandler = new WecashupSubscriptionsHandler($provider);
							$db_subscription = $wecashupSubscriptionsHandler->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $billingInfo, $subscription_billing_uuid, $sub_uuid, 'api', 0);
							break;
						default:
							$msg = "record new: unsupported feature for provider named : ".$provider->getName();
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
		$subscriptions = NULL;
		try {
			config::getLogger()->addInfo("subscriptions getting for userid=".$user->getId()."...");
			$provider = ProviderDAO::getProviderById($user->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider id : ".$user->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			switch($provider->getName()) {
				case 'celery' :
					$subscriptionsHandler = new CelerySubscriptionsHandler($provider);			
					$subscriptions = $subscriptionsHandler->doGetUserSubscriptions($user);
					break;
				case 'recurly' :
					$subscriptionsHandler = new RecurlySubscriptionsHandler($provider);
					$subscriptions = $subscriptionsHandler->doGetUserSubscriptions($user);
					break;
				case 'gocardless' :
					$subscriptionsHandler = new GocardlessSubscriptionsHandler($provider);
					$subscriptions = $subscriptionsHandler->doGetUserSubscriptions($user);
					break;
				case 'bachat' :
					$subscriptionsHandler = new BachatSubscriptionsHandler($provider);
					$subscriptions = $subscriptionsHandler->doGetUserSubscriptions($user);
					break;
				case 'afr' :
					$subscriptionsHandler = new AfrSubscriptionsHandler($provider);
					$subscriptions = $subscriptionsHandler->doGetUserSubscriptions($user);
					break;
				case 'cashway' :
					$subscriptionsHandler = new CashwaySubscriptionsHandler($provider);
					$subscriptions = $subscriptionsHandler->doGetUserSubscriptions($user);
					break;
				case 'orange' :
					$subscriptionsHandler = new OrangeSubscriptionsHandler($provider);
					$subscriptions = $subscriptionsHandler->doGetUserSubscriptions($user);
					break;
				case 'bouygues' :
					$subscriptionsHandler = new BouyguesSubscriptionsHandler($provider);
					$subscriptions = $subscriptionsHandler->doGetUserSubscriptions($user);
					break;
				case 'stripe' :
					$subscriptionsHandler = new StripeSubscriptionsHandler($provider);
					$subscriptions = $subscriptionsHandler->doGetUserSubscriptions($user);
					break;
				case 'braintree' :
					$subscriptionsHandler = new BraintreeSubscriptionsHandler($provider);
					$subscriptions = $subscriptionsHandler->doGetUserSubscriptions($user);
					break;
				case 'netsize' :
					$subscriptionsHandler = new NetsizeSubscriptionsHandler($provider);
					$subscriptions = $subscriptionsHandler->doGetUserSubscriptions($user);
					break;
				case 'wecashup' :
					$subscriptionsHandler = new WecashupSubscriptionsHandler($provider);
					$subscriptions = $subscriptionsHandler->doGetUserSubscriptions($user);
					break;
				default:
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			$usersRequestsLog = new UsersRequestsLog();
			$usersRequestsLog->setUserId($user->getId());
			$usersRequestsLog = UsersRequestsLogDAO::addUsersRequestsLog($usersRequestsLog);
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
		doSortSubscriptions($subscriptions);
		return($subscriptions);
	}
	
	public function doGetUserSubscriptionsByUserReferenceUuid($userReferenceUuid) {
		$subscriptions = array();
		try {
			config::getLogger()->addInfo("subscriptions getting for userReferenceUuid=".$userReferenceUuid."...");
			$users = UserDAO::getUsersByUserReferenceUuid($userReferenceUuid);
			foreach ($users as $user) {
				$provider = ProviderDAO::getProviderById($user->getProviderId());
				$currentProviderSubscriptionsHandler = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
				array_push($subscriptions, $currentProviderSubscriptionsHandler->doGetUserSubscriptions($user));
			}
			config::getLogger()->addInfo("subscriptions getting for userReferenceUuid=".$userReferenceUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting subscriptions for userReferenceUuid=".$userReferenceUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscriptions getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting subscriptions for userReferenceUuid=".$userReferenceUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscriptions getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		doSortSubscriptions($subscriptions);
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
					$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler($provider);
					$recurlySubscriptionsHandler->doUpdateUserSubscriptions($user, $userOpts);
					break;
				case 'gocardless' :
					$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler($provider);
					$gocardlessSubscriptionsHandler->doUpdateUserSubscriptions($user, $userOpts);
					break;
				case 'celery' :
					//nothing to do (owned)
					break;
				case 'bachat' :
					//nothing to do (owned)
					break;
				case 'afr' :
					//nothing to do (owned)
					break;
				case 'cashway' :
					//nothing to do (owned)
					break;
				case 'orange' :
					$orangeSubscriptionsHandler = new OrangeSubscriptionsHandler($provider);
					$orangeSubscriptionsHandler->doUpdateUserSubscriptions($user, $userOpts);
					break;
				case 'bouygues' :
					$bouyguesSubscriptionsHandler = new BouyguesSubscriptionsHandler($provider);
					$bouyguesSubscriptionsHandler->doUpdateUserSubscriptions($user, $userOpts);
					break;
				case 'stripe':
					$stripeSubscriptionHandler = new StripeSubscriptionsHandler($provider);
					$stripeSubscriptionHandler->doUpdateUserSubscriptions($user, $userOpts);
					break;
				case 'braintree' :
					$braintreeSubscriptionsHandler = new BraintreeSubscriptionsHandler($provider);
					$braintreeSubscriptionsHandler->doUpdateUserSubscriptions($user, $userOpts);
					break;
				case 'netsize' :
					$netsizeSubscriptionsHandler = new NetsizeSubscriptionsHandler($provider);
					$netsizeSubscriptionsHandler->doUpdateUserSubscriptions($user, $userOpts);
					break;
				case 'wecashup' :
					$wecashupSubscriptionsHandler = new WecashupSubscriptionsHandler($provider);
					$wecashupSubscriptionsHandler->doUpdateUserSubscriptions($user, $userOpts);
					break;
				default:
					//nothing to do (unknown)
					break;
			}
			config::getLogger()->addInfo("dbsubscriptions updating for userid=".$user->getId()." done successfully");
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
	
	public function doUpdateUserSubscriptionByUuid($subscriptionBillingUuid) {
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("dbsubscription updating for subscriptionBillingUuid=".$subscriptionBillingUuid."...");
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid);
			if($db_subscription == NULL) {
				$msg = "unknown subscriptionBillingUuid : ".$subscriptionBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}	
			$provider = ProviderDAO::getProviderById($db_subscription->getProviderId());	
			if($provider == NULL) {
				$msg = "unknown provider id : ".$db_subscription->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_subscription_before_update = clone $db_subscription;
			switch($provider->getName()) {
				case 'netsize' :
					$netsizeSubscriptionsHandler = new NetsizeSubscriptionsHandler($provider);
					$db_subscription = $netsizeSubscriptionsHandler->doUpdateUserSubscription($db_subscription);
					break;
				default:
					//nothing to do (unknown)
					break;
			}
			//
			$this->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
			config::getLogger()->addInfo("dbsubscription updating for subscriptionBillingUuid=".$subscriptionBillingUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while dbsubscription updating for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription updating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while dbsubscription updating for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_subscription);
	}
	
	public function doRenewSubscriptionByUuid($subscriptionBillingUuid, DateTime $start_date = NULL, DateTime $end_date = NULL) {
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
				$msg = "unknown provider with id : ".$db_subscription->getProviderId();
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
					$db_subscription = $gocardlessSubscriptionsHandler->doRenewSubscription($db_subscription, $start_date, $end_date);
					break;
				case 'celery' :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
				case 'bachat' :
					$bachatSubscriptionsHandler = new BachatSubscriptionsHandler($provider);
					$db_subscription = $bachatSubscriptionsHandler->doRenewSubscription($db_subscription, $start_date, $end_date);
					break;
				case 'orange' :
					$orangeSubscriptionHandler = new OrangeSubscriptionsHandler($provider);
					$db_subscription = $orangeSubscriptionHandler->doRenewSubscription($db_subscription, $start_date, $end_date);
					break;
				case 'bouygues' :
					$bouyguesSubscriptionsHandler = new BouyguesSubscriptionsHandler($provider);
					$db_subscription = $bouyguesSubscriptionsHandler->doRenewSubscription($db_subscription, $start_date, $end_date);
					break;
				case 'netsize' :
					$netsizeSubscriptionsHandler = new NetsizeSubscriptionsHandler($provider);
					$db_subscription = $netsizeSubscriptionsHandler->doRenewSubscription($db_subscription, $start_date, $end_date);
					break;
				default:
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
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
		$starttime = microtime(true);
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
				$msg = "unknown provider with id : ".$db_subscription->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_subscription_before_update = clone $db_subscription;
			switch($provider->getName()) {
				case 'recurly' :
					$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler($provider);
					$db_subscription = $recurlySubscriptionsHandler->doCancelSubscription($db_subscription, $cancel_date, $is_a_request);
					break;
				case 'gocardless' :
					$gocardlessSubscriptionsHandler = new GocardlessSubscriptionsHandler($provider);
					$db_subscription = $gocardlessSubscriptionsHandler->doCancelSubscription($db_subscription, $cancel_date, $is_a_request);
					break;
				case 'celery' :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
				case 'bachat' :
					$bachatSubscriptionsHandler = new BachatSubscriptionsHandler($provider);
					$db_subscription = $bachatSubscriptionsHandler->doCancelSubscription($db_subscription, $cancel_date, $is_a_request);
					break;
				case 'stripe':
					$stripeSubscriptionHandler = new StripeSubscriptionsHandler($provider);
					$db_subscription = $stripeSubscriptionHandler->doCancelSubscription($db_subscription, $cancel_date);
					break;
				case 'braintree' :
					$braintreeSubscriptionsHandler = new BraintreeSubscriptionsHandler($provider);
					$db_subscription = $braintreeSubscriptionsHandler->doCancelSubscription($db_subscription, $cancel_date);
					break;
				case 'netsize' :
					$netsizeSubscriptionsHandler = new NetsizeSubscriptionsHandler($provider);
					$db_subscription = $netsizeSubscriptionsHandler->doCancelSubscription($db_subscription, $cancel_date, $is_a_request);
					break;
				case 'afr' :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
				case 'wecashup' :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
				default:
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			//
			$this->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
			config::getLogger()->addInfo("dbsubscription canceling for subscriptionBillingUuid=".$subscriptionBillingUuid." done successfully");
		} catch(BillingsException $e) {
			BillingStatsd::inc('route.providers.all.subscriptions.cancel.error');
			$msg = "a billings exception occurred while dbsubscription canceling for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription canceling failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			BillingStatsd::inc('route.providers.all.subscriptions.cancel.error');
			$msg = "an unknown exception occurred while dbsubscription canceling for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription canceling failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		} finally {
			BillingStatsd::inc('route.providers.all.subscriptions.cancel.hit');
			$responseTimeInMillis = round((microtime(true) - $starttime) * 1000);
			BillingStatsd::timing('route.providers.all.subscriptions.cancel.responsetime', $responseTimeInMillis);
		}
		return($db_subscription);
	}
	
	public function doDeleteSubscriptionByUuid($subscriptionBillingUuid, $is_a_request = true) {
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("dbsubscription deleting for subscriptionBillingUuid=".$subscriptionBillingUuid."...");
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid);
			if($db_subscription == NULL) {
				$msg = "unknown subscriptionBillingUuid : ".$subscriptionBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$provider = ProviderDAO::getProviderById($db_subscription->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider with id : ".$db_subscription->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_subscription_before_update = clone $db_subscription;
			switch($provider->getName()) {
				case 'cashway' :
					$cashwaySubscriptionsHandler = new CashwaySubscriptionsHandler($provider);
					$db_subscription = $cashwaySubscriptionsHandler->doDeleteSubscription($db_subscription, $is_a_request);
					break;
				default:
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			//
			$this->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
			config::getLogger()->addInfo("dbsubscription deleting for subscriptionBillingUuid=".$subscriptionBillingUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while dbsubscription deleting for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription deleting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while dbsubscription deleting for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription deleting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_subscription);
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		try {
			config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid()."...");
			$subscription_is_new_event = false;
			$subscription_is_canceled_event = false;
			$subscription_is_expired_event = false;
			$sendgrid_template_id = NULL;
			$event = NULL;
			//check subscription_is_new_event
			if($subscription_before_update == NULL) {
				if($subscription_after_update->getSubStatus() == 'active') {
					$subscription_is_new_event = true;
				}
			} else {
				if(
					($subscription_before_update->getSubStatus() != 'active')
					&&
					($subscription_after_update->getSubStatus() == 'active')
				)	{
					$subscription_is_new_event = true;
				}
			}
			if($subscription_is_new_event == true) {
				$sendgrid_template_id = getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_NEW_ID');
				$event = "subscription_is_new";
			}
			//check subscription_is_canceled_event
			if($subscription_before_update == NULL) {
				if($subscription_after_update->getSubStatus() == 'canceled') {
					$subscription_is_canceled_event = true;
				}
			} else {
				if(
					($subscription_before_update->getSubStatus() != 'canceled')
					&&
					($subscription_after_update->getSubStatus() == 'canceled')
				)	{
						$subscription_is_canceled_event = true;
				}
			}
			if($subscription_is_canceled_event == true) {
				$sendgrid_template_id = getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_CANCEL_ID');
				$event = "subscription_is_canceled";
			}
			//check subscription_is_expired_event
			if($subscription_before_update == NULL) {
				if($subscription_after_update->getSubStatus() == 'expired') {
					$subscription_is_expired_event = true;
				}
			} else {
				if(
					($subscription_before_update->getSubStatus() != 'expired')
					&&
					($subscription_after_update->getSubStatus() == 'expired')
				)	{
						$subscription_is_expired_event = true;
				}
			}
			if($subscription_is_expired_event == true) {
				if($subscription_after_update->getSubExpiresDate() == $subscription_after_update->getSubCanceledDate()) {
					$sendgrid_template_id = getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_ENDED_FP_ID');//FP = FAILED PAYMENT
				} else {
					$sendgrid_template_id = getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_ENDED_ID');
				}
				$event = "subscription_is_expired";
			}
			$hasEvent = ($event != NULL);
			if($hasEvent) {
				$this->doSendEmail($subscription_after_update, $event, $sendgrid_template_id);
				switch ($event) {
					case 'subscription_is_new' :
						//nothing to do : creation is made only by API calls already traced 
						break;
					case 'subscription_is_canceled' :
						BillingStatsd::inc('route.providers.all.subscriptions.cancel.success');
						break;
					case 'subscription_is_expired' :
						BillingStatsd::inc('route.providers.all.subscriptions.expire.success');
						break;
					default :
						//nothing to do
						break;
				}
			}
			config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid()." done successfully");
		} catch(Exception $e) {
			config::getLogger()->addError("an error occurred while processing subscription event for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", message=".$e->getMessage());
		}
	}
	
	public function doReactivateSubscriptionByUuid($subscriptionBillingUuid) {
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("dbsubscription reactivating for subscriptionBillingUuid=".$subscriptionBillingUuid."...");
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid);
			if($db_subscription == NULL) {
				$msg = "unknown subscriptionBillingUuid : ".$subscriptionBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$provider = ProviderDAO::getProviderById($db_subscription->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider with id : ".$db_subscription->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_subscription_before_update = clone $db_subscription;
			switch($provider->getName()) {
				case 'recurly' :
					$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler($provider);
					$db_subscription = $recurlySubscriptionsHandler->doReactivateSubscription($db_subscription);
					break;
				case 'stripe':
					$stripeSubscriptionHandler = new StripeSubscriptionsHandler($provider);
					$stripeSubscriptionHandler->doReactivateSubscription($db_subscription);
					break;
				default:
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			//
			$this->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
			config::getLogger()->addInfo("dbsubscription reactivating for subscriptionBillingUuid=".$subscriptionBillingUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while dbsubscription reactivating for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription reactivating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while dbsubscription reactivating for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription reactivating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_subscription);
	}
	
	public function doUpdateInternalPlanByUuid($subscriptionBillingUuid, $internalPlanUuid) {
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("dbsubscription updating internalPlan for subscriptionBillingUuid=".$subscriptionBillingUuid."...");
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid);
			if($db_subscription == NULL) {
				$msg = "unknown subscriptionBillingUuid : ".$subscriptionBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$provider = ProviderDAO::getProviderById($db_subscription->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider with id : ".$db_subscription->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid);
			if($internalPlan == NULL) {
				$msg = "unknown internalPlanUuid : ".$internalPlanUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
			$providerPlanId = InternalPlanLinksDAO::getProviderPlanIdFromInternalPlanId($internalPlan->getId(), $provider->getId());
			if($providerPlanId == NULL) {
				$msg = "unknown plan : ".$internalPlan->getInternalPlanUuid()." for provider : ".$provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerPlan = PlanDAO::getPlanById($providerPlanId);
			if($providerPlan == NULL) {
				$msg = "unknown plan with id : ".$providerPlanId;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerPlanOpts = PlanOptsDAO::getPlanOptsByPlanId($providerPlan->getId());
			$db_subscription_before_update = clone $db_subscription;
			switch($provider->getName()) {
				case 'recurly' :
					$recurlySubscriptionsHandler = new RecurlySubscriptionsHandler($provider);
					$db_subscription = $recurlySubscriptionsHandler->doUpdateInternalPlan($db_subscription, $internalPlan, $internalPlanOpts, $providerPlan, $providerPlanOpts);
					break;
				case 'stripe':
					$stripeSubscriptionHandler = new StripeSubscriptionsHandler($provider);
					$stripeSubscriptionHandler->doUpdateInternalPlan($db_subscription, $internalPlan, $internalPlanOpts, $providerPlan, $providerPlanOpts);
					break;
				case 'braintree' :
					$braintreeSubscriptionsHandler = new BraintreeSubscriptionsHandler($provider);
					$db_subscription = $braintreeSubscriptionsHandler->doUpdateInternalPlan($db_subscription, $internalPlan, $internalPlanOpts, $providerPlan, $providerPlanOpts);
					break;
				default:
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			//
			$this->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
			config::getLogger()->addInfo("dbsubscription updating internalPlan for subscriptionBillingUuid=".$subscriptionBillingUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while dbsubscription updating internalPlan for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription updating internalPlan failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while dbsubscription updating internalPlan for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription updating internalPlan failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_subscription);
	}
	
	private function doSendEmail($subscription_after_update, $event, $sendgrid_template_id) {
		try {
			config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=".$event.", sending mail...");
			if(getEnv('EVENT_EMAIL_ACTIVATED') != 1) {
				config::getLogger()->addInfo("event by email : email is inactive");
				return;
			}
			if(empty($sendgrid_template_id)) {
				config::getLogger()->addInfo("event by email : no template found for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=".$event);
				return;
			}
			$eventEmailProvidersExceptionArray = explode(";", getEnv('EVENT_EMAIL_PROVIDERS_EXCEPTION'));
			$provider = ProviderDAO::getProviderById($subscription_after_update->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider with id : ".$subscription_after_update->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(in_array($provider->getName(), $eventEmailProvidersExceptionArray)) {
				config::getLogger()->addInfo("event by email : ignored for providerName=".$provider->getName()." for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=".$event);
				return;
			}
			$user = UserDAO::getUserById($subscription_after_update->getUserId());
			if($user == NULL) {
				$msg = "unknown user with id : ".$subscription_after_update->getUserId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($event == "subscription_is_expired") {
				if($this->hasFutureSubscription($user, $subscription_after_update)) {
					config::getLogger()->addInfo("event by email : ignored - has a future subscription - for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=".$event);
					return;
				}
			}
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			$emailTo = NULL;
			if(array_key_exists('email', $userOpts->getOpts())) {
				$emailTo = $userOpts->getOpts()['email'];
			}
			//DATA -->
			$providerPlan = PlanDAO::getPlanById($subscription_after_update->getPlanId());
			if($providerPlan == NULL) {
				$msg = "unknown plan with id : ".$subscription_after_update->getPlanId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($providerPlan->getId()));
			if($internalPlan == NULL) {
				$msg = "plan with uuid=".$providerPlan->getPlanUuid()." for provider ".$provider->getName()." is not linked to an internal plan";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
			$userInternalCoupon = BillingUserInternalCouponDAO::getBillingUserInternalCouponBySubId($subscription_after_update->getId());
			$internalCoupon = NULL;
			$internalCouponsCampaign = NULL;
			if(isset($userInternalCoupon)) {
				$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponById($userInternalCoupon->getInternalCouponsId());
				$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($internalCoupon->getInternalCouponsCampaignsId());
			}
			//DATA <--
			//DATA SUBSTITUTION -->
			setlocale(LC_MONETARY, 'fr_FR.utf8');//TODO : Forced to French Locale for "," in floats...
			$substitions = array();
			//user
			$substitions['%userreferenceuuid%'] = $user->getUserReferenceUuid();
			$substitions['%userbillinguuid%'] = $user->getUserBillingUuid();
			//provider : nothing
			//providerPlan : nothing
			//internalPlan :
			$substitions['%internalplanname%'] = $internalPlan->getName();
			$substitions['%internalplandesc%'] = $internalPlan->getDescription();
			$substitions['%amountincents%'] = $internalPlan->getAmountInCents();
			$amountInMoney = new Money((integer) $internalPlan->getAmountInCents(), new Currency($internalPlan->getCurrency()));
			$substitions['%amount%'] = money_format('%!.2n', (float) ($amountInMoney->getAmount() / 100));
			$substitions['%amountincentsexcltax%'] = $internalPlan->getAmountInCentsExclTax();
			$amountExclTaxInMoney = new Money((integer) $internalPlan->getAmountInCentsExclTax(), new Currency($internalPlan->getCurrency()));
			$substitions['%amountexcltax%'] = money_format('%!.2n', (float) ($amountExclTaxInMoney->getAmount() / 100));
			if($internalPlan->getVatRate() == NULL) {
				$substitions['%vat%'] = 'N/A';
			} else {
				$substitions['%vat%'] = number_format($internalPlan->getVatRate(), 2, ',', '').'%';
			}
			$substitions['%amountincentstax%'] = $internalPlan->getAmountInCents() - $internalPlan->getAmountInCentsExclTax();
			$amountTaxInMoney = new Money((integer) ($internalPlan->getAmountInCents() - $internalPlan->getAmountInCentsExclTax()), new Currency($internalPlan->getCurrency()));
			$substitions['%amounttax%'] = money_format('%!.2n', (float) ($amountTaxInMoney->getAmount() / 100));
			$substitions['%currency%'] = $internalPlan->getCurrencyForDisplay();
			$substitions['%cycle%'] = $internalPlan->getCycle();
			$substitions['%periodunit%'] = $internalPlan->getPeriodUnit();
			$substitions['%periodlength%'] = $internalPlan->getPeriodLength();
			//user : nothing
			//userOpts
			$substitions['%email%'] = ($emailTo == NULL ? '' : $emailTo);
			$firstname = '';
			if(array_key_exists('firstName', $userOpts->getOpts())) {
				$firstname = $userOpts->getOpts()['firstName'];
			}
			if($firstname == 'firstNameValue') {
				$firstname = '';
			}
			$substitions['%firstname%'] = $firstname;
			$lastname = '';
			if(array_key_exists('lastName', $userOpts->getOpts())) {
				$lastname = $userOpts->getOpts()['lastName'];
			}
			if($lastname == 'lastNameValue') {
				$lastname = '';
			}
			$substitions['%lastname%'] = $lastname;
			$username = $firstname;
			if($username == '') {
				if(!empty($emailTo)) {
					$username = explode('@', $emailTo)[0];
				}
			}
			$substitions['%username%'] = $username;
			$fullname = trim($firstname." ".$lastname);
			$substitions['%fullname%'] = $fullname;
			//subscription
			$substitions['%subscriptionbillinguuid%'] = $subscription_after_update->getSubscriptionBillingUuid();
			//Coupon
			$substitions['%couponCode%'] = '';
			$substitions['%couponAmountForDisplay%'] = '';
			$substitions['%couponDetails%'] = '';
			$substitions['%couponAppliedSentence%'] = '';
			if(isset($internalCouponsCampaign) && $internalCouponsCampaign->getCouponType() == 'promo') {
				$couponAmountForDisplay = '';
				switch($internalCouponsCampaign->getDiscountType()) {
					case 'percent' :
						$couponAmountForDisplay = $internalCouponsCampaign->getPercent().'%';
						break;
					case 'amount' :
						$couponAmountForDisplay = new Money((integer) $internalCouponsCampaign->getAmountInCents(), new Currency($internalCouponsCampaign->getCurrency()));
						$couponAmountForDisplay = money_format('%!.2n', (float) ($couponAmountForDisplay->getAmount() / 100));
						$couponAmountForDisplay = $couponAmountForDisplay.' '.dbGlobal::getCurrencyForDisplay($internalCouponsCampaign->getCurrency());  
						break;
				}
				$substitions['%couponCode%'] = $userInternalCoupon->getCode();
				$substitions['%couponAmountForDisplay%'] = $couponAmountForDisplay;
				$substitions['%couponDetails%'] = $internalCouponsCampaign->getDescription();
				$couponAppliedSentence = getEnv('SENDGRID_VAR_couponAppliedSentence');
				$couponAppliedSentence = str_replace(array_keys($substitions), array_values($substitions), $couponAppliedSentence);
				$substitions['%couponAppliedSentence%'] = $couponAppliedSentence;
			}
			//DATA SUBSTITUTION <--
			$sendgrid = new SendGrid(getEnv('SENDGRID_API_KEY'));
			$email = new SendGrid\Email();
			$email->addTo(!empty($emailTo) ? $emailTo : getEnv('SENDGRID_TO_IFNULL'));
			$email
			->setFrom(getEnv('SENDGRID_FROM'))
			->setFromName(getEnv('SENDGRID_FROM_NAME'))
			->setSubject(' ')
			->setText(' ')
			->setHtml(' ')
			->setTemplateId($sendgrid_template_id);
			if((null !== (getEnv('SENDGRID_BCC'))) && ('' !== (getEnv('SENDGRID_BCC')))) {
				$email->setBcc(getEnv('SENDGRID_BCC'));
				foreach($substitions as $var => $val) {
					$vals = array($val, $val);//Bcc (same value twice (To + Bcc))
					$email->addSubstitution($var, $vals);
				}
			} else {
				foreach($substitions as $var => $val) {
					$email->addSubstitution($var, array($val));//once (To)
				}
			}
			$sendgrid->send($email);
			config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=".$event.", sending mail done successfully");
		} catch(\SendGrid\Exception $e) {
			$msg = 'an error occurred while sending email for a new subscription event for subscriptionBillingUuid='.$subscription_after_update->getSubscriptionBillingUuid().', event='.$event.', error_code='.$e->getCode().', error_message=';
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
			throw $e;
		} catch(Exception $e) {
			$msg = 'an error occurred while sending email for a new subscription event for subscriptionBillingUuid='.$subscription_after_update->getSubscriptionBillingUuid().', event='.$event.', error_code='.$e->getCode().', error_message=';
			config::getLogger()->addError($msg);
			throw $e;			
		}
	}
	
	private function hasFutureSubscription(User $user, BillingsSubscription $currentBillingsSubscription) {
		$subscriptions = $this->doGetUserSubscriptionsByUserReferenceUuid($user->getUserReferenceUuid());
		if(count($subscriptions) > 0) {
			foreach ($subscriptions as $subscription) {
				if($subscription->getId() != $currentBillingsSubscription->getId()) {
					if($subscription->getSubStatus() == 'future') {
						//NC : Quite risky : For now, we do not filter with dates. We wait for first expirations to decide if we filter or not
						/*$futureSubActivatedDate = $subscription->getSubActivatedDate();
						$currentSubExpiresDate = $currentBillingsSubscription->getSubExpiresDate();
						$diffInSeconds = $futureSubActivatedDate->format('U') - $currentSubExpiresDate->format('U');
						if($diffInSeconds == 0) {
							return(true);
						}*/
						return(true);
					}
				}
			}
		}
		return(false);
	}
	
	public function doExpireSubscription(ExpireSubscriptionRequest $expireSubscriptionRequest) {
		$starttime = microtime(true);
		$subscriptionBillingUuid = $expireSubscriptionRequest->getSubscriptionBillingUuid();
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("dbsubscription expiring for subscriptionBillingUuid=".$subscriptionBillingUuid."...");
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid);
			if($db_subscription == NULL) {
				$msg = "unknown subscriptionBillingUuid : ".$subscriptionBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$provider = ProviderDAO::getProviderById($db_subscription->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider with id : ".$db_subscription->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_subscription_before_update = clone $db_subscription;
			//
			$providerSubscriptionsHandlerInstance = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
			$db_subscription = $providerSubscriptionsHandlerInstance->doExpireSubscription($db_subscription, $expireSubscriptionRequest);
			//
			$this->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
			config::getLogger()->addInfo("dbsubscription expiring for subscriptionBillingUuid=".$subscriptionBillingUuid." done successfully");
		} catch(BillingsException $e) {
			BillingStatsd::inc('route.providers.all.subscriptions.expire.error');
			$msg = "a billings exception occurred while dbsubscription expiring for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription expiring failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			BillingStatsd::inc('route.providers.all.subscriptions.expire.error');
			$msg = "an unknown exception occurred while dbsubscription expiring for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("dbsubscription expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		} finally {
			BillingStatsd::inc('route.providers.all.subscriptions.expire.hit');
			$responseTimeInMillis = round((microtime(true) - $starttime) * 1000);
			BillingStatsd::timing('route.providers.all.subscriptions.expire.responsetime', $responseTimeInMillis);
		}
		return($db_subscription);
	}
	
}

?>