<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../users/UsersHandler.php';
require_once __DIR__ . '/../subscriptions/SubscriptionsHandler.php';
require_once __DIR__ .'/BillingsController.php';

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
			$subscriptionsHandler = new SubscriptionsHandler();
			$subscription = $subscriptionsHandler->doGetSubscriptionBySubscriptionBillingUuid($subscriptionBillingUuid);
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
			$subscriptionsHandler = new SubscriptionsHandler();
			if(isset($userReferenceUuid)) {
				$users = $usersHandler->doGetUsers($userReferenceUuid);
				if(count($users) == 0) {
					return($this->returnNotFoundAsJson($response));
				}
				foreach($users as $user) {
					$current_subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUser($user);
					$subscriptions = array_merge($subscriptions, $current_subscriptions);
				}
			} else if(isset($userBillingUuid)) {
				$user = $usersHandler->doGetUserByUserBillingUuid($userBillingUuid);
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
			$this->doSortSubscriptions($subscriptions);
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
			if(!isset($data['billingInfoOpts'])) {
				//exception
				$msg = "field 'billingInfoOpts' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			} else {
				if(!is_array($data['billingInfoOpts'])) {
					//exception
					$msg = "field 'billingInfoOpts' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
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
			$billing_info_opts = $data['billingInfoOpts'];
			$subscription_provider_uuid = NULL;
			if(isset($data['subscriptionProviderUuid'])) {
				$subscription_provider_uuid = $data['subscriptionProviderUuid'];
			}
			$subscriptionsHandler = new SubscriptionsHandler();
			$subscription = $subscriptionsHandler->doGetOrCreateSubscription($user_billing_uuid, $internal_plan_uuid, $subscription_provider_uuid, $billing_info_opts, $sub_opts);
			return($this->returnObjectAsJson($response, 'subscription', $subscription));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while creating a subscription, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating an subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function update(Request $request, Response $response, array $args) {
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
			$subscriptionsHandler = new SubscriptionsHandler();
			if(isset($userReferenceUuid)) {
				$users = $usersHandler->doGetUsers($userReferenceUuid);
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
				$user = $usersHandler->doGetUserByUserBillingUuid($userBillingUuid);
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
	
	public function cancel(Request $request, Response $response, array $args) {
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
			$subscriptionsHandler = new SubscriptionsHandler();
			$subscription = $subscriptionsHandler->doCancelSubscriptionByUuid($subscriptionBillingUuid, new DateTime(), true);
			if($subscription == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'subscription', $subscription));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while cancelling a subscription, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while cancelling a subscription, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	//passage par référence !!!
	private function doSortSubscriptions(&$subscriptions) {
		//more recent firt
		usort($subscriptions, 
				function(BillingsSubscription $a, BillingsSubscription $b) {
					return(strcmp($b->getSubActivatedDate(), $a->getSubActivatedDate()));
				}
		);
	}
	
}

?>