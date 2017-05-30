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
require_once __DIR__ . '/../providers/global/requests/RenewSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/UpdateInternalPlanSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/UpdateSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetOrCreateSubscriptionRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetUserSubscriptionsRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetSubscriptionsRequest.php';
require_once __DIR__ . '/../providers/global/requests/RedeemCouponRequest.php';

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
			$clientId = NULL;
			if(isset($data['clientId'])) {
				$clientId = $data['clientId'];
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
				$getSubscriptionsRequest = new GetSubscriptionsRequest();
				$getSubscriptionsRequest->setOrigin('api');
				$getSubscriptionsRequest->setClientId($clientId);
				$getSubscriptionsRequest->setUserReferenceUuid($userReferenceUuid);
				$subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUserReferenceUuid($getSubscriptionsRequest);
			} else if(isset($userBillingUuid)) {
				$getUserRequest = new GetUserRequest();
				$getUserRequest->setOrigin('api');
				$getUserRequest->setUserBillingUuid($userBillingUuid);
				$user = $usersHandler->doGetUser($getUserRequest);
				if($user == NULL) {
					return($this->returnNotFoundAsJson($response));
				}
				$getUserSubscriptionsRequest = new GetUserSubscriptionsRequest();
				$getUserSubscriptionsRequest->setOrigin('api');
				$getUserSubscriptionsRequest->setUserBillingUuid($user->getUserBillingUuid());
				$getUserSubscriptionsRequest->setClientId($clientId);
				$subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUser($getUserSubscriptionsRequest);
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
			$getOrCreateSubscriptionRequest = new GetOrCreateSubscriptionRequest();
			$getOrCreateSubscriptionRequest->setUserBillingUuid($user_billing_uuid);
			$getOrCreateSubscriptionRequest->setInternalPlanUuid($internal_plan_uuid);
			$getOrCreateSubscriptionRequest->setSubscriptionProviderUuid($subscription_provider_uuid);
			$getOrCreateSubscriptionRequest->setBillingInfoArray($billing_info_array);
			$getOrCreateSubscriptionRequest->setSubOptsArray($sub_opts);
			$getOrCreateSubscriptionRequest->setOrigin('api');
			$subscription = $subscriptionsHandler->doGetOrCreateSubscription($getOrCreateSubscriptionRequest);
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
					$getUserSubscriptionsRequest = new GetUserSubscriptionsRequest();
					$getUserSubscriptionsRequest->setOrigin('api');
					$getUserSubscriptionsRequest->setUserBillingUuid($user->getUserBillingUuid());
					$current_subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUser($getUserSubscriptionsRequest);
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
				$getUserSubscriptionsRequest = new GetUserSubscriptionsRequest();
				$getUserSubscriptionsRequest->setOrigin('api');
				$getUserSubscriptionsRequest->setUserBillingUuid($user->getUserBillingUuid());
				$subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUser($getUserSubscriptionsRequest);
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
			$updateSubscriptionRequest = new UpdateSubscriptionRequest();
			$updateSubscriptionRequest->setSubscriptionBillingUuid($subscriptionBillingUuid);
			$updateSubscriptionRequest->setOrigin('api');
			$subscription = $subscriptionsHandler->doUpdateUserSubscription($updateSubscriptionRequest);
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
			$renewSubscriptionRequest = new RenewSubscriptionRequest();
			$renewSubscriptionRequest->setSubscriptionBillingUuid($subscriptionBillingUuid);
			$renewSubscriptionRequest->setOrigin('api');
			$subscription = $subscriptionsHandler->doRenewSubscription($renewSubscriptionRequest);
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
			$timeframe = NULL;
			if(!isset($data['timeframe'])) {
				//exception
				$msg = "field 'timeframe' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);				
			}
			$timeframe = $data['timeframe'];
			$timeframeValues = ['now', 'atRenewal'];
			if(!in_array($timeframe, $timeframeValues)) {
				//exception
				$msg = "field 'timeframe' value must be one of follows : ".implode(', ', $timeframeValues);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);				
			}
			//
			$subscriptionsHandler = new SubscriptionsFilteredHandler();
			$updateInternalPlanSubscriptionRequest = new UpdateInternalPlanSubscriptionRequest();
			$updateInternalPlanSubscriptionRequest->setSubscriptionBillingUuid($subscriptionBillingUuid);
			$updateInternalPlanSubscriptionRequest->setInternalPlanUuid($internalPlanUuid);
			$updateInternalPlanSubscriptionRequest->setTimeframe($timeframe);
			$updateInternalPlanSubscriptionRequest->setOrigin('api');
			$subscription = $subscriptionsHandler->doUpdateInternalPlanSubscription($updateInternalPlanSubscriptionRequest);
			if($subscription == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'subscription', $subscription));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while updating a plan for a subscription, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating a plan for a subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
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
			$isRefundProrated = false;
			if(isset($data['isRefundProrated'])) {
				$isRefundProrated = $data['isRefundProrated'] == 'true' ? true : false;
			}
			$subscriptionsHandler = new SubscriptionsFilteredHandler();
			$expireSubscriptionRequest = new ExpireSubscriptionRequest();
			$expireSubscriptionRequest->setSubscriptionBillingUuid($subscriptionBillingUuid);
			$expireSubscriptionRequest->setOrigin('api');
			$expireSubscriptionRequest->setIsRefundEnabled($isRefundEnabled);
			$expireSubscriptionRequest->setForceBeforeEndsDate($forceBeforeEndsDate);
			$expireSubscriptionRequest->setIsRefundProrated($isRefundProrated);
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
	
	public function redeemCoupon(Request $request, Response $response, array $args) {
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
			$couponCode = NULL;
			if(!isset($args['couponCode'])) {
				//exception
				$msg = "field 'couponCode' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(strlen(trim($args['couponCode'])) == 0) {
				//exception
				$msg = "field 'couponCode' is empty";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$couponCode = trim($args['couponCode']);
			$force = NULL;
			if(isset($data['force'])) {
				$force = $data['force'] == 'true' ? true : false;
			}
			$subscriptionsHandler = new SubscriptionsFilteredHandler();
			$redeemCouponRequest = new RedeemCouponRequest();
			$redeemCouponRequest->setSubscriptionBillingUuid($subscriptionBillingUuid);
			$redeemCouponRequest->setCouponCode($couponCode);
			$redeemCouponRequest->setForce($force);
			$redeemCouponRequest->setOrigin('api');
			$subscription = $subscriptionsHandler->doRedeemCoupon($redeemCouponRequest);
			if($subscription == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'subscription', $subscription));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while redeeming a coupon to a subscription, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while redeeming a coupon to a subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}			
	}
	
}

?>