<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../users/UsersHandler.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class UsersController {

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
			$provider_name = $data['providerName'];
			$user_reference_uuid = $data['userReferenceUuid'];
			$user_opts_array = $data['userOpts'];
			$usersHandler = new UsersHandler();
			$user = $usersHandler->doGetOrCreateUser($provider_name, $user_reference_uuid, $user_opts_array);
			return($this->returnUserAsJson($response, $user));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while creating an user, error_type=".$e->getExceptionType().",error_code=".$e->getCode().", error_message=".$e->getMessage();
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
	
	private function returnBillingsExceptionAsJson(Response $response, BillingsException $e) {
		$json_as_array = array();
		$json_as_array['status'] = 'error';
		$json_as_array['statusMessage'] = $e->getMessage();
		$json_as_array['statusCode'] = $e->getCode();
		$json_as_array['statusType'] =  (string) $e->getExceptionType();
		$json_error_as_array = array(
				"error" => array(
							"errorMessage" => $e->getMessage(),
							"errorType" => (string) $e->getExceptionType(),
							"errorCode" => $e->getCode()
						)

		);
		$json_as_array['errors'][] = $json_error_as_array;
		$json = json_encode($json_as_array);
		$response = $response->withHeader('Content-Type', 'application/json');
		$response->getBody()->write($json);
		return($response);
	}
	
	private function returnExceptionAsJson(Response $response, Exception $e) {
		$json_as_array = array();
		$json_as_array['status'] = 'error';
		$json_as_array['statusMessage'] = $e->getMessage();
		$json_as_array['statusCode'] = $e->getCode();
		$json_as_array['statusType'] =  'unknown';
		$json_error_as_array = array(
				"error" => array(
							"errorMessage" => $e->getMessage(),
							"errorType" => 'unknown',
							"errorCode" => $e->getCode()
						)

		);
		$json_as_array['errors'][] = $json_error_as_array;
		$json = json_encode($json_as_array);
		$response = $response->withHeader('Content-Type', 'application/json');
		$response->getBody()->write($json);
		return($response);
	}
	
	private function returnUserAsJson(Response $response, User $user) {
		//
		$json_as_array = array();
		$json_as_array['status'] = 'done';
		$json_as_array['statusMessage'] = 'success';
		$json_as_array['statusCode'] = 0;
		$json_user = json_encode($user, JSON_UNESCAPED_UNICODE);
		$json_as_array['response']['user'] = json_decode($json_user, true);
		//
		$json = json_encode($json_as_array);
		$response = $response->withHeader('Content-Type', 'application/json');
		$response->getBody()->write($json);
		return($response);
	}
	
}

?>