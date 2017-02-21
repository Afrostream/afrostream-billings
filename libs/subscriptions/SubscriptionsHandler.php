<?php

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
require_once __DIR__ . '/../providers/google/subscriptions/GoogleSubscriptionsHandler.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../utils/BillingsException.php';
require_once __DIR__ . '/../utils/utils.php';
require_once __DIR__ . '/../providers/global/requests/ExpireSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/ReactivateSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetSubscriptionsRequest.php';
require_once __DIR__ . '/../providers/global/requests/DeleteSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/RenewSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/UpdateInternalPlanSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/ProviderHandlersBuilder.php';

class SubscriptionsHandler {
	
	public function __construct() {
	}
	
	//doGetSubscriptionBySubscriptionBillingUuid
	public function doGetSubscription(GetSubscriptionRequest $getSubscriptionRequest) {
		$subscriptionBillingUuid = $getSubscriptionRequest->getSubscriptionBillingUuid();
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
			$provider = ProviderDAO::getProviderById($db_subscription->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider with id : ".$db_subscription->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerSubscriptionsHandlerInstance = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
			$db_subscription = $providerSubscriptionsHandlerInstance->doFillSubscription($db_subscription);
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
				$providerSubscriptionsHandlerInstance = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
				$sub_uuid = $providerSubscriptionsHandlerInstance->doCreateUserSubscription($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_billing_uuid, $subscription_provider_uuid, $billingInfo, $subOpts);
				config::getLogger()->addInfo("subscription creating...provider creating done successfully, provider_subscription_uuid=".$sub_uuid);
				//subscription created provider side, save it in billings database
				config::getLogger()->addInfo("subscription creating...database savings...");
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					$db_subscription = $providerSubscriptionsHandlerInstance->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $provider, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $billingInfo, $subscription_billing_uuid, $sub_uuid, 'api', 0);
					//COMMIT
					pg_query("COMMIT");
					config::getLogger()->addInfo("subscription creating...database savings done successfully");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
				//CREATED
				$providerSubscriptionsHandlerInstance->doSendSubscriptionEvent(NULL, $db_subscription);
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
				$subscriptions = array_merge($subscriptions, $currentProviderSubscriptionsHandler->doGetUserSubscriptions($user));
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
			$currentSubscriptionsHandler = NULL;
			switch($provider->getName()) {
				case 'netsize' :
					$currentSubscriptionsHandler = new NetsizeSubscriptionsHandler($provider);
					$db_subscription = $currentSubscriptionsHandler->doUpdateUserSubscription($db_subscription);
					break;
				default:
					//nothing to do (unknown)
					break;
			}
			//
			$currentSubscriptionsHandler->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
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
	
	public function doRenewSubscription(RenewSubscriptionRequest $renewSubscriptionRequest) {
		$subscriptionBillingUuid = $renewSubscriptionRequest->getSubscriptionBillingUuid();
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
			$db_subscription_before_update = clone $db_subscription;
			//
			$providerSubscriptionsHandlerInstance = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
			$db_subscription = $providerSubscriptionsHandlerInstance->doRenewSubscription($db_subscription, $renewSubscriptionRequest);
			//
			$providerSubscriptionsHandlerInstance->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
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
	
	public function doCancelSubscription(CancelSubscriptionRequest $cancelSubscriptionRequest) {
		$starttime = microtime(true);
		$subscriptionBillingUuid = $cancelSubscriptionRequest->getSubscriptionBillingUuid();
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
			//
			$providerSubscriptionsHandlerInstance = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
			$db_subscription = $providerSubscriptionsHandlerInstance->doCancelSubscription($db_subscription, $cancelSubscriptionRequest);
			//
			$providerSubscriptionsHandlerInstance->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
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
	
	public function doDeleteSubscription(DeleteSubscriptionRequest $deleteSubscriptionRequest) {
		$subscriptionBillingUuid = $deleteSubscriptionRequest->getSubscriptionBillingUuid();
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
			//
			$providerSubscriptionsHandlerInstance = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
			$db_subscription = $providerSubscriptionsHandlerInstance->doDeleteSubscription($db_subscription, $deleteSubscriptionRequest);
			//
			$providerSubscriptionsHandlerInstance->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
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
	
	public function doReactivateSubscription(ReactivateSubscriptionRequest $reactivateSubscriptionRequest) {
		$subscriptionBillingUuid = $reactivateSubscriptionRequest->getSubscriptionBillingUuid();
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
			//
			$providerSubscriptionsHandlerInstance = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
			$db_subscription = $providerSubscriptionsHandlerInstance->doReactivateSubscription($db_subscription, $reactivateSubscriptionRequest);
			//
			$providerSubscriptionsHandlerInstance->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
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
	
	public function doUpdateInternalPlanSubscription(UpdateInternalPlanSubscriptionRequest $updateInternalPlanSubscriptionRequest) {
		$subscriptionBillingUuid = $updateInternalPlanSubscriptionRequest->getSubscriptionBillingUuid();
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
			$db_subscription_before_update = clone $db_subscription;
			//
			$providerSubscriptionsHandlerInstance = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
			$db_subscription = $providerSubscriptionsHandlerInstance->doUpdateInternalPlanSubscription($db_subscription, $updateInternalPlanSubscriptionRequest);
			//
			$providerSubscriptionsHandlerInstance->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
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
			$providerSubscriptionsHandlerInstance->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
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