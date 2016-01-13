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
	
	public function create(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			if(!isset($data['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $data["internalPlanUuid"];
			if(!isset($data['name'])) {
				//exception
				$msg = "field 'name' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$name = $data["name"];
			if(!isset($data['description'])) {
				//exception
				$msg = "field 'description' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$description = $data["description"];
			if(!isset($data['amount_in_cents'])) {
				//exception
				$msg = "field 'amount_in_cents' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$amount_in_cents = $data["amount_in_cents"];
			if(!isset($data['currency'])) {
				//exception
				$msg = "field 'currency' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$currency = $data["currency"];
			if(!isset($data['cycle'])) {
				//exception
				$msg = "field 'cycle' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$cycle = $data["cycle"];
			if(!isset($data['periodUnit'])) {
				//exception
				$msg = "field 'periodUnit' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$periodUnitStr = $data["periodUnit"];
			if(!isset($data['periodLength'])) {
				//exception
				$msg = "field 'periodLength' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$periodLength = $data["periodLength"];
			if(!isset($data['internalPlanOpts'])) {
				//exception
				$msg = "field 'internalPlanOpts' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			} else {
				if(!is_array($data['internalPlanOpts'])) {
					//exception
					$msg = "field 'internalPlanOpts' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
			}
			$internalplan_opts_array = $data['internalPlanOpts'];
			$internalPlansHandler = new InternalPlansHandler();
			$internalPlan = $internalPlansHandler->doCreate(
					$internalPlanUuid,
					$name,
					$description,
					$amount_in_cents,
					$currency,
					$cycle,
					$periodUnitStr,
					$periodLength,
					$internalplan_opts_array
					);
			return($this->returnObjectAsJson($response, 'internalPlan', $internalPlan));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while creating an Internal Plan, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating an Internal Plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function addToProvider(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			if(!isset($args['providerName'])) {
				//exception
				$msg = "field 'providerName' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerName = $args['providerName'];
			//
			$provider = ProviderDAO::getProviderByName($providerName);
			if($provider == NULL) {
				$msg = "unknown provider named : ".$providerName;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlansHandler = new InternalPlansHandler();
			$internalPlan = $internalPlansHandler->doAddToProvider($internalPlanUuid, $provider);
			return($this->returnObjectAsJson($response, 'internalPlan', $internalPlan));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while linking an internal plan to a provider, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while linking an internal plan to a provider, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function update(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			$internalPlansHandler = new InternalPlansHandler();
			$internalPlan = NULL;
			if(isset($data['internalPlanOpts'])) {
				if(!is_array($data['internalPlanOpts'])) {
					//exception
					$msg = "field 'internalPlanOpts' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$internalplan_opts_array = $data['internalPlanOpts'];
				$internalPlan = $internalPlansHandler->doUpdateInternalPlanOpts($internalPlanUuid, $internalplan_opts_array);
			}
			if($internalPlan == NULL) {
				//NO UPDATE, JUST SEND BACK THE CURRENT INTERNAL_PLAN
				$internalPlan = $internalPlansHandler->doGetInternalPlan($internalPlanUuid);
			}
			return($this->returnObjectAsJson($response, 'internalPlan', $internalPlan));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while updating an internal plan, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating an internal plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}