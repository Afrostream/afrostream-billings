<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../internalplans/InternalPlansHandler.php';
require_once __DIR__ .'/BillingsController.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class InternalPlansController extends BillingsController {
	
	public function getOne(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$internalPlanUuid = NULL;
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			$internalPlansHandler = new InternalPlansHandler();
			$internalPlan = $internalPlansHandler->doGetInternalPlan($internalPlanUuid);
			
			if($internalPlan == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'internalPlan', $internalPlan));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting an Internal Plan, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an Internal Plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function getMulti(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$provider_name = NULL;
			if(isset($data['providerName'])) {
				$provider_name = $data['providerName'];
			}
			$internalPlansHandler = new InternalPlansHandler();
			$internalPlans = $internalPlansHandler->doGetInternalPlans($provider_name);
			return($this->returnObjectAsJson($response, 'internalPlans', $internalPlans));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting Internal Plans, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting Internal Plans, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}