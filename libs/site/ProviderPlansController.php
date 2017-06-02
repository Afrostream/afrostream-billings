<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../plans/PlansHandler.php';

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
	
	public function addPaymentMethod(Request $request, Response $response, array $args) {		try {
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
			$internalPlansHandler = new InternalPlansFilteredHandler();
			$addInternalPlanToContextRequest = new AddInternalPlanToContextRequest();
			$addInternalPlanToContextRequest->setInternalPlanUuid($internalPlanUuid);
			$addInternalPlanToContextRequest->setContextBillingUuid($contextBillingUuid);
			$addInternalPlanToContextRequest->setContextCountry($contextCountry);
			$addInternalPlanToContextRequest->setOrigin('api');
			$internalPlan = $internalPlansHandler->doAddToContext($addInternalPlanToContextRequest);
			return($this->returnObjectAsJson($response, 'internalPlan', $internalPlan));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while linking an internal plan to a context, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while linking an internal plan to a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function removePaymentMethod(Request $request, Response $response, array $args) {
		//TODO
	}
	
}