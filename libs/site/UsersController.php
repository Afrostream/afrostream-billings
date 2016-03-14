<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../users/UsersHandler.php';
require_once __DIR__ .'/BillingsController.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class UsersController extends BillingsController {
	
	public function get(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$userBillingUuid = NULL;
			$provider_name = NULL;
			$user_reference_uuid = NULL;
			if(isset($args['userBillingUuid'])) {
				$userBillingUuid = $args['userBillingUuid'];
			} else {
				if(!isset($data['providerName'])) {
					//exception
					$msg = "field 'providerName' is missing";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if(!isset($data['userReferenceUuid'])) {
					//exception
					$msg = "field 'userReferenceUuid' is missing";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$provider_name = $data['providerName'];
				$user_reference_uuid = $data['userReferenceUuid'];
			}
			//
			$usersHandler = new UsersHandler();
			if(isset($userBillingUuid)) {
				$user = $usersHandler->doGetUserByUserBillingUuid($userBillingUuid);
			} else {
				$user = $usersHandler->doGetUser($provider_name, $user_reference_uuid);
			}
			if($user == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'user', $user));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting an user, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an user, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}		
	}

	public function create(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			if(!isset($data['providerName'])) {
				//exception
				$msg = "field 'providerName' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!isset($data['userReferenceUuid'])) {
				//exception
				$msg = "field 'userReferenceUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!isset($data['userOpts'])) {
				//exception
				$msg = "field 'userOpts' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			} else {
				if(!is_array($data['userOpts'])) {
					//exception
					$msg = "field 'userOpts' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
			}
			//
			$provider_name = $data['providerName'];
			$user_reference_uuid = $data['userReferenceUuid'];
			$user_opts_array = $data['userOpts'];
			$user_provider_uuid = NULL;
			if(isset($data['userProviderUuid'])) {
				$user_provider_uuid = $data['userProviderUuid'];
			}
			//
			$usersHandler = new UsersHandler();
			$user = $usersHandler->doGetOrCreateUser($provider_name, $user_reference_uuid, $user_provider_uuid, $user_opts_array);
			return($this->returnObjectAsJson($response, 'user', $user));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while creating an user, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating an user, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function update(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			if(!isset($args['userBillingUuid'])) {
				//exception
				$msg = "field 'userBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$userBillingUuid = $args['userBillingUuid'];
			$usersHandler = new UsersHandler();
			$user = NULL;
			if(isset($data['userOpts'])) {
				if(!is_array($data['userOpts'])) {
					//exception
					$msg = "field 'userOpts' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$user_opts_array = $data['userOpts'];
				$user = $usersHandler->doUpdateUserOpts($userBillingUuid, $user_opts_array);
			}
			if($user == NULL) {
				//NO UPDATE, JUST SEND BACK THE CURRENT USER
				$user = $usersHandler->doGetUserByUserBillingUuid($userBillingUuid);
			}
			return($this->returnObjectAsJson($response, 'user', $user));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while updating an user, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating an user, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}

?>