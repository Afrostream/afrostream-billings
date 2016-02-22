<?php

class GocardlessPlansHandler {
	
	private static $supported_currencies = array();
	private static $supported_cycles = array();
	private static $supported_periods = array();
	
	public static function init() {
		GocardlessPlansHandler::$supported_currencies = array('EUR');
		GocardlessPlansHandler::$supported_cycles = array(
				(new PlanCycle(PlanCycle::auto))->getValue(),
				(new PlanCycle(PlanCycle::once))->getValue()
		);
	
		GocardlessPlansHandler::$supported_periods = array(
				(new PlanPeriodUnit(PlanPeriodUnit::day))->getValue() => NULL,
				(new PlanPeriodUnit(PlanPeriodUnit::month))->getValue() => NULL,
				(new PlanPeriodUnit(PlanPeriodUnit::year))->getValue() => NULL,
		);
	}
	
	public function __construct() {
	}
		
}

GocardlessPlansHandler::init();

?>