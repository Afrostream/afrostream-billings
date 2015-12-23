<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../subscriptions/SubscriptionsHandler.php';
require_once __DIR__ .'/BillingsController.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class SubscriptionsController extends BillingsController {
	
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
			$user_billing_uuid = $data['userBillingUuid'];
			$internal_plan_uuid = $data['internalPlanUuid'];
			$billing_info_opts = $data['billingInfoOpts'];
			$subscription_provider_uuid = NULL;
			if(isset($data['subscriptionProviderUuid'])) {
				$subscription_provider_uuid = $data['subscriptionProviderUuid'];
			}
			$subscriptionsHandler = new SubscriptionsHandler();
			$subscription = $subscriptionsHandler->doGetOrCreateSubscription($user_billing_uuid, $internal_plan_uuid, $subscription_provider_uuid, $billing_info_opts);
			return($this->returnObjectAsJson($response, 'subscription', $subscription));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while creating a subscription, error_type=".$e->getExceptionType().",error_code=".$e->getCode().", error_message=".$e->getMessage();
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
	
	
	
}