<?php

require_once __DIR__ . '/../../config/config.php';
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
require_once __DIR__ . '/../providers/global/requests/UpdateSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetOrCreateSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetUserSubscriptionsRequest.php';
require_once __DIR__ . '/../providers/global/requests/RedeemCouponRequest.php';
require_once __DIR__ . '/../providers/global/ProviderHandlersBuilder.php';
require_once __DIR__ . '/../slack/SlackHandler.php';

class SubscriptionsHandler {
	
	public function __construct() {
	}
	
	public function doGetSubscription(GetSubscriptionRequest $getSubscriptionRequest) {
		$subscriptionBillingUuid = $getSubscriptionRequest->getSubscriptionBillingUuid();
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("subscription getting for subscriptionBillingUuid=".$subscriptionBillingUuid."...");
			//
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid, $getSubscriptionRequest->getPlatform()->getId());
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
	
	public function doGetOrCreateSubscription(GetOrCreateSubscriptionRequest $getOrCreateSubscriptionRequest) {
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("subscription creating...");
			$billingInfo = BillingInfo::getInstance($getOrCreateSubscriptionRequest->getBillingInfoArray());
			$billingInfo->setBillingInfoBillingUuid(guid());
			$subOpts = new BillingsSubscriptionOpts();
			$subOpts->setOpts($getOrCreateSubscriptionRequest->getSubOptsArray());
			$user = UserDAO::getUserByUserBillingUuid($getOrCreateSubscriptionRequest->getUserBillingUuid(), $getOrCreateSubscriptionRequest->getPlatform()->getId());
			if($user == NULL) {
				$msg = "unknown user_billing_uuid : ".$getOrCreateSubscriptionRequest->getUserBillingUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			
			$internal_plan = InternalPlanDAO::getInternalPlanByUuid($getOrCreateSubscriptionRequest->getInternalPlanUuid(), $getOrCreateSubscriptionRequest->getPlatform()->getId());
			if($internal_plan == NULL) {
				$msg = "unknown internal_plan_uuid : ".$getOrCreateSubscriptionRequest->getInternalPlanUuid();
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
			
			$provider_plan = PlanDAO::getPlanByInternalPlanId($internal_plan->getId(), $provider->getId());
			if($provider_plan == NULL) {
				$msg = "unknown plan : ".$getOrCreateSubscriptionRequest->getInternalPlanUuid()." for provider : ".$provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$provider_plan_opts = PlanOptsDAO::getPlanOptsByPlanId($provider_plan->getId());
			if($getOrCreateSubscriptionRequest->getSubscriptionProviderUuid() != NULL) {
				//check : Does this subscription_provider_uuid already exist in the Database ?
				$db_tmp_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($provider->getId(), $getOrCreateSubscriptionRequest->getSubscriptionProviderUuid());
				if($db_tmp_subscription == NULL) {
					//nothing to do
				} else {
					//check if it is linked to the right user
					if($db_tmp_subscription->getUserId() != $user->getId()) {
						//Exception
						$msg = "subscription with subscription_provider_uuid=".$getOrCreateSubscriptionRequest->getSubscriptionProviderUuid()." is already linked to another user_reference_uuid";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					//check if it is linked to the right plan
					if($db_tmp_subscription->getPlanId() != $provider_plan->getId()) {
						//Exception
						$msg = "subscription with subscription_provider_uuid=".$getOrCreateSubscriptionRequest->getSubscriptionProviderUuid()." is not linked to the plan with provider_plan_uuid=".$provider_plan->getPlanUuid();
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
				$sub_uuid = $providerSubscriptionsHandlerInstance->doCreateUserSubscription($user, $userOpts, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subscription_billing_uuid, $getOrCreateSubscriptionRequest->getSubscriptionProviderUuid(), $billingInfo, $subOpts);
				config::getLogger()->addInfo("subscription creating...provider creating done successfully, provider_subscription_uuid=".$sub_uuid);
				//subscription created provider side, save it in billings database
				config::getLogger()->addInfo("subscription creating...database savings...");
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					$db_subscription = $providerSubscriptionsHandlerInstance->createDbSubscriptionFromApiSubscriptionUuid($user, $userOpts, $internal_plan, $internal_plan_opts, $provider_plan, $provider_plan_opts, $subOpts, $billingInfo, $subscription_billing_uuid, $sub_uuid, 'api', 0);
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
			$msg = "a billings exception occurred while creating a subscription user for user_billing_uuid=".$getOrCreateSubscriptionRequest->getUserBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscription creating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a subscription for user_billing_uuid=".$getOrCreateSubscriptionRequest->getUserBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscription creating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_subscription);
	}
	
	public function doGetUserSubscriptionsByUser(GetUserSubscriptionsRequest $getUserSubscriptionsRequest) {
		$subscriptions = NULL;
		try {
			config::getLogger()->addInfo("subscriptions getting for userBillingUuid=".$getUserSubscriptionsRequest->getUserBillingUuid()."...");
			$user = UserDAO::getUserByUserBillingUuid($getUserSubscriptionsRequest->getUserBillingUuid(), $getUserSubscriptionsRequest->getPlatform()->getId());
			if($user == NULL) {
				$msg = "unknown userBillingUuid : ".$getUserSubscriptionsRequest->getUserBillingUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$provider = ProviderDAO::getProviderById($user->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider id : ".$user->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerSubscriptionsHandlerInstance = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
			$subscriptions = $providerSubscriptionsHandlerInstance->doGetUserSubscriptions($user);
			$usersRequestsLog = new UsersRequestsLog();
			$usersRequestsLog->setUserId($user->getId());
			$usersRequestsLog = UsersRequestsLogDAO::addUsersRequestsLog($usersRequestsLog);
			config::getLogger()->addInfo("subscriptions getting for userBillingUuid=".$getUserSubscriptionsRequest->getUserBillingUuid()." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting subscriptions for userBillingUuid=".$getUserSubscriptionsRequest->getUserBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscriptions getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting subscriptions for userBillingUuid=".$getUserSubscriptionsRequest->getUserBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscriptions getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		doSortSubscriptions($subscriptions);
		return($subscriptions);
	}
	
	public function doGetUserSubscriptionsByUserReferenceUuid(GetSubscriptionsRequest $getSubscriptionsRequest) {
		$subscriptions = array();
		try {
			config::getLogger()->addInfo("subscriptions getting for userReferenceUuid=".$getSubscriptionsRequest->getUserReferenceUuid()."...");
			$users = UserDAO::getUsersByUserReferenceUuid($getSubscriptionsRequest->getUserReferenceUuid(), NULL, $getSubscriptionsRequest->getPlatform()->getId());
			foreach ($users as $user) {
				$getUserSubscriptionsRequest = new GetUserSubscriptionsRequest();
				$getUserSubscriptionsRequest->setOrigin('api');
				$getUserSubscriptionsRequest->setUserBillingUuid($user->getUserBillingUuid());
				$getUserSubscriptionsRequest->setPlatform($getSubscriptionsRequest->getPlatform());
				$current_subscriptions = $this->doGetUserSubscriptionsByUser($getUserSubscriptionsRequest);
				$subscriptions = array_merge($subscriptions, $current_subscriptions);
			}
			config::getLogger()->addInfo("subscriptions getting for userReferenceUuid=".$getSubscriptionsRequest->getUserReferenceUuid()." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting subscriptions for userReferenceUuid=".$getSubscriptionsRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("subscriptions getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting subscriptions for userReferenceUuid=".$getSubscriptionsRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
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
			$providerSubscriptionsHandlerInstance = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
			$providerSubscriptionsHandlerInstance->doUpdateUserSubscriptions($user, $userOpts);
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
	
	public function doUpdateUserSubscription(UpdateSubscriptionRequest $updateSubscriptionRequest) {
		$subscriptionBillingUuid = $updateSubscriptionRequest->getSubscriptionBillingUuid();
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("dbsubscription updating for subscriptionBillingUuid=".$subscriptionBillingUuid."...");
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid, $updateSubscriptionRequest->getPlatform()->getId());
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
			//
			$providerSubscriptionsHandlerInstance = ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider);
			
			$db_subscription = $providerSubscriptionsHandlerInstance->doUpdateUserSubscription($db_subscription, $updateSubscriptionRequest);
			//
			$providerSubscriptionsHandlerInstance->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
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
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid, $renewSubscriptionRequest->getPlatform()->getId());
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
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid, $cancelSubscriptionRequest->getPlatform()->getId());
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
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid, $deleteSubscriptionRequest->getPlatform()->getId());
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
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid, $reactivateSubscriptionRequest->getPlatform()->getId());
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
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid, $updateInternalPlanSubscriptionRequest->getPlatform()->getId());
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
			//<-- SLACK
			$fromInternalPlan = InternalPlanDAO::getInternalPlanByProviderPlanId($db_subscription_before_update->getPlanId());
			$userOpts = UserOptsDAO::getUserOptsByUserId($db_subscription->getUserId());
			$email = 'unknown@domain.com';
			if(array_key_exists('email', $userOpts->getOpts())) {
				$email = $userOpts->getOpts()['email'];
			}
			$separatorMsg = "**************************************************";
			$msg = $email." : plan change from : ".$fromInternalPlan->getInternalPlanUuid()." to : ".$updateInternalPlanSubscriptionRequest->getInternalPlanUuid().
			" done successfully, details : subscriptionBillingUuid=".$updateInternalPlanSubscriptionRequest->getSubscriptionBillingUuid().", timeframe=".$updateInternalPlanSubscriptionRequest->getTimeframe().
			" (".$provider->getName().")";
			$slackHandler = new SlackHandler();		
			$slackHandler->sendMessage(getEnv('SLACK_STATS_CHANNEL'), $separatorMsg);
			$slackHandler->sendMessage(getEnv('SLACK_STATS_CHANNEL'), $msg);
			$slackHandler->sendMessage(getEnv('SLACK_STATS_CHANNEL'), $separatorMsg);
			//--> SLACK
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
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid, $expireSubscriptionRequest->getPlatform()->getId());
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
	
	public function doRedeemCoupon(RedeemCouponRequest $redeemCouponRequest) {
		$subscriptionBillingUuid = $redeemCouponRequest->getSubscriptionBillingUuid();
		$db_subscription = NULL;
		try {
			config::getLogger()->addInfo("redeeming coupon for subscriptionBillingUuid=".$subscriptionBillingUuid."...");
			$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid, $redeemCouponRequest->getPlatform()->getId());
			if($db_subscription == NULL) {
				$msg = "unknown subscriptionBillingUuid : ".$subscriptionBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($db_subscription->getSubStatus() != 'active') {
				//Exception
				$msg = "a coupon can only be applied to an 'active' subscription";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($redeemCouponRequest->getForce() == NULL || $redeemCouponRequest->getForce() == false) {
				$billingUserInternalCoupons = BillingUserInternalCouponDAO::getBillingUserInternalCouponsBySubId($db_subscription->getId());
				if(count($billingUserInternalCoupons) > 0) {
					//Exception
					$msg = "a coupon has already been applied to the subscription";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_ANOTHER_ALREADY_APPLIED);				
				}
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
			$db_subscription = $providerSubscriptionsHandlerInstance->doRedeemCoupon($db_subscription, $redeemCouponRequest);
			//
			$providerSubscriptionsHandlerInstance->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription, 'REDEEM_COUPON');
			config::getLogger()->addInfo("redeeming coupon for subscriptionBillingUuid=".$subscriptionBillingUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while redeeming coupon for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("redeeming coupon failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while redeeming coupon for subscriptionBillingUuid=".$subscriptionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("redeeming coupon failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_subscription);
	}
	
}

?>