<?php

class BachatPlansHandler {
	
	private static $supported_currencies = array();
	private static $supported_cycles = array();
	private static $supported_periods = array();
	
	public static function init() {
		BachatPlansHandler::$supported_currencies = array('EUR');
		BachatPlansHandler::$supported_cycles = array(
				(new PlanCycle(PlanCycle::auto))->getValue(),
				(new PlanCycle(PlanCycle::once))->getValue()
		);
		
		BachatPlansHandler::$supported_periods = array(
				(new PlanPeriodUnit(PlanPeriodUnit::day))->getValue() => array(1,30)
		);
	}
	
	public function __construct() {
	}
	
	public function createProviderPlan(InternalPlan $internaPlan) {
		$provider_plan_uuid = NULL;
		try {
			config::getLogger()->addInfo("bachat plan creation...");
			if(!in_array($internaPlan->getCurrency(), BachatPlansHandler::$supported_currencies))  {
				$msg = "unsupported currency, must be in : ".implode(', ', BachatPlansHandler::$supported_currencies);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!in_array($internaPlan->getCycle()->getValue(), BachatPlansHandler::$supported_cycles)) {
				$msg = "unsupported cycle, must be in : ".implode(', ', BachatPlansHandler::$supported_cycles);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists($internaPlan->getPeriodUnit()->getValue(), BachatPlansHandler::$supported_periods)) {
				$msg = "unsupported period unit, must be in : ".implode(', ', array_keys(BachatPlansHandler::$supported_periods));
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$supported_period_length = BachatPlansHandler::$supported_periods[$internaPlan->getPeriodUnit()->getValue()];
			if(!in_array($internaPlan->getPeriodLength(), $supported_period_length)) {
				$msg = "unsupported period length for this period unit, must be in : ".implode(', ', $supported_period_length);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//done
			$provider_plan_uuid = $internaPlan->getInternalPlanUuid();
			config::getLogger()->addInfo("bachat plan creation done successfully, bachat_plan_uuid=".$provider_plan_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a bachat plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("bachat plan creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a bachat plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("bachat subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($provider_plan_uuid);
	}
	
}

BachatPlansHandler::init();


?>