<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../users/UsersHandler.php';
require_once __DIR__ .'/BillingsController.php';
require_once __DIR__ . '/../providers/global/requests/GetUserRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetUsersRequest.php';
require_once __DIR__ . '/../providers/global/requests/CreateUserRequest.php';
require_once __DIR__ . '/../providers/global/requests/UpdateUserRequest.php';
require_once __DIR__ . '/../providers/global/requests/UpdateUsersRequest.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class UsersController extends BillingsController {
	
	public function get(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$userBillingUuid = NULL;
			$providerName = NULL;
			$userReferenceUuuid = NULL;
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
				$providerName = $data['providerName'];
				$userReferenceUuid = $data['userReferenceUuid'];
			}
			//
			$usersHandler = new UsersHandler();
			$getUserRequest = new GetUserRequest();
			$getUserRequest->setOrigin('api');
			$getUserRequest->setUserBillingUuid($userBillingUuid);
			$getUserRequest->setProviderName($providerName);
			$getUserRequest->setUserReferenceUuid($userReferenceUuid);
			$user = $usersHandler->doGetUser($getUserRequest);
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
			$providerName = $data['providerName'];
			$userReferenceUuid = $data['userReferenceUuid'];
			$userOptsArray = $data['userOpts'];
			$userProviderUuid = NULL;
			if(isset($data['userProviderUuid'])) {
				$userProviderUuid = $data['userProviderUuid'];
			}
			//
			$usersHandler = new UsersHandler();
			
			$createUserRequest = new CreateUserRequest();
			$createUserRequest->setOrigin('api');
			$createUserRequest->setProviderName($providerName);
			$createUserRequest->setUserReferenceUuid($userReferenceUuid);
			$createUserRequest->setUserOpts($userOptsArray);
			$createUserRequest->setUserProviderUuid($userProviderUuid);
			$user = $usersHandler->doGetOrCreateUser($createUserRequest);
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
				$userOptsArray = $data['userOpts'];
				$updateUserRequest = new UpdateUserRequest();
				$updateUserRequest->setOrigin('api');
				$updateUserRequest->setUserBillingUuid($userBillingUuid);
				$updateUserRequest->setUserOpts($userOptsArray);
				$user = $usersHandler->doUpdateUserOpts($updateUserRequest);
			}
			if($user == NULL) {
				//NO UPDATE, JUST SEND BACK THE CURRENT USER
				$getUserRequest = new GetUserRequest();
				$getUserRequest->setOrigin('api');
				$getUserRequest->setUserBillingUuid($userBillingUuid);
				$user = $usersHandler->doGetUser($getUserRequest);
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
	
	public function updateUsers(Request $request, Response $response, array $args) {
		try {
			$query_params = $request->getQueryParams();
			$data = json_decode($request->getBody(), true);
			if(!isset($query_params['userReferenceUuid'])) {
				//exception
				$msg = "field 'userReferenceUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$userReferenceUuid = $query_params['userReferenceUuid'];
			$usersHandler = new UsersHandler();
			$users = NULL;
			if(isset($data['userOpts'])) {
				if(!is_array($data['userOpts'])) {
					//exception
					$msg = "field 'userOpts' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$userOptsArray = $data['userOpts'];
				$getUsersRequest = new GetUsersRequest();
				$getUsersRequest->setOrigin('api');
				$getUsersRequest->setUserReferenceUuid($userReferenceUuid);
				$users_to_update = $usersHandler->doGetUsers($getUsersRequest);
				if(count($users_to_update) == 0) {
					return($this->returnNotFoundAsJson($response));
				}
				$users = array();
				foreach($users_to_update as $user) {
					$updateUserRequest = new UpdateUserRequest();
					$updateUserRequest->setOrigin('api');
					$updateUserRequest->setUserBillingUuid($user->getUserBillingUuid());
					$updateUserRequest->setUserOpts($userOptsArray);
					$users[] = $usersHandler->doUpdateUserOpts($updateUserRequest);
				}
			}
			if($users == NULL) {
				//NO UPDATE, JUST SEND BACK THE CURRENT USER
				$getUsersRequest = new GetUsersRequest();
				$getUsersRequest->setOrigin('api');
				$getUsersRequest->setUserReferenceUuid($userReferenceUuid);
				$users = $usersHandler->doGetUsers($getUsersRequest);
			}
			return($this->returnObjectAsJson($response, 'users', $users));
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