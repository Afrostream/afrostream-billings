<?php

require_once __DIR__ . '/../../global/plans/ProviderPlansHandler.php';

class BraintreePlansHandler extends ProviderPlansHandler {
	
	public function createProviderPlan(InternalPlan $internalPlan) {
		$provider_plan_uuid = NULL;
		try {
			config::getLogger()->addInfo("braintree plan creation...");
			Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
			Braintree_Configuration::merchantId($this->provider->getMerchantId());
			Braintree_Configuration::publicKey($this->provider->getApiKey());
			Braintree_Configuration::privateKey($this->provider->getApiSecret());
			//
			$currentBraintreePlan = NULL;
			$braintreePlans = Braintree\Plan::all();
			foreach($braintreePlans as $braintreePlan) {
				if($braintreePlan->id == $internalPlan->getInternalPlanUuid()) {
					$currentBraintreePlan = $braintreePlan;
					break;
				}
			}
			if($currentBraintreePlan == NULL) {
				//exception
				$msg = "braintree plan not found, it must be created before from braintree console";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//check
			//amount <-> price
			if($internalPlan->getAmountInCents() != intval(floatval($currentBraintreePlan->price) * 100)) {
				//exception
				$msg = "amount are different ".$internalPlan->getAmountInCents()."<>".intval(floatval($currentBraintreePlan->price) * 100);
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//currency
			if($internalPlan->getCurrency() != $currentBraintreePlan->currencyIsoCode) {
				//exception
				$msg = "currency are different ".$internalPlan->getCurrency()."<>".$currentBraintreePlan->currencyIsoCode;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($internalPlan->getPeriodUnit() != PlanPeriodUnit::month) {
				//exception
				$msg = "periodUnit must be 'month', current value is : ".$internalPlan->getPeriodUnit();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($internalPlan->getPeriodLength() != $currentBraintreePlan->billingFrequency) {
				//exception
				$msg = "periodLength are different ".$internalPlan->getPeriodLength()."<>".$currentBraintreePlan->billingFrequency;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//cycle
			switch($internalPlan->getCycle()) {
				case 'once' :
					if($currentBraintreePlan->numberOfBillingCycles != 1) {
						//exception
						$msg = "cycle is 'once', numberOfBillingCycles should be 1, current value is : ".$currentBraintreePlan->numberOfBillingCycles;
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					break;
				case 'auto' :
					if($currentBraintreePlan->numberOfBillingCycles != NULL) {
						//exception
						$msg = "cycle is 'auto', numberOfBillingCycles should be NULL, current value is : ".$currentBraintreePlan->numberOfBillingCycles;
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					break;
				default : 
					//exception
					$msg = "unknown cycle : ".$internalPlan->getCycle();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			//trial period
			switch($internalPlan->getTrialEnabled()) {
				case true :
					if($currentBraintreePlan->trialPeriod != 'true') {
						//exception
						$msg = "trialPeriod must be enabled";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					//
					if($internalPlan->getTrialPeriodUnit() != $currentBraintreePlan->trialDurationUnit) {
						//exception
						$msg = "trialPeriodUnit are different ".$internalPlan->getTrialPeriodUnit()."<>".$currentBraintreePlan->trialDurationUnit;
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					if($internalPlan->getTrialPeriodLength() != $currentBraintreePlan->trialDuration) {
						//exception
						$msg = "trialPeriodLength are different ".$internalPlan->getTrialPeriodLength()."<>".$currentBraintreePlan->trialDuration;
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					break;
				case false;
					if($currentBraintreePlan->trialPeriod == 'true') {
						//exception
						$msg = "trialPeriod must be disabled";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
				break;
			}
			//done
			$provider_plan_uuid = $currentBraintreePlan->id;
			config::getLogger()->addInfo("braintree plan creation done successfully, braintree_plan_uuid=".$provider_plan_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a braintree plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree plan creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a braintree plan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree plan creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($provider_plan_uuid);
	}
	
}

?>