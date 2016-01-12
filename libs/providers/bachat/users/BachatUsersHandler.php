<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';
require_once __DIR__ . '/../../../../libs/utils/BillingsException.php';

class BachatUsersHandler {
	
	public function __construct() {
	}
	
	public function doCreateUser($user_reference_uuid, $user_provider_uuid, array $user_opts_array) {
		try {
			config::getLogger()->addInfo("bachat user creation...");
			if(isset($user_provider_uuid)) {
				//TODO
			} else {
				//
				if(!isset($user_opts_array['email'])) {
					$msg = "userOpts field 'email' was not provided";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if(!isset($user_opts_array['firstName'])) {
					$msg = "userOpts field 'firstName' was not provided";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if(!isset($user_opts_array['lastName'])) {
					$msg = "userOpts field 'lastName' was not provided";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				//TODO
			}
			//TODO : $user_provider_uuid = 
			config::getLogger()->addInfo("bachat user creation done successfully, user_provider_uuid=".$user_provider_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a bachat user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("bachat user creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a bachat user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("bachat user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($user_provider_uuid);
	}
}

?>