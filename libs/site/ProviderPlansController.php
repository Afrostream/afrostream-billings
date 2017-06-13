<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../plans/PlansHandler.php';
require_once __DIR__ . '/../providers/global/requests/AddPaymentMethodToProviderPlanRequest.php';
require_once __DIR__ . '/../providers/global/requests/RemovePaymentMethodFromProviderPlanRequest.php';
require_once __DIR__ . '/../providers/global/requests/UpdateProviderPlanRequest.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class ProviderPlansController extends BillingsController {
	
	public function getOne(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$providerPlanBillingUuid = NULL;
			if(!isset($args['providerPlanBillingUuid'])) {
				//exception
				$msg = "field 'providerPlanBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerPlanBillingUuid = $args['providerPlanBillingUuid'];
			$plansHandler = new PlansHandler();
			$getProviderPlanRequest = new GetProviderPlanRequest();
			$getProviderPlanRequest->setProviderPlanBillingUuid($providerPlanBillingUuid);
			$getProviderPlanRequest->setOrigin('api');
			$providerPlan = $plansHandler->doGetProviderPlan($getProviderPlanRequest);
			if($providerPlan == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'providerPlan', $providerPlan));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting a ProviderPlan, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a ProviderPlan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function addPaymentMethod(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			if(!isset($args['providerPlanBillingUuid'])) {
				//exception
				$msg = "field 'providerPlanBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerPlanBillingUuid = $args['providerPlanBillingUuid'];
			if(!isset($args['paymentMethodType'])) {
				//exception
				$msg = "field 'paymentMethodType' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$paymentMethodType = $args['paymentMethodType'];
			$plansHandler = new PlansHandler();
			$addPaymentMethodToProviderPlanRequest = new AddPaymentMethodToProviderPlanRequest();
			$addPaymentMethodToProviderPlanRequest->setProviderPlanBillingUuid($providerPlanBillingUuid);
			$addPaymentMethodToProviderPlanRequest->setPaymentMethodType($paymentMethodType);
			$addPaymentMethodToProviderPlanRequest->setOrigin('api');
			$providerPlan = PlanDAO::getPlanByProviderPlanBillingUuid($providerPlanBillingUuid, $addPaymentMethodToProviderPlanRequest->getPlatform()->getId());
			if($providerPlan == NULL) {
				$msg = "unknown providerPlanBillingUuid : ".$providerPlanBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerPlan = $plansHandler->doAddPaymentMethod($providerPlan, $addPaymentMethodToProviderPlanRequest);
			return($this->returnObjectAsJson($response, 'providerPlan', $providerPlan));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while adding a paymentMethod to a ProviderPlan, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while adding a paymentMethod to a ProviderPlan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function removePaymentMethod(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			if(!isset($args['providerPlanBillingUuid'])) {
				//exception
				$msg = "field 'providerPlanBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerPlanBillingUuid = $args['providerPlanBillingUuid'];
			if(!isset($args['paymentMethodType'])) {
				//exception
				$msg = "field 'paymentMethodType' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$paymentMethodType = $args['paymentMethodType'];
			$plansHandler = new PlansHandler();
			$removePaymentMethodFromProviderPlanRequest = new RemovePaymentMethodFromProviderPlanRequest();
			$removePaymentMethodFromProviderPlanRequest->setProviderPlanBillingUuid($providerPlanBillingUuid);
			$removePaymentMethodFromProviderPlanRequest->setPaymentMethodType($paymentMethodType);
			$removePaymentMethodFromProviderPlanRequest->setOrigin('api');
			$providerPlan = PlanDAO::getPlanByProviderPlanBillingUuid($providerPlanBillingUuid, $removePaymentMethodFromProviderPlanRequest->getPlatform()->getId());
			if($providerPlan == NULL) {
				$msg = "unknown providerPlanBillingUuid : ".$providerPlanBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerPlan = $plansHandler->doRemovePaymentMethod($providerPlan, $removePaymentMethodFromProviderPlanRequest);
			return($this->returnObjectAsJson($response, 'providerPlan', $providerPlan));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while removing a paymentMethod from a ProviderPlan, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while removing a paymentMethod from a ProviderPlan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function update(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			$updateProviderPlanRequest = new UpdateProviderPlanRequest();
			$updateProviderPlanRequest->setOrigin('api');
			if(!isset($args['providerPlanBillingUuid'])) {
				//exception
				$msg = "field 'providerPlanBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$updateProviderPlanRequest->setProviderPlanBillingUuid($args['providerPlanBillingUuid']);
			if(isset($data['providerPlanOpts'])) {
				if(!is_array($data['providerPlanOpts'])) {
					//exception
					$msg = "field 'providerPlanOpts' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$updateProviderPlanRequest->setProviderPlanOptsArray($data['providerPlanOpts']);
			}
			if(isset($data['isVisible'])) {
				$updateProviderPlanRequest->setIsVisible($data['isVisible'] === true ? true : false);
			}
			$plansHandler = new PlansHandler();
			$providerPlan = $plansHandler->doUpdateProviderPlan($updateProviderPlanRequest);
			return($this->returnObjectAsJson($response, 'providerPlan', $providerPlan));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while updating a ProviderPlan, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating a ProviderPlan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}