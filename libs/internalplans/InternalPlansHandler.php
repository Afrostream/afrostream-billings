<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';

class InternalPlansHandler {
	
	public function __construct() {
	}
	
	public function doGetInternalPlan($internalPlanUuid) {
		$db_internal_plan = NULL;
		try {
			config::getLogger()->addInfo("internal plan getting, internalPlanUuid=".$internalPlanUuid."....");
			//
			$db_internal_plan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid);
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
	
	public function doGetInternalPlans($provider_name = NULL) {
		$db_internal_plans = NULL;
		try {
			config::getLogger()->addInfo("internal plans getting...");
			$provider_id = NULL;
			if(isset($provider_name)) {
				$provider = ProviderDAO::getProviderByName($provider_name);
				if($provider == NULL) {
					$msg = "unknown provider named : ".$provider_name;
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$provider_id = $provider->getId();
			}
			$db_internal_plans = InternalPlanDAO::getInternaPlans($provider_id);
			config::getLogger()->addInfo("internal plans getting done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting Internal Plans, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting Internal Plans, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_plans);
	}
	
}

?>