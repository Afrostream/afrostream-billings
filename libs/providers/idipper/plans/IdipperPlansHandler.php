<?php

class IdipperPlansHandler {
	
	private static $supported_currencies = array();
	private static $supported_cycles = array();
	private static $supported_periods = array();
	
	public static function init() {
		IdipperPlansHandler::$supported_currencies = array('EUR');
		IdipperPlansHandler::$supported_cycles = array(
				(new PlanCycle(PlanCycle::auto))->getValue()
		);
		
		IdipperPlansHandler::$supported_periods = array(
				(new PlanPeriodUnit(PlanPeriodUnit::day))->getValue() => array(7,30)
		);
	}
	
	public function __construct() {
	}
	
	public function createProviderPlan(InternalPlan $internaPlan) {
		$provider_plan_uuid = NULL;
		try {
			config::getLogger()->addInfo("Idipper plan creation...");
			if(!in_array($internaPlan->getCurrency(), IdipperPlansHandler::$supported_currencies))  {
				$msg = "unsupported currency, must be in : ".implode(', ', IdipperPlansHandler::$supported_currencies);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!in_array($internaPlan->getCycle()->getValue(), IdipperPlansHandler::$supported_cycles)) {
				$msg = "unsupported cycle, must be in : ".implode(', ', IdipperPlansHandler::$supported_cycles);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists($internaPlan->getPeriodUnit()->getValue(), IdipperPlansHandler::$supported_periods)) {
				$msg = "unsupported period unit, must be in : ".implode(', ', array_keys(IdipperPlansHandler::$supported_periods));
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$supported_period_length = IdipperPlansHandler::$supported_periods[$internaPlan->getPeriodUnit()->getValue()];
			if(!in_array($internaPlan->getPeriodLength(), $supported_period_length)) {
				$msg = "unsupported period length for this period unit, must be in : ".implode(', ', $supported_period_length);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//done
			$provider_plan_uuid = $internaPlan->getInternalPlanUuid();
			config::getLogger()->addInfo("idipper plan creation done successfully, idipper_plan_uuid=".$provider_plan_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a idipper plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("idipper plan creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a idipper plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("idipper subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($provider_plan_uuid);
	}
	
}

IdipperPlansHandler::init();


?>