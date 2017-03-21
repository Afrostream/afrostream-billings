<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../providers/global/ProviderHandlersBuilder.php';
require_once __DIR__ . '/../providers/global/requests/AddInternalPlanToContextRequest.php';
require_once __DIR__ . '/../providers/global/requests/AddInternalPlanToCountryRequest.php';
require_once __DIR__ . '/../providers/global/requests/AddInternalPlanToProviderRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalPlanRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalPlansRequest.php';
require_once __DIR__ . '/../providers/global/requests/CreateInternalPlanRequest.php';
require_once __DIR__ . '/../providers/global/requests/RemoveInternalPlanFromContextRequest.php';
require_once __DIR__ . '/../providers/global/requests/RemoveInternalPlanFromCountryRequest.php';
require_once __DIR__ . '/../providers/global/requests/UpdateInternalPlanRequest.php';

use Money\Currency;
use Iso3166\Codes;

class InternalPlansHandler {
	
	public function __construct() {
	}
	
	public function doGetInternalPlan(GetInternalPlanRequest $getInternalPlanRequest) {
		$internalPlanUuid = $getInternalPlanRequest->getInternalPlanUuid();
		$db_internal_plan = NULL;
		try {
			config::getLogger()->addInfo("internal plan getting, internalPlanUuid=".$internalPlanUuid."....");
			//
			$db_internal_plan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid, $getInternalPlanRequest->getPlatform()->getId());
			//
			config::getLogger()->addInfo("internal plan getting, internalPlanUuid=".$internalPlanUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting an internal plan for internalPlanUuid=".$internalPlanUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internal plan getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an internal plan for internalPlanUuid=".$internalPlanUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internal plan getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_plan);
	}
	
