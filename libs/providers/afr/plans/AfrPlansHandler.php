<?php

class AfrPlansHandler {
	
	public static $supported_currencies = array();
	public static $supported_cycles = array();
	public static $supported_periods = array();
	
	public static function init() {
		AfrPlansHandler::$supported_currencies = array('EUR');
		AfrPlansHandler::$supported_cycles = array(
				(new PlanCycle(PlanCycle::auto))->getValue(),
				(new PlanCycle(PlanCycle::once))->getValue()
		);
		
		AfrPlansHandler::$supported_periods = array(
				(new PlanPeriodUnit(PlanPeriodUnit::day))->getValue() => NULL,
				(new PlanPeriodUnit(PlanPeriodUnit::month))->getValue() => NULL,
				(new PlanPeriodUnit(PlanPeriodUnit::year))->getValue() => NULL
		);
	}
	
	public function __construct() {
	}
	
	public function createProviderPlan(InternalPlan $internalPlan) {
		$provider_plan_uuid = NULL;
		try {
			config::getLogger()->addInfo("afr plan creation...");
			if(!in_array($internalPlan->getCurrency(), AfrPlansHandler::$supported_currencies))  {
				$msg = "unsupported currency, must be in : ".implode(', ', AfrPlansHandler::$supported_currencies);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!in_array($internalPlan->getCycle()->getValue(), AfrPlansHandler::$supported_cycles)) {
				$msg = "unsupported cycle, must be in : ".implode(', ', AfrPlansHandler::$supported_cycles);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists($internalPlan->getPeriodUnit()->getValue(), AfrPlansHandler::$supported_periods)) {
				$msg = "unsupported period unit, must be in : ".implode(', ', array_keys(AfrPlansHandler::$supported_periods));
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$supported_period_length = AfrPlansHandler::$supported_periods[$internalPlan->getPeriodUnit()->getValue()];
			if(isset($supported_period_length) && !in_array($internalPlan->getPeriodLength(), $supported_period_length)) {
				$msg = "unsupported period length for this period unit, must be in : ".implode(', ', $supported_period_length);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//done
			$provider_plan_uuid = $internalPlan->getInternalPlanUuid();
			config::getLogger()->addInfo("afr plan creation done successfully, afr_plan_uuid=".$provider_plan_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a afr plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr plan creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a afr plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($provider_plan_uuid);
	}
	
}

AfrPlansHandler::init();

?>