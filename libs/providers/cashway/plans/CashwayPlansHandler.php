<?php

class CashwayPlansHandler {
	
	public static $supported_currencies = array();
	public static $supported_cycles = array();
	public static $supported_periods = array();
	
	public static function init() {
		CashwayPlansHandler::$supported_currencies = array('EUR');
		CashwayPlansHandler::$supported_cycles = array(
				(new PlanCycle(PlanCycle::once))->getValue()
		);
		
		CashwayPlansHandler::$supported_periods = array(
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
			config::getLogger()->addInfo("cashway plan creation...");
			if(!in_array($internalPlan->getCurrency(), CashwayPlansHandler::$supported_currencies))  {
				$msg = "unsupported currency, must be in : ".implode(', ', CashwayPlansHandler::$supported_currencies);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!in_array($internalPlan->getCycle()->getValue(), CashwayPlansHandler::$supported_cycles)) {
				$msg = "unsupported cycle, must be in : ".implode(', ', CashwayPlansHandler::$supported_cycles);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists($internalPlan->getPeriodUnit()->getValue(), CashwayPlansHandler::$supported_periods)) {
				$msg = "unsupported period unit, must be in : ".implode(', ', array_keys(CashwayPlansHandler::$supported_periods));
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$supported_period_length = CashwayPlansHandler::$supported_periods[$internalPlan->getPeriodUnit()->getValue()];
			if(isset($supported_period_length) && !in_array($internalPlan->getPeriodLength(), $supported_period_length)) {
				$msg = "unsupported period length for this period unit, must be in : ".implode(', ', $supported_period_length);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//done
			$provider_plan_uuid = $internalPlan->getInternalPlanUuid();
			config::getLogger()->addInfo("cashway plan creation done successfully, cashway_plan_uuid=".$provider_plan_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a cashway plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway plan creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a cashway plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($provider_plan_uuid);
	}
	
}

CashwayPlansHandler::init();

?>