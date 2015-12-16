<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../users/UsersHandler.php';

class UsersController {

	public function create() {
		try {
			$data = json_decode(file_get_contents('php://input'), true);
			if(!isset($data['provider_name'])) {
				//exception
				$msg = "field 'provider_name' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!isset($data['user_reference_uuid'])) {
				//exception
				$msg = "field 'user_reference_uuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!isset($data['user_opts'])) {
				//exception
				$msg = "field 'user_opts' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			} else {
				if(!is_array($data['user_opts'])) {
					//exception
					$msg = "field 'user_opts' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
			}
			$provider_name = $data['provider_name'];
			$user_reference_uuid = $data['user_reference_uuid'];
			$user_opts_array = $data['user_opts'];
			$usersHandler = new UsersHandler();
			$user = $usersHandler->doGetOrCreateUser($provider_name, $user_reference_uuid, $user_opts_array);
			$this->returnAsJson($user);
		} catch(BillingsException $e) {
			$msg = "an exception occurred while creating an user, error_type=".$e->getExceptionType().",error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			echo $msg;
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating an user, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			echo $msg;
			//
		}
	}
	
	private function returnAsJson(User $user) {
		$json = json_encode($user, JSON_UNESCAPED_UNICODE);
		header('Content-Type: application/json');
		echo $json;
	}
	
}

?>