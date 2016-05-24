<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../internalplans/InternalPlansFilteredHandler.php';
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
			$internalPlansHandler = new InternalPlansFilteredHandler();
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
			$contextBillingUuid = NULL;
			if(isset($data['contextBillingUuid'])) {
				$contextBillingUuid = $data['contextBillingUuid'];
			}
			$contextCountry = NULL;
			if(isset($data['contextCountry'])) {
				$contextCountry = $data['contextCountry'];
			}
			$isVisible = true;//by default isVisible only
			if(isset($data['isVisible'])) {
				$isVisible = $data['isVisible'];
				if(empty($isVisible)) {
					$isVisible = NULL;//empty = ALL
				}
			}
			$filtered_array = array_filter($data, 
				function($k) {
					return strpos($k, "filter") === 0;
				}, ARRAY_FILTER_USE_KEY);
			$country = NULL;
			if(isset($data['country'])) {
				$country = $data['country'];
			}
			$internalPlansHandler = new InternalPlansFilteredHandler();
			$internalPlans = $internalPlansHandler->doGetInternalPlans($provider_name, $contextBillingUuid, $contextCountry, $isVisible, $country, $filtered_array);
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

			if (!isset($data['vatRate']) || !is_numeric($data['vatRate'])) {
				//exception
				$msg = "field 'vatRate' is missing or is not a numeric value";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}

			$vatRate = floatval($data['vatRate']);

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

			$trialEnabled = (!empty($data['trialEnabled']));
			$trialPeriodLength = null;
			$trialPeriodUnit = null;
			if ($trialEnabled) {
				if (empty($data['trialPeriodLength']) || !is_numeric($data['trialPeriodLength']) || $data['trialPeriodLength'] < 1) {
					$msg = "field trialPeriodLength can't be less than 1 when trial is enabled";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}

				if (empty($data['trialPeriodUnit']) || !in_array($data['trialPeriodUnit'], ['day', 'month'])) {
					$msg = "field trialPeriodUnit can't be empty or must match day or month";
					config::getLogger()->addError($msg);

					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}

				$trialPeriodLength = $data['trialPeriodLength'];
				$trialPeriodUnit   = $data['trialPeriodUnit'];
			}

			$internalplan_opts_array = $data['internalPlanOpts'];
			$internalPlansHandler = new InternalPlansFilteredHandler();
			$internalPlan = $internalPlansHandler->doCreate(
					$internalPlanUuid,
					$name,
					$description,
					$amount_in_cents,
					$currency,
					$cycle,
					$periodUnitStr,
					$periodLength,
					$vatRate,
					$internalplan_opts_array,
					$trialEnabled,
					$trialPeriodLength,
					$trialPeriodUnit
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
			$internalPlansHandler = new InternalPlansFilteredHandler();
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
			$internalPlansHandler = new InternalPlansFilteredHandler();
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
	
	public function addToCountry(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			if(!isset($args['country'])) {
				//exception
				$msg = "field 'country' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$country = $args['country'];
			$internalPlansHandler = new InternalPlansFilteredHandler();
			$internalPlan = $internalPlansHandler->doAddToCountry($internalPlanUuid, $country);
			return($this->returnObjectAsJson($response, 'internalPlan', $internalPlan));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while linking an internal plan to a country, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while linking an internal plan to a country, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function removeFromCountry(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			if(!isset($args['country'])) {
				//exception
				$msg = "field 'country' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$country = $args['country'];
			$internalPlansHandler = new InternalPlansFilteredHandler();
			$internalPlan = $internalPlansHandler->doRemoveFromCountry($internalPlanUuid, $country);
			return($this->returnObjectAsJson($response, 'internalPlan', $internalPlan));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while removing an internal plan from a country, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while removing an internal plan from a country, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function addToContext(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			if(!isset($args['contextBillingUuid'])) {
				//exception
				$msg = "field 'contextBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextBillingUuid = $args['contextBillingUuid'];
			if(!isset($args['contextCountry'])) {
				//exception
				$msg = "field 'contextCountry' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextCountry = $args['contextCountry'];
			$internalPlansHandler = new InternalPlansFilteredHandler();
			$internalPlan = $internalPlansHandler->doAddToContext($internalPlanUuid, $contextBillingUuid, $contextCountry);
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
	
	public function removeFromContext(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			if(!isset($args['contextBillingUuid'])) {
				//exception
				$msg = "field 'contextBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextBillingUuid = $args['contextBillingUuid'];
			if(!isset($args['contextCountry'])) {
				//exception
				$msg = "field 'contextCountry' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextCountry = $args['contextCountry'];
			$internalPlansHandler = new InternalPlansFilteredHandler();
			$internalPlan = $internalPlansHandler->doRemoveFromContext($internalPlanUuid, $contextBillingUuid, $contextCountry);
			return($this->returnObjectAsJson($response, 'internalPlan', $internalPlan));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while removing an internal plan from a context, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while removing an internal plan from a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}

?>