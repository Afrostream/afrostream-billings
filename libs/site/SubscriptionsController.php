<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../users/UsersHandler.php';
require_once __DIR__ . '/../subscriptions/SubscriptionsFilteredHandler.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../utils/utils.php';
require_once __DIR__ . '/../providers/global/requests/ExpireSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetUserRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetUsersRequest.php';
require_once __DIR__ . '/../providers/global/requests/ReactivateSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/CancelSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetSubscriptionRequest.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class SubscriptionsController extends BillingsController {
	
	public function getOne(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$subscriptionBillingUuid = NULL;
			if(!isset($args['subscriptionBillingUuid'])) {
				//exception
				$msg = "field 'subscriptionBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$subscriptionBillingUuid = $args['subscriptionBillingUuid'];
			//
			$subscriptionsHandler = new SubscriptionsFilteredHandler();
			$getSubscriptionRequest = new GetSubscriptionRequest();
			$getSubscriptionRequest->setSubscriptionBillingUuid($subscriptionBillingUuid);
			$getSubscriptionRequest->setOrigin('api');
			$subscription = $subscriptionsHandler->doGetSubscription($getSubscriptionRequest);
			if($subscription == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'subscription', $subscription));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting a subscription, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function getMulti(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$requestIsOk = false;
			$userReferenceUuid = NULL;
			if(isset($data['userReferenceUuid'])) {
				$requestIsOk = true;
				$userReferenceUuid = $data['userReferenceUuid'];
			}
			$userBillingUuid = NULL;
			if(isset($data['userBillingUuid'])) {
				$requestIsOk = true;
				$userBillingUuid = $data['userBillingUuid'];
			}
			if(!$requestIsOk) {
				//exception
				$msg = "field 'userReferenceUuid' or field 'userBillingUuid' are missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$subscriptions = array();
			$usersHandler = new UsersHandler();
			$subscriptionsHandler = new SubscriptionsFilteredHandler();
			if(isset($userReferenceUuid)) {
				$getUsersRequest = new GetUsersRequest();
				$getUsersRequest->setOrigin('api');
				$getUsersRequest->setUserReferenceUuid($userReferenceUuid);
				$users = $usersHandler->doGetUsers($getUsersRequest);
				if(count($users) == 0) {
					return($this->returnNotFoundAsJson($response));
				}
				foreach($users as $user) {
					$current_subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUser($user);
					$subscriptions = array_merge($subscriptions, $current_subscriptions);
				}
			} else if(isset($userBillingUuid)) {
				$getUserRequest = new GetUserRequest();
				$getUserRequest->setOrigin('api');
				$getUserRequest->setUserBillingUuid($userBillingUuid);
				$user = $usersHandler->doGetUser($getUserRequest);
				if($user == NULL) {
					return($this->returnNotFoundAsJson($response));
				}
				$subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUser($user);
			} else {
				//exception (should not happen)
				$msg = "field 'userReferenceUuid' or field 'userBillingUuid' are missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			doSortSubscriptions($subscriptions);
			return($this->returnObjectAsJson($response, 'subscriptions', $subscriptions));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting subscriptions, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting subscriptions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function create(Request $request, Response $response, array $args) {
		$starttime = microtime(true);
		try {
			$data = json_decode($request->getBody(), true);
			if(!isset($data['userBillingUuid'])) {
				//exception
				$msg = "field 'userBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!isset($data['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$billing_info_array = array();
			if(isset($data['billingInfo'])) {
				if(!is_array($data['billingInfo'])) {
					//exception
					$msg = "field 'billingInfo' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$billing_info_array = $data['billingInfo'];
			}
			$sub_opts = array();
			if(isset($data['subOpts'])) {
				if(!is_array($data['subOpts'])) {
					//exception
					$msg = "field 'subOpts' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$sub_opts = $data['subOpts'];
			}
			$user_billing_uuid = $data['userBillingUuid'];
			$internal_plan_uuid = $data['internalPlanUuid'];
			$subscription_provider_uuid = NULL;
			if(isset($data['subscriptionProviderUuid'])) {
				$subscription_provider_uuid = $data['subscriptionProviderUuid'];
			}
			$subscriptionsHandler = new SubscriptionsFilteredHandler();
			$subscription = $subscriptionsHandler->doGetOrCreateSubscription($user_billing_uuid, $internal_plan_uuid, $subscription_provider_uuid, $billing_info_array, $sub_opts);
			BillingStatsd::inc('route.api.providers.all.subscriptions.create.success');
			return($this->returnObjectAsJson($response, 'subscription', $subscription));
		} catch(BillingsException $e) {
			BillingStatsd::inc('route.api.providers.all.subscriptions.create.error');
			BillingStatsd::inc('route.api.providers.all.subscriptions.create.infos.error.code.'.$e->getCode());
			$msg = "an exception occurred while creating a subscription, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			BillingStatsd::inc('route.api.providers.all.subscriptions.create.error');
			BillingStatsd::inc('route.api.providers.all.subscriptions.create.infos.error.code.0');
			$msg = "an unknown exception occurred while creating an subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		} finally {
			BillingStatsd::inc('route.api.providers.all.subscriptions.create.hit');
			$responseTimeInMillis = round((microtime(true) - $starttime) * 1000);
			BillingStatsd::timing('route.api.providers.all.subscriptions.create.responsetime', $responseTimeInMillis);
		}
	}
	
	public function updateMulti(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			$requestIsOk = false;
			$userReferenceUuid = NULL;
			if(isset($data['userReferenceUuid'])) {
				$requestIsOk = true;
				$userReferenceUuid = $data['userReferenceUuid'];
			}
			$userBillingUuid = NULL;
			if(isset($data['userBillingUuid'])) {
				$requestIsOk = true;
				$userBillingUuid = $data['userBillingUuid'];
			}
			if(!$requestIsOk) {
				//exception
				$msg = "field 'userReferenceUuid' or field 'userBillingUuid' are missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$subscriptions = array();
			$usersHandler = new UsersHandler();
			$subscriptionsHandler = new SubscriptionsFilteredHandler();
			if(isset($userReferenceUuid)) {
				$getUsersRequest = new GetUsersRequest();
				$getUsersRequest->setOrigin('api');
				$getUsersRequest->setUserReferenceUuid($userReferenceUuid);
				$users = $usersHandler->doGetUsers($getUsersRequest);
				if(count($users) == 0) {
					return($this->returnNotFoundAsJson($response));
				}
				foreach($users as $user) {
					$subscriptionsHandler->doUpdateUserSubscriptionsByUser($user);
				}
				foreach($users as $user) {
					$current_subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUser($user);
					$subscriptions = array_merge($subscriptions, $current_subscriptions);
				}
			} else if(isset($userBillingUuid)) {
				$getUserRequest = new GetUserRequest();
				$getUserRequest->setOrigin('api');
				$getUserRequest->setUserBillingUuid($userBillingUuid);
				$user = $usersHandler->doGetUser($getUserRequest);
				if($user == NULL) {
					return($this->returnNotFoundAsJson($response));
				}
				$subscriptionsHandler->doUpdateUserSubscriptionsByUser($user);
				$subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUser($user);
			} else {
				//exception (should not happen)
				$msg = "field 'userReferenceUuid' or field 'userBillingUuid' are missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			doSortSubscriptions($subscriptions);
			return($this->returnObjectAsJson($response, 'subscriptions', $subscriptions));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while updating subscriptions, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating subscriptions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function updateOne(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['subscriptionBillingUuid'])) {
				//exception
				$msg = "field 'subscriptionBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$subscriptionBillingUuid = $args['subscriptionBillingUuid'];
			$subscriptionsHandler = new SubscriptionsFilteredHandler();
			$subscription = $subscriptionsHandler->doUpdateUserSubscriptionByUuid($subscriptionBillingUuid);
			return($this->returnObjectAsJson($response, 'subscription', $subscription));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while updating subscription, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function cancel(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			$subscriptionBillingUuid = NULL;
			if(!isset($args['subscriptionBillingUuid'])) {
				//exception
				$msg = "field 'subscriptionBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$subscriptionBillingUuid = $args['subscriptionBillingUuid'];
			//
			$subscriptionsHandler = new SubscriptionsFilteredHandler();
			$cancelSubscriptionRequest = new CancelSubscriptionRequest();
			$cancelSubscriptionRequest->setSubscriptionBillingUuid($subscriptionBillingUuid);
			$cancelSubscriptionRequest->setOrigin('api');
			$cancelSubscriptionRequest->setCancelDate(new DateTime());
			$subscription = $subscriptionsHandler->doCancelSubscription($cancelSubscriptionRequest);
			if($subscription == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'subscription', $subscription));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while canceling a subscription, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while canceling a subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function renew(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			$subscriptionBillingUuid = NULL;
			if(!isset($args['subscriptionBillingUuid'])) {
				//exception
				$msg = "field 'subscriptionBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$subscriptionBillingUuid = $args['subscriptionBillingUuid'];
			//
			$subscriptionsHandler = new SubscriptionsFilteredHandler();
			$subscription = $subscriptionsHandler->doRenewSubscriptionByUuid($subscriptionBillingUuid);
			if($subscription == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'subscription', $subscription));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while renewing a subscription, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while renewing a subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function reactivate(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			$subscriptionBillingUuid = NULL;
			if(!isset($args['subscriptionBillingUuid'])) {
				//exception
				$msg = "field 'subscriptionBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$subscriptionBillingUuid = $args['subscriptionBillingUuid'];
			//
			$subscriptionsHandler = new SubscriptionsFilteredHandler();
			$reactivateSubscriptionRequest = new ReactivateSubscriptionRequest();
			$reactivateSubscriptionRequest->setSubscriptionBillingUuid($subscriptionBillingUuid);
			$reactivateSubscriptionRequest->setOrigin('api');
			$subscription = $subscriptionsHandler->doReactivateSubscription($reactivateSubscriptionRequest);
			if($subscription == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'subscription', $subscription));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while reactivating a subscription, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while reactivating a subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function updateInternalPlan(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			$subscriptionBillingUuid = NULL;
			if(!isset($args['subscriptionBillingUuid'])) {
				//exception
				$msg = "field 'subscriptionBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$subscriptionBillingUuid = $args['subscriptionBillingUuid'];
			$internalPlanUuid = NULL;
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			//
			$subscriptionsHandler = new SubscriptionsFilteredHandler();
			$subscription = $subscriptionsHandler->doUpdateInternalPlanByUuid($subscriptionBillingUuid, $internalPlanUuid);
			if($subscription == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'subscription', $subscription));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while reactivating a subscription, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while reactivating a subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function expire(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			if(!isset($args['subscriptionBillingUuid'])) {
				//exception
				$msg = "field 'subscriptionBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$subscriptionBillingUuid = $args['subscriptionBillingUuid'];
			if(!isset($data['isRefundEnabled'])) {
				//exception
				$msg = "field 'isRefundEnabled' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$isRefundEnabled = $data['isRefundEnabled'] == 'true' ? true : false;
			if(!isset($data['forceBeforeEndsDate'])) {
				//exception
				$msg = "field 'forceBeforeEndsDate' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$forceBeforeEndsDate = $data['forceBeforeEndsDate'] == 'true' ? true : false;
			$subscriptionsHandler = new SubscriptionsFilteredHandler();
			$expireSubscriptionRequest = new ExpireSubscriptionRequest();
			$expireSubscriptionRequest->setSubscriptionBillingUuid($subscriptionBillingUuid);
			$expireSubscriptionRequest->setOrigin('api');
			$expireSubscriptionRequest->setIsRefundEnabled($isRefundEnabled);
			$expireSubscriptionRequest->setForceBeforeEndsDate($forceBeforeEndsDate);
			$subscription = $subscriptionsHandler->doExpireSubscription($expireSubscriptionRequest);
			if($subscription == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'subscription', $subscription));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while expiring a subscription, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring a subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}

?>