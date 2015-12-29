<?php

class CeleryUsersHandler {

	public function __construct() {
	}
	
	public function doCreateUser($user_reference_uuid, $user_provider_uuid, array $user_opts_array) {
		try {
			config::getLogger()->addInfo("celery user creation...");
			if(isset($user_provider_uuid)) {
				//nothing
				//TODO : (should check provider side...)
			} else {
				$msg = "unsupported feature for provider named celery";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			config::getLogger()->addInfo("celery user creation done successfully, user_provider_uuid=".$user_provider_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a celery user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("celery user creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a celery user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("celery user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($user_provider_uuid);
	}
}

?>