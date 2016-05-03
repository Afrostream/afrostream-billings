<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';

use Iso3166\Codes;

class ContextsHandler {
	
	public function __construct() {
	}

	public function doGetContext($contextBillingUuid, $contextCountry) {
		$db_context = NULL;
		try {
			config::getLogger()->addInfo("context getting, contextBillingUuid=".$contextBillingUuid.", contextCountry=".$contextCountry."....");
			//
			$db_context = ContextDAO::getContext($contextBillingUuid, $contextCountry);
			//
			config::getLogger()->addInfo("context getting, contextBillingUuid=".$contextBillingUuid.", contextCountry=".$contextCountry." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting a context for contextBillingUuid=".$contextBillingUuid.", contextCountry=".$contextCountry.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("context getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a context for contextBillingUuid=".$contextBillingUuid.", contextCountry=".$contextCountry." error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("context getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_context);
	}
	
	public function doGetContexts($contextCountry = NULL) {
		$db_contexts = NULL;
		try {
			config::getLogger()->addInfo("contexts getting...");
			$db_contexts = ContextDAO::getContexts($contextCountry);
			config::getLogger()->addInfo("contexts getting done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting contexts, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("contexts getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting contexts, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("contexts getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_contexts);
	}
	
	public function doCreate($contextBillingUuid, $contextCountry, $name, $description) {
		$db_context = NULL;
		try {
			config::getLogger()->addInfo("context creating...");
			//checks
			if(!Codes::isValid($contextCountry)) {
				$msg = $contextCountry." is NOT a valid ISO3166-1 country code";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_tmp_context = ContextDAO::getContext($contextBillingUuid, $contextCountry);
			if(isset($db_tmp_context)) {
				$msg = "a context with the same contextBillingUuid=".$contextBillingUuid.", contextCountry=".$contextCountry." already exists";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//OK
			$db_context = new Context();
			$db_context->setContextUuid($contextBillingUuid);
			$db_context->setContextCountry($contextCountry);
			$db_context->setName($name);
			$db_context->setDescription($description);
			$db_context = ContextDAO::addContext($db_context);
			config::getLogger()->addInfo("context creating done successfully, contextId=".$db_context->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("context creating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("context creating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_context);
	}
	
	public function doAddInternalPlanToContext($contextBillingUuid, $contextCountry, $internalPlanUuid) {
		$context = NULL;
		try {
			if(!Codes::isValid($contextCountry)) {
				$msg = $country." is NOT a valid ISO3166-1 country code";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_internal_plan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid);
			if($db_internal_plan == NULL) {
				$msg = "unknown internalPlanUuid : ".$internalPlanUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$context = ContextDAO::getContext($contextBillingUuid, $contextCountry);
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
			$context = ContextDAO::getContextById($context->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while adding an Internal Plan to a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an Internal Plan to a context failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while adding an Internal Plan to a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an Internal Plan to a context failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($context);
	}
	
	public function doRemoveInternalPlanFromContext($contextBillingUuid, $contextCountry, $internalPlanUuid) {
		$context = NULL;
		try {
			$db_internal_plan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid);
			if($db_internal_plan == NULL) {
				$msg = "unknown internalPlanUuid : ".$internalPlanUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$context = ContextDAO::getContext($contextBillingUuid, $contextCountry);
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
			$context = ContextDAO::getContextById($context->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while removing an Internal Plan from a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("removing an Internal Plan from a context failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while removing an Internal Plan from a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("removing an Internal Plan from a context failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($context);
	}
	
	public function doSetInternalPlanIndexInContext($contextBillingUuid, $contextCountry, $internalPlanUuid, $index) {
		$context = NULL;
		try {
			if(!(ctype_digit($index)) || !($index > 0)) {
				$msg = "index must be a positive or zero integer";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_internal_plan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid);
			if($db_internal_plan == NULL) {
				$msg = "unknown internalPlanUuid : ".$internalPlanUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$context = ContextDAO::getContext($contextBillingUuid, $contextCountry);
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
			$maxIndex = InternalPlanContextDAO::getMaxIndex($context->getId());
			if($index > $maxIndex) {
				$msg = "index cannot exceed the number of internalPlans in the context : ( ".$index. " > " .$maxIndex. " )";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($internalPlanContext->getIndex() != $index) {
				$internalPlanContext->setIndex($index);
				$internalPlanContext = InternalPlanContextDAO::updateIndex($internalPlanContext);
			}
			//Done
			$context = ContextDAO::getContextById($context->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while moving an Internal Plan in a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("moving an Internal Plan in a context failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while moving an Internal Plan in a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("moving an Internal Plan in a context failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($context);
	}
}

?>