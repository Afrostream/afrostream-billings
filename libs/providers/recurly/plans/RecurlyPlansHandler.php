<?php

class RecurlyPlansHandler {

	public function __construct() {
	}
	
	public function createProviderPlan(InternalPlan $internaPlan) {
		$provider_plan_uuid = NULL;
		try {
			config::getLogger()->addInfo("recurly plan creation...");
			Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
			Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
			$plan = new Recurly_Plan();
			$plan->plan_code = $internaPlan->getInternalPlanUuid();
			$plan->name = $internaPlan->getName();
			$plan->description = $internaPlan->getDescription();
			$plan->unit_amount_in_cents->addCurrency($internaPlan->getCurrency(), $internaPlan->getAmountInCents());
			switch ($internaPlan->getCycle()) {
				case PlanCycle::once :
					$plan->total_billing_cycles = 1;
					break;
				case PlanCycle::auto :
					$plan->total_billing_cycles = NULL;
					break;
				default :
					$msg = "unknown cycle : ".$internaPlan->getCycle()->getValue();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			switch($internaPlan->getPeriodUnit()) {
				case PlanPeriodUnit::day :
					$plan->plan_interval_unit = 'days';
					$plan->plan_interval_length = 1 * $internaPlan->getPeriodLength();
					break;
				case PlanPeriodUnit::month :
					$plan->plan_interval_unit = 'months';
					$plan->plan_interval_length = 1 * $internaPlan->getPeriodLength();
					break;
				case PlanPeriodUnit::year :
					$plan->plan_interval_unit = 'months';
					$plan->plan_interval_length = 12 * $internaPlan->getPeriodLength();
					break;
				default :
					$msg = "unknown periodUnit : ".$internaPlan->getPeriodUnit()->getValue();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			$plan->create();
			$provider_plan_uuid = $plan->plan_code;
			config::getLogger()->addInfo("recurly plan creation done successfully, recurly_plan_uuid=".$provider_plan_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a recurly plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly plan creation failed : ".$msg);
			throw $e;
		} catch (Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while creating a recurly plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a recurly plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($provider_plan_uuid);
	}
	
}


?>