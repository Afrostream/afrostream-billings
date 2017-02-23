<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../contexts/ContextsHandler.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../providers/global/requests/GetContextRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetContextsRequest.php';
require_once __DIR__ . '/../providers/global/requests/CreateContextRequest.php';
require_once __DIR__ . '/../providers/global/requests/AddInternalPlanToContextRequest.php';
require_once __DIR__ . '/../providers/global/requests/RemoveInternalPlanFromContextRequest.php';
require_once __DIR__ . '/../providers/global/requests/SetInternalPlanIndexInContextRequest.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class ContextsController extends BillingsController {
	
	public function getOne(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			if(!isset($args['contextBillingUuid'])) {
				//exception
				$msg = "field 'contextBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextBillingUuid = $args['contextBillingUuid'];
			if(!isset($args['contextCountry'])) {
				//exception
				$msg = "field 'contextCountry' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextCountry = $args['contextCountry'];
			//
			$contextHandler = new ContextsHandler();
			$getContextRequest = new GetContextRequest();
			$getContextRequest->setContextBillingUuid($contextBillingUuid);
			$getContextRequest->setContextCountry($contextCountry);
			$getContextRequest->setOrigin('api');
			$context = $contextHandler->doGetContext($getContextRequest);
			if($context == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'context', $context));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting a context, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function getMulti(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$contextCountry = NULL;
			if(isset($data['contextCountry'])) {
				$contextCountry = $data['contextCountry'];
			}
			//
			$contextHandler = new ContextsHandler();
			$getContextsRequest = new GetContextsRequest();
			$getContextsRequest->setContextCountry($contextCountry);
			$getContextsRequest->setOrigin('api');
			$contexts = $contextHandler->doGetContexts($getContextsRequest);
			return($this->returnObjectAsJson($response, 'contexts', $contexts));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting contexts, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting contexts, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function create(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			if(!isset($data['contextBillingUuid'])) {
				//exception
				$msg = "field 'contextBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextBillingUuid = $data["contextBillingUuid"];
			if(!isset($data['contextCountry'])) {
				//exception
				$msg = "field 'contextCountry' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextCountry = $data["contextCountry"];
			if(!isset($data['name'])) {
				//exception
				$msg = "field 'name' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$name = $data["name"];
			if(!isset($data['description'])) {
				//exception
				$msg = "field 'description' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$description = $data["description"];
			$contextsHandler = new ContextsHandler();
			$createContextRequest = new CreateContextRequest();
			$createContextRequest->setContextBillingUuid($contextBillingUuid);
			$createContextRequest->setContextCountry($contextCountry);
			$createContextRequest->setName($name);
			$createContextRequest->setDescription($description);
			$createContextRequest->setOrigin('api');
			$context = $contextsHandler->doCreate($createContextRequest);
			return($this->returnObjectAsJson($response, 'context', $context));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while creating a context, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function addInternalPlanToContext(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			if(!isset($args['contextBillingUuid'])) {
				//exception
				$msg = "field 'contextBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextBillingUuid = $args['contextBillingUuid'];
			if(!isset($args['contextCountry'])) {
				//exception
				$msg = "field 'contextCountry' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextCountry = $args['contextCountry'];
			$contextsHandler = new ContextsHandler();
			$addInternalPlanToContextRequest = new AddInternalPlanToContextRequest();
			$addInternalPlanToContextRequest->setContextBillingUuid($contextBillingUuid);
			$addInternalPlanToContextRequest->setContextCountry($contextCountry);
			$addInternalPlanToContextRequest->setInternalPlanUuid($internalPlanUuid);
			$addInternalPlanToContextRequest->setOrigin('api');
			$context = $contextsHandler->doAddInternalPlanToContext($addInternalPlanToContextRequest);
			return($this->returnObjectAsJson($response, 'context', $context));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while linking an internal plan to a context, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while linking an internal plan to a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function removeInternalPlanFromContext(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			if(!isset($args['contextBillingUuid'])) {
				//exception
				$msg = "field 'contextBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextBillingUuid = $args['contextBillingUuid'];
			if(!isset($args['contextCountry'])) {
				//exception
				$msg = "field 'contextCountry' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextCountry = $args['contextCountry'];
			$contextsHandler = new ContextsHandler();
			$removeInternalPlanFromContextRequest = new RemoveInternalPlanFromContextRequest();
			$removeInternalPlanFromContextRequest->setContextBillingUuid($contextBillingUuid);
			$removeInternalPlanFromContextRequest->setContextCountry($contextCountry);
			$removeInternalPlanFromContextRequest->setInternalPlanUuid($internalPlanUuid);
			$removeInternalPlanFromContextRequest->setOrigin('api');
			$context = $contextsHandler->doRemoveInternalPlanFromContext($removeInternalPlanFromContextRequest);
			return($this->returnObjectAsJson($response, 'context', $context));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while removing an internal plan from a context, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while removing an internal plan from a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function setInternalPlanIndexInContext(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			if(!isset($args['contextBillingUuid'])) {
				//exception
				$msg = "field 'contextBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextBillingUuid = $args['contextBillingUuid'];
			if(!isset($args['contextCountry'])) {
				//exception
				$msg = "field 'contextCountry' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$contextCountry = $args['contextCountry'];
			if(!isset($args['index'])) {
				//exception
				$msg = "field 'index' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$index = $args['index'];			
			$contextsHandler = new ContextsHandler();
			$setInternalPlanIndexInContextRequest = new SetInternalPlanIndexInContextRequest();
			$setInternalPlanIndexInContextRequest->setContextBillingUuid($contextBillingUuid);
			$setInternalPlanIndexInContextRequest->setContextCountry($contextCountry);
			$setInternalPlanIndexInContextRequest->setInternalPlanUuid($internalPlanUuid);
			$setInternalPlanIndexInContextRequest->setIndex($index);
			$setInternalPlanIndexInContextRequest->setOrigin('api');
			$context = $contextsHandler->doSetInternalPlanIndexInContext($setInternalPlanIndexInContextRequest);
			return($this->returnObjectAsJson($response, 'context', $context));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while moving an internal plan in a context, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while moving an internal plan in a context, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}

?>