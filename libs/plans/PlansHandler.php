<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../providers/global/requests/GetProviderPlanRequest.php';
require_once __DIR__ . '/../providers/global/requests/AddPaymentMethodToProviderPlanRequest.php';
require_once __DIR__ . '/../providers/global/requests/RemovePaymentMethodFromProviderPlanRequest.php';
require_once __DIR__ . '/../providers/global/requests/UpdateProviderPlanRequest.php';

class PlansHandler {
	
	public function __construct() {
	}
	
	public function doGetProviderPlan(GetProviderPlanRequest $getProviderPlanRequest) {
		$providerPlanBillingUuid = $getProviderPlanRequest->getProviderPlanBillingUuid();
		$db_provider_plan = NULL;
		try {
			config::getLogger()->addInfo("provider plan getting, providerPlanBillingUuid=".$providerPlanBillingUuid."....");
			//
			$db_provider_plan = PlanDAO::getPlanByProviderPlanBillingUuid($providerPlanBillingUuid, $getProviderPlanRequest->getPlatform()->getId());
			//
			config::getLogger()->addInfo("= plan getting, providerPlanBillingUuid=".$providerPlanBillingUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting an provider plan for providerPlanBillingUuid=".$providerPlanBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("provider plan getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an provider plan for providerPlanBillingUuid=".$providerPlanBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("provider plan getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_provider_plan);
	}
	
	/**
	 * Add a PaymentMethod to the given ProviderPlan
	 * TODO : Has to check if the PaymentMethod given is supported by the Provider
	 * @param Plan $plan
	 * @param AddPaymentMethodToProviderPlanRequest $addPaymentMethodToProviderPlanRequest
	 * @throws BillingsException
	 * @return NULL|Plan
	 */
	public function doAddPaymentMethod(Plan $plan, AddPaymentMethodToProviderPlanRequest $addPaymentMethodToProviderPlanRequest) {
		try {
			config::getLogger()->addInfo("adding a PaymentMethod to a ProviderPlan...");
			$billingPaymentMethodTypeToAdd = new BillingPaymentMethodType($addPaymentMethodToProviderPlanRequest->getPaymentMethodType());
			$DBBillingPaymentMethod = BillingPaymentMethodDAO::getBillingPaymentMethodByPaymentMethodType($billingPaymentMethodTypeToAdd->getValue());
			if($DBBillingPaymentMethod == NULL) {
				//Exception
				$msg = "no paymentMethod found with paymentMethodType=".$billingPaymentMethodTypeToAdd->getValue();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$billingProviderPlanPaymentMethods = BillingProviderPlanPaymentMethodsDAO::getBillingProviderPlanPaymentMethodsByProviderPlanId($plan->getId());
			foreach ($billingProviderPlanPaymentMethods as $billingProviderPlanPaymentMethod) {
				if($billingProviderPlanPaymentMethod->getPaymentMethodId() == $DBBillingPaymentMethod->getId()) {
					//Exception
					//already Exists
					$msg = "paymentMethodType=".$billingPaymentMethodTypeToAdd->getValue()." is already linked to the providerPlan";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
			}
			//OK
			$billingProviderPlanPaymentMethod = new BillingProviderPlanPaymentMethod();
			$billingProviderPlanPaymentMethod->setProviderPlanId($plan->getId());
			$billingProviderPlanPaymentMethod->setPaymentMethodId($DBBillingPaymentMethod->getId());
			//Add It
			$billingProviderPlanPaymentMethod = BillingProviderPlanPaymentMethodsDAO::addBillingProviderPlanPaymentMethod($billingProviderPlanPaymentMethod);
			//done
			$plan = PlanDAO::getPlanById($plan->getId());
			config::getLogger()->addInfo("adding a PaymentMethod to a ProviderPlan done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while adding a PaymentMethod to a ProviderPlan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding a PaymentMethod to a ProviderPlan failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while adding a PaymentMethod to a ProviderPlan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding a PaymentMethod to a ProviderPlan failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($plan);
	}
	
	/**
	 * Remove a PaymentMethod from the given ProviderPlan
	 * @param Plan $plan
	 * @param RemovePaymentMethodFromProviderPlanRequest $removePaymentMethodFromProviderPlanRequest
	 * @throws BillingsException
	 */
	public function doRemovePaymentMethod(Plan $plan, RemovePaymentMethodFromProviderPlanRequest $removePaymentMethodFromProviderPlanRequest) {
		try {
			config::getLogger()->addInfo("removing a PaymentMethod from a ProviderPlan...");
			$billingPaymentMethodTypeToRemove = new BillingPaymentMethodType($removePaymentMethodFromProviderPlanRequest->getPaymentMethodType());
			$DBBillingPaymentMethod = BillingPaymentMethodDAO::getBillingPaymentMethodByPaymentMethodType($billingPaymentMethodTypeToRemove->getValue());
			if($DBBillingPaymentMethod == NULL) {
				//Exception
				$msg = "no paymentMethod found with paymentMethodType=".$billingPaymentMethodTypeToRemove->getValue();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$done = false;
			$billingProviderPlanPaymentMethods = BillingProviderPlanPaymentMethodsDAO::getBillingProviderPlanPaymentMethodsByProviderPlanId($plan->getId());
			foreach ($billingProviderPlanPaymentMethods as $billingProviderPlanPaymentMethod) {
				if($billingProviderPlanPaymentMethod->getPaymentMethodId() == $DBBillingPaymentMethod->getId()) {
					//Remove It
					BillingProviderPlanPaymentMethodsDAO::deleteBillingProviderPlanPaymentMethodById($billingProviderPlanPaymentMethod->getId());
					//done
					$done = true;
					break;
				}
			}
			if($done == false) {
				//Exception
				$msg = "paymentMethodType=".$billingPaymentMethodTypeToRemove->getValue()." is NOT linked to the providerPlan";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//done
			$plan = PlanDAO::getPlanById($plan->getId());
			config::getLogger()->addInfo("removing a PaymentMethod from a ProviderPlan done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while removing a PaymentMethod from a ProviderPlan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("removing a PaymentMethod from a ProviderPlan failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while removing a PaymentMethod from a ProviderPlan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("removing a PaymentMethod from a ProviderPlan failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($plan);
	}
	
	public function doUpdateProviderPlan(UpdateProviderPlanRequest $updateProviderPlanRequest) {
		$providerPlanBillingUuid = $updateProviderPlanRequest->getProviderPlanBillingUuid();
		//
		$db_provider_plan = NULL;
		try {
			config::getLogger()->addInfo("ProviderPlan updating...");
			$db_provider_plan = PlanDAO::getPlanByProviderPlanBillingUuid($providerPlanBillingUuid, $updateProviderPlanRequest->getPlatform()->getId());
			if($db_provider_plan == NULL) {
				$msg = "unknown providerPlanBillingUuid : ".$providerPlanBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				//opts
				if($updateProviderPlanRequest->getProviderPlanOptsArray() != NULL) {
					$db_provider_plan_opts = PlanOptsDAO::getPlanOptsByPlanId($db_provider_plan->getId());
					$current_provider_opts_array = $db_provider_plan_opts->getOpts();
					foreach ($updateProviderPlanRequest->getProviderPlanOptsArray() as $key => $value) {
						if(array_key_exists($key, $current_provider_opts_array)) {
							//UPDATE OR DELETE
							if(isset($value)) {
								PlanOptsDAO::updateProviderPlanOptsKey($db_provider_plan->getId(), $key, $value);
							} else {
								PlanOptsDAO::deleteProviderPlanOptsKey($db_provider_plan->getId(), $key);
							}
						} else {
							//ADD
							PlanOptsDAO::addProviderPlanOptsKey($db_provider_plan->getId(), $key, $value);
						}
					}
					$db_provider_plan = PlanDAO::getPlanById($db_provider_plan->getId());
				}
				//isVisible
				if($updateProviderPlanRequest->getIsVisible() !== NULL) {
					$db_provider_plan->setIsVisible($updateProviderPlanRequest->getIsVisible());
					$db_provider_plan = PlanDAO::updateIsVisible($db_provider_plan);
				}
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			//done
			config::getLogger()->addInfo("ProviderPlan updating done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating ProviderPlan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("ProviderPlan updating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating ProviderPlan, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("ProviderPlan updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_provider_plan);
	}
		
}

?>