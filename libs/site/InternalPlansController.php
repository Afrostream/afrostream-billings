<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../internalplans/InternalPlansFilteredHandler.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../providers/global/requests/AddInternalPlanToContextRequest.php';
require_once __DIR__ . '/../providers/global/requests/AddInternalPlanToCountryRequest.php';
require_once __DIR__ . '/../providers/global/requests/AddInternalPlanToProviderRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalPlanRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalPlansRequest.php';
require_once __DIR__ . '/../providers/global/requests/CreateInternalPlanRequest.php';
require_once __DIR__ . '/../providers/global/requests/RemoveInternalPlanFromContextRequest.php';
require_once __DIR__ . '/../providers/global/requests/RemoveInternalPlanFromCountryRequest.php';
require_once __DIR__ . '/../providers/global/requests/UpdateInternalPlanRequest.php';
require_once __DIR__ . '/../providers/global/requests/SetDefaultInternalCouponsCampaignToInternalPlanRequest.php';
require_once __DIR__ . '/../providers/global/requests/UnsetDefaultInternalCouponsCampaignToInternalPlanRequest.php';

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
			$getInternalPlanRequest = new GetInternalPlanRequest();
			$getInternalPlanRequest->setInternalPlanUuid($internalPlanUuid);
			$getInternalPlanRequest->setOrigin('api');
			$internalPlan = $internalPlansHandler->doGetInternalPlan($getInternalPlanRequest);
			
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
			$providerName = NULL;
			if(isset($data['providerName'])) {
				$providerName = $data['providerName'];
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
			$filteredArray = array_filter($data, 
				function($k) {
					return strpos($k, "filter") === 0;
				}, ARRAY_FILTER_USE_KEY);
			$country = NULL;
			if(isset($data['country'])) {
				$country = $data['country'];
			}
			$internalPlansHandler = new InternalPlansFilteredHandler();
			$getInternalPlansRequest = new GetInternalPlansRequest();
			$getInternalPlansRequest->setProviderName($providerName);
			$getInternalPlansRequest->setContextBillingUuid($contextBillingUuid);
			$getInternalPlansRequest->setContextCountry($contextCountry);
			$getInternalPlansRequest->setIsVisible($isVisible);
			$getInternalPlansRequest->setCountry($country);
			$getInternalPlansRequest->setFilteredArray($filteredArray);
			$getInternalPlansRequest->setOrigin('api');
			$internalPlans = $internalPlansHandler->doGetInternalPlans($getInternalPlansRequest);
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
			if(!isset($data['amountInCents'])) {
				//exception
				$msg = "field 'amountInCents' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$amountInCents = $data["amountInCents"];
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
			$internalplan_opts_array = $data['internalPlanOpts'];
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
			$internalPlansHandler = new InternalPlansFilteredHandler();
			$createInternalPlanRequest = new CreateInternalPlanRequest();
			$createInternalPlanRequest->setInternalPlanUuid($internalPlanUuid);
			$createInternalPlanRequest->setName($name);
			$createInternalPlanRequest->setDescription($description);
			$createInternalPlanRequest->setAmountInCents($amountInCents);
			$createInternalPlanRequest->setCurrency($currency);
			$createInternalPlanRequest->setCycle($cycle);
			$createInternalPlanRequest->setPeriodUnit($periodUnitStr);
			$createInternalPlanRequest->setPeriodLength($periodLength);
			$createInternalPlanRequest->setVatRate($vatRate);
			$createInternalPlanRequest->setInternalPlanOptsArray($internalplan_opts_array);
			$createInternalPlanRequest->setTrialEnabled($trialEnabled);
			$createInternalPlanRequest->setTrialPeriodLength($trialPeriodLength);
			$createInternalPlanRequest->setTrialPeriodUnit($trialPeriodUnit);
			$createInternalPlanRequest->setOrigin('api');
			$internalPlan = $internalPlansHandler->doCreate($createInternalPlanRequest);
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
			$internalPlansHandler = new InternalPlansFilteredHandler();
			$addInternalPlanToProviderRequest = new AddInternalPlanToProviderRequest();
			$addInternalPlanToProviderRequest->setInternalPlanUuid($internalPlanUuid);
			$addInternalPlanToProviderRequest->setProviderName($providerName);
			$addInternalPlanToProviderRequest->setOrigin('api');
			$internalPlan = $internalPlansHandler->doAddToProvider($addInternalPlanToProviderRequest);
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
			$updateInternalPlanRequest = new UpdateInternalPlanRequest();
			$updateInternalPlanRequest->setOrigin('api');
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$updateInternalPlanRequest->setInternalPlanUuid($args['internalPlanUuid']);
			if(isset($data['internalPlanOpts'])) {
				if(!is_array($data['internalPlanOpts'])) {
					//exception
					$msg = "field 'internalPlanOpts' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$updateInternalPlanRequest->setInternalPlanOptsArray($data['internalPlanOpts']);
			}
			if(isset($data['name'])) {
				$updateInternalPlanRequest->setName($data['name']);
			}
			if(isset($data['description'])) {
				$updateInternalPlanRequest->setDescription($data['description']);
			}
			if(isset($data['isVisible'])) {
				$updateInternalPlanRequest->setIsVisible($data['isVisible'] === true ? true : false);
			}
			if(isset($data['details'])) {
				//Check is a json
				$decodedDetails = json_decode($data['details'], true);
				if($decodedDetails === NULL) {
					//exception
					$msg = "field 'details' must be a valid json";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$updateInternalPlanRequest->setDetails($decodedDetails);
			}
			$internalPlansHandler = new InternalPlansFilteredHandler();
			$internalPlan = $internalPlansHandler->doUpdateInternalPlan($updateInternalPlanRequest);
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
			$addInternalPlanToCountryRequest = new AddInternalPlanToCountryRequest();
			$addInternalPlanToCountryRequest->setInternalPlanUuid($internalPlanUuid);
			$addInternalPlanToCountryRequest->setCountry($country);
			$addInternalPlanToCountryRequest->setOrigin('api');
			$internalPlan = $internalPlansHandler->doAddToCountry($addInternalPlanToCountryRequest);
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
			$removeInternalPlanFromCountryRequest = new RemoveInternalPlanFromCountryRequest();
			$removeInternalPlanFromCountryRequest->setInternalPlanUuid($internalPlanUuid);
			$removeInternalPlanFromCountryRequest->setCountry($country);
			$removeInternalPlanFromCountryRequest->setOrigin('api');
			$internalPlan = $internalPlansHandler->doRemoveFromCountry($removeInternalPlanFromCountryRequest);
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
			$removeInternalPlanFromContextRequest = new RemoveInternalPlanFromContextRequest();
			$removeInternalPlanFromContextRequest->setInternalPlanUuid($internalPlanUuid);
			$removeInternalPlanFromContextRequest->setContextBillingUuid($contextBillingUuid);
			$removeInternalPlanFromContextRequest->setContextCountry($contextCountry);
			$removeInternalPlanFromContextRequest->setOrigin('api');
			$internalPlan = $internalPlansHandler->doRemoveFromContext($removeInternalPlanFromContextRequest);
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
	
	public function setDefaultInternalCouponsCampaign(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			if(!isset($args['couponsCampaignInternalBillingUuid'])) {
				//exception
				$msg = "field 'couponsCampaignInternalBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlansHandler = new InternalPlansFilteredHandler();
			$couponsCampaignInternalBillingUuid = $args['couponsCampaignInternalBillingUuid'];
			$setDefaultInternalCouponsCampaignToInternalPlanRequest = new SetDefaultInternalCouponsCampaignToInternalPlanRequest();
			$setDefaultInternalCouponsCampaignToInternalPlanRequest->setOrigin('api');
			$setDefaultInternalCouponsCampaignToInternalPlanRequest->setInternalPlanUuid($internalPlanUuid);
			$setDefaultInternalCouponsCampaignToInternalPlanRequest->setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid);
			$internalPlan = $internalPlansHandler->doSetDefaultInternalCouponsCampaign($setDefaultInternalCouponsCampaignToInternalPlanRequest);
			return($this->returnObjectAsJson($response, 'internalPlan', $internalPlan));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while setting a default internalCouponsCampaign to an internalPlan, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while setting a default internalCouponsCampaign to an internalPlan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function unsetDefaultInternalCouponsCampaign(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			$internalPlansHandler = new InternalPlansFilteredHandler();
			$unsetDefaultInternalCouponsCampaignToInternalPlanRequest = new UnsetDefaultInternalCouponsCampaignToInternalPlanRequest();
			$unsetDefaultInternalCouponsCampaignToInternalPlanRequest->setOrigin('api');
			$unsetDefaultInternalCouponsCampaignToInternalPlanRequest->setInternalPlanUuid($internalPlanUuid);
			$internalPlan = $internalPlansHandler->doUnsetDefaultInternalCouponsCampaign($unsetDefaultInternalCouponsCampaignToInternalPlanRequest);
			return($this->returnObjectAsJson($response, 'internalPlan', $internalPlan));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while unsetting the default internalCouponsCampaign from an internalPlan, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while unsetting the default internalCouponsCampaign from an internalPlan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
		
	}
	
}

?>