	public function doGetInternalPlans(GetInternalPlansRequest $getInternalPlansRequest) {
		$provider_name = $getInternalPlansRequest->getProviderName();
		$contextBillingUuid = $getInternalPlansRequest->getContextBillingUuid();
		$contextCountry = $getInternalPlansRequest->getContextCountry();
		$isVisible = $getInternalPlansRequest->getIsVisible();
		$country = $getInternalPlansRequest->getCountry();
		//
		$db_internal_plans = NULL;
		try {
			config::getLogger()->addInfo("internal plans getting...");
			$provider_id = NULL;
			if(isset($provider_name)) {
				$provider = ProviderDAO::getProviderByName($provider_name, $getInternalPlansRequest->getPlatform()->getId());
				if($provider == NULL) {
					$msg = "unknown provider named : ".$provider_name;
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$provider_id = $provider->getId();
			}
			$context_id = NULL;
			if(isset($contextBillingUuid)) {
				if($contextCountry == NULL) {
					$contextCountry = "FR";//forced when not given (it is the case for now)
					config::getLogger()->addInfo("contextCountry NOT given, it was forced to : ".$contextCountry);
				}
				$context = ContextDAO::getContext($contextBillingUuid, $contextCountry, $getInternalPlansRequest->getPlatform()->getId());
				if($context == NULL) {
					//switch contextBillingUuid to 'common' when context is 'mobile' only
					$oldContextBillingUuid = $contextBillingUuid;
					if($contextBillingUuid == 'mobile') {
						$contextBillingUuid = 'common';
					}
					//switch country
					$oldContextCountry = $contextCountry;
					$contextCountry = "FR";//back to France
					config::getLogger()->addInfo("unknown context with contextBillingUuid=".$oldContextBillingUuid.", contextCountry=".$oldContextCountry.
							", rollback to contextBillingUuid=".$contextBillingUuid.", contextCountry=".$contextCountry);
					$context = ContextDAO::getContext($contextBillingUuid, $contextCountry, $getInternalPlansRequest->getPlatform()->getId());
				}
				if($context == NULL) {
					$msg = "unknown context with contextBillingUuid : ".$contextBillingUuid." AND contextCountry : ".$contextCountry.", no internalPlan given";
					config::getLogger()->addInfo($msg);		
					$db_internal_plans = array();//no internalPlan
				} else {
					$context_id = $context->getId();
					$country = $contextCountry;//Force country to the contextCountry
					$db_internal_plans = InternalPlanDAO::getInternalPlans($provider_id, $context_id, $isVisible, $country, $getInternalPlansRequest->getPlatform()->getId());
				}
			} else {
				$db_internal_plans = InternalPlanDAO::getInternalPlans($provider_id, $context_id, $isVisible, $country, $getInternalPlansRequest->getPlatform()->getId());
			}
			config::getLogger()->addInfo("internal plans getting done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting internal plans, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internal plans getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting internal plans, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internal plans getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_plans);
	}
	
	public function doCreate(CreateInternalPlanRequest $createInternalPlanRequest) {
		$internalPlanUuid = $createInternalPlanRequest->getInternalPlanUuid();
		$name = $createInternalPlanRequest->getName();
		$description = $createInternalPlanRequest->getDescription();
		$amount_in_cents = $createInternalPlanRequest->getAmountInCents();
		$currency = $createInternalPlanRequest->getCurrency();
		$cycle = $createInternalPlanRequest->getCycle();
		$period_unit_str = $createInternalPlanRequest->getPeriodUnit();
		$period_length = $createInternalPlanRequest->getPeriodLength();
		$vatRate = $createInternalPlanRequest->getVateRate();
		$internalplan_opts_array = $createInternalPlanRequest->getInternalplanOptsArray();
		$trialEnabled = $createInternalPlanRequest->getTrialEnabled();
		$trialPeriodLength = $createInternalPlanRequest->getTrialPeriodLength();
		$trialPeriodUnit = $createInternalPlanRequest->getTrialPeriodUnit();
		//
		$db_internal_plan = NULL;
		try {
			config::getLogger()->addInfo("internal plan creating...");
			//checks
			$db_tmp_internal_plan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid, $createInternalPlanRequest->getPlatform()->getId());
			if(isset($db_tmp_internal_plan)) {
				$msg = "an internal plan with the same InternalPlanUuid=".$internalPlanUuid." already exists";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_tmp_internal_plan = InternalPlanDAO::getInternalPlanByName($name, $createInternalPlanRequest->getPlatform()->getId());
			if(isset($db_tmp_internal_plan)) {
				$msg = "an internal plan with the same name=".$name." already exists";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!(ctype_digit($amount_in_cents)) || !($amount_in_cents >= 0)) {
				$msg = "amount_in_cents must be a positive or zero integer";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists($currency, Currency::getCurrencies())) {
				$msg = "currency is not valid";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			if(!PlanCycle::isValid($cycle)) {
				$msg = "cycle is not valid, must be in : ".implode(', ', PlanCycle::toArray());
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$planCycle = new PlanCycle($cycle);
			if(!PlanPeriodUnit::isValid($period_unit_str)) {
				$msg = "period is not valid, must be in : ".implode(', ', PlanPeriodUnit::toArray());
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$planPeriodUnit = new PlanPeriodUnit($period_unit_str);
			//
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				//INTERNAL_PLAN
				$db_internal_plan = new InternalPlan();
				$db_internal_plan->setInternalPlanUid($internalPlanUuid);
				$db_internal_plan->setName($name);
				$db_internal_plan->setAmountInCents($amount_in_cents);
				$db_internal_plan->setCurrency($currency);
				$db_internal_plan->setCycle($planCycle);
				$db_internal_plan->setPeriodUnit($planPeriodUnit);
				$db_internal_plan->setPeriodLength($period_length);
				$db_internal_plan->setVatRate($vatRate);
				$db_internal_plan->setTrialEnabled($trialEnabled);
				$db_internal_plan->setTrialPeriodLength($trialPeriodLength);
				$db_internal_plan->setTrialPeriodUnit(new TrialPeriodUnit($trialPeriodUnit));
				$db_internal_plan->setPlatformId($createInternalPlanRequest->getPlatform()->getId());
				$db_internal_plan = InternalPlanDAO::addInternalPlan($db_internal_plan);
				//INTERNAL_PLAN_OPTS
				$internalPlanOpts = new InternalPlanOpts();
				$internalPlanOpts->setInternalPlanId($db_internal_plan->getId());
				$internalPlanOpts->setOpts($internalplan_opts_array);
				$internalPlanOpts = InternalPlanOptsDAO::addInternalPlanOpts($internalPlanOpts);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			config::getLogger()->addInfo("internal plan creating done successfully, internalplanid=".$db_internal_plan->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating an internal plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internal plan creating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating an internal plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internal plan creating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_plan);
	}
	
	public function doAddToProvider(AddInternalPlanToProviderRequest $addInternalPlanToProviderRequest) {
		$internalPlanUuid = $addInternalPlanToProviderRequest->getInternalPlanUuid();
		$providerName = $addInternalPlanToProviderRequest->getProviderName();
		//
		$db_internal_plan = NULL;
		try {
			$db_internal_plan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid, $addInternalPlanToProviderRequest->getPlatform()->getId());
			if($db_internal_plan == NULL) {
				$msg = "unknown internalPlanUuid : ".$internalPlanUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$provider = ProviderDAO::getProviderByName($providerName, $addInternalPlanToProviderRequest->getPlatform()->getId());
			if($provider == NULL) {
				$msg = "unknown provider named : ".$providerName;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//linked ?
			$providerPlan = PlanDAO::getPlanByInternalPlanId($db_internal_plan->getId(), $provider->getId());
			if(isset($providerPlan)) {
				$msg = "internal plan with internalPlanUuid : ".$internalPlanUuid." is already linked to provider : ".$provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//already exist ?
			$db_tmp_internal_plan = PlanDAO::getPlanByName($provider->getId(), $db_internal_plan->getName());
			if(isset($db_tmp_internal_plan)) {
				$msg = "a provider plan named ".$db_tmp_internal_plan->getName()." does already exist for provider : ".$provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//create provider side
			$providerPlansHandler = ProviderHandlersBuilder::getProviderPlansHandlerInstance($provider);
			$provider_plan_uuid = $providerPlansHandler->createProviderPlan($db_internal_plan);
			//create it in DB
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				$provider_plan = new Plan();
				$provider_plan->setProviderId($provider->getId());
				$provider_plan->setPlanUid($provider_plan_uuid);
				$provider_plan->setName($db_internal_plan->getName());
				$provider_plan->setDescription($db_internal_plan->getDescription());
				$provider_plan->setInternalPlanId($db_internal_plan->getId());
				$provider_plan = PlanDAO::addPlan($provider_plan);
				//done
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			$db_internal_plan = InternalPlanDAO::getInternalPlanById($db_internal_plan->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while adding an Internal Plan to a provider, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an Internal Plan to a provider failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while adding an Internal Plan to a provider, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an Internal Plan to a provider failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_plan);
	}
	
	public function doAddToCountry(AddInternalPlanToCountryRequest $addInternalPlanToCountryRequest) {
		$internalPlanUuid = $addInternalPlanToCountryRequest->getInternalPlanUuid();
		$country = $addInternalPlanToCountryRequest->getCountry();
		$db_internal_plan = NULL;
		try {
			if(!Codes::isValid($country)) {
				$msg = $country." is NOT a valid ISO3166-1 country code";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_internal_plan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid, $addInternalPlanToCountryRequest->getPlatform()->getId());
			if($db_internal_plan == NULL) {
				$msg = "unknown internalPlanUuid : ".$internalPlanUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//linked to that country ?
			$internalPlanCountry = InternalPlanCountryDAO::getInternalPlanCountry($db_internal_plan->getId(), $country);
			if(isset($internalPlanCountry)) {
				$msg = "internal plan with internalPlanUuid : ".$internalPlanUuid." is already linked to country : ".$country;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanCountry = new InternalPlanCountry();
			$internalPlanCountry->setInternalPlanId($db_internal_plan->getId());
			$internalPlanCountry->setCountry($country);
			$internalPlanCountry = InternalPlanCountryDAO::addInternalPlanCountry($internalPlanCountry);
			//Done
			$db_internal_plan = InternalPlanDAO::getInternalPlanById($db_internal_plan->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while adding an Internal Plan to a country, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an Internal Plan to a country failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while adding an Internal Plan to a country, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an Internal Plan to a country failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_plan);	
	}
	
	public function doRemoveFromCountry(RemoveInternalPlanFromCountryRequest $removeInternalPlanFromCountryRequest) {
		$internalPlanUuid = $removeInternalPlanFromCountryRequest->getInternalPlanUuid();
		$country = $removeInternalPlanFromCountryRequest->getCountry();
		$db_internal_plan = NULL;
		try {
			$db_internal_plan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid, $removeInternalPlanFromCountryRequest->getPlatform()->getId());
			if($db_internal_plan == NULL) {
				$msg = "unknown internalPlanUuid : ".$internalPlanUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//linked to that country ?
			$internalPlanCountry = InternalPlanCountryDAO::getInternalPlanCountry($db_internal_plan->getId(), $country);
			if($internalPlanCountry == NULL) {
				$msg = "internal plan with internalPlanUuid : ".$internalPlanUuid." is NOT linked to country : ".$country;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			InternalPlanCountryDAO::deleteInternalPlanCountryById($internalPlanCountry->getId());
			//Done
			$db_internal_plan = InternalPlanDAO::getInternalPlanById($db_internal_plan->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while removing an Internal Plan from a country, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("removing an Internal Plan from a country failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while removing an Internal Plan from a country, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("removing an Internal Plan from a country failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_plan);		
	}
	
	public function doAddToContext(AddInternalPlanToContextRequest $addInternalPlanToContextRequest) {
		$internalPlanUuid = $addInternalPlanToContextRequest->getInternalPlanUuid();
		$contextBillingUuid = $addInternalPlanToContextRequest->getContextBillingUuid();
		$contextCountry = $addInternalPlanToContextRequest->getContextCountry();
		$db_internal_plan = NULL;
		try {
			if(!Codes::isValid($contextCountry)) {
				$msg = $country." is NOT a valid ISO3166-1 country code";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_internal_plan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid, $addInternalPlanToContextRequest->getPlatform()->getId());
			if($db_internal_plan == NULL) {
				$msg = "unknown internalPlanUuid : ".$internalPlanUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$context = ContextDAO::getContext($contextBillingUuid, $contextCountry, $addInternalPlanToContextRequest->getPlatform()->getId());
			if($context == NULL) {
				$msg = "unknown context with contextBillingUuid : ".$contextBillingUuid." AND contextCountry : ".$contextCountry;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::CONTEXT_NOT_FOUND);
			}
			//linked to that context ?
			$internalPlanContext = InternalPlanContextDAO::getInternalPlanContext($db_internal_plan->getId(), $context->getId());
			if(isset($internalPlanContext)) {
				$msg = "internal plan with internalPlanUuid : ".$internalPlanUuid." is already linked to the contextBillingUuid : ".$contextBillingUuid." and contextCountry : ".$contextCountry;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanCountry = InternalPlanCountryDAO::getInternalPlanCountry($db_internal_plan->getId(), $contextCountry);
			if($internalPlanCountry == NULL) {
				$msg = "internal plan with internalPlanUuid : ".$internalPlanUuid." must be available in the country : ".$contextCountry." in order to be added to that context";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);			
			}
			$internalPlanContext = new InternalPlanContext();
			$internalPlanContext->setInternalPlanId($db_internal_plan->getId());
			$internalPlanContext->setContextId($context->getId());
			$internalPlanContext = InternalPlanContextDAO::addInternalPlanContext($internalPlanContext);
			//Done
			$db_internal_plan = InternalPlanDAO::getInternalPlanById($db_internal_plan->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while adding an Internal Plan to a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an Internal Plan to a context failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while adding an Internal Plan to a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an Internal Plan to a context failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_plan);		
	}
	
	public function doRemoveFromContext(RemoveInternalPlanFromContextRequest $removeInternalPlanFromContextRequest) {
		$internalPlanUuid = $removeInternalPlanFromContextRequest->getInternalPlanUuid();
		$contextBillingUuid = $removeInternalPlanFromContextRequest->getContextBillingUuid();
		$contextCountry = $removeInternalPlanFromContextRequest->getContextCountry();
		$db_internal_plan = NULL;
		try {
			$db_internal_plan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid, $removeInternalPlanFromContextRequest->getPlatform()->getId());
			if($db_internal_plan == NULL) {
				$msg = "unknown internalPlanUuid : ".$internalPlanUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$context = ContextDAO::getContext($contextBillingUuid, $contextCountry, $removeInternalPlanFromContextRequest->getPlatform()->getId());
			if($context == NULL) {
				$msg = "unknown context with contextBillingUuid : ".$contextBillingUuid." AND contextCountry : ".$contextCountry;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::CONTEXT_NOT_FOUND);
			}
			//linked to that context ?
			$internalPlanContext = InternalPlanContextDAO::getInternalPlanContext($db_internal_plan->getId(), $context->getId());
			if($internalPlanContext == NULL) {
				$msg = "internal plan with internalPlanUuid : ".$internalPlanUuid." is NOT linked to the contextBillingUuid : ".$contextBillingUuid." and contextCountry : ".$contextCountry;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			InternalPlanContextDAO::deleteInternalPlanContextById($internalPlanContext->getId());
			//Done
			$db_internal_plan = InternalPlanDAO::getInternalPlanById($db_internal_plan->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while removing an Internal Plan from a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("removing an Internal Plan from a context failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while removing an Internal Plan from a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("removing an Internal Plan from a context failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_plan);
	}
	
	public function doUpdateInternalPlanOpts(UpdateInternalPlanRequest $updateInternalPlanRequest) {
		$internalPlanUuid = $updateInternalPlanRequest->getInternalPlanUuid();
		$internalplan_opts_array = $updateInternalPlanRequest->getInternalplanOptsArray();
		//
		$db_internal_plan = NULL;
		try {
			config::getLogger()->addInfo("internal plan opts updating...");
			$db_internal_plan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid, $updateInternalPlanRequest->getPlatform()->getId());
			if($db_internal_plan == NULL) {
				$msg = "unknown internalPlanUuid : ".$internalPlanUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_internal_plan_opts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($db_internal_plan->getId());
			$current_internalplan_opts_array = $db_internal_plan_opts->getOpts();
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				foreach ($internalplan_opts_array as $key => $value) {
					if(array_key_exists($key, $current_internalplan_opts_array)) {
						//UPDATE OR DELETE
						if(isset($value)) {
							InternalPlanOptsDAO::updateInternalPlanOptsKey($db_internal_plan->getId(), $key, $value);
						} else {
							InternalPlanOptsDAO::deleteInternalPlanOptsKey($db_internal_plan->getId(), $key);
						}
					} else {
						//ADD
						InternalPlanOptsDAO::addInternalPlanOptsKey($db_internal_plan->getId(), $key, $value);
					}
				}
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			//done
			$db_internal_plan = InternalPlanDAO::getInternalPlanById($db_internal_plan->getId());
			config::getLogger()->addInfo("internal plan opts updating done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating Internal Plan Opts, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internal plan opts updating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating Internal Plan Opts, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internal plan opts updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_plan);
	}
	
}

?>