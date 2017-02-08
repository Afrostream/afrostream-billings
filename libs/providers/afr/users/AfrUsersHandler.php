<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/users/ProviderUsersHandler.php';
require_once __DIR__ . '/../../global/requests/CreateUserRequest.php';

class AfrUsersHandler extends ProviderUsersHandler {
	
	public function doCreateUser(CreateUserRequest $createUserRequest) {
		try {
			config::getLogger()->addInfo("afr user creation...");
			if($createUserRequest->getUserProviderUuid() != NULL) {
				$msg = "unsupported feature for provider named afr, userProviderUuid has NOT to be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$user_provider_uuid = guid();
			config::getLogger()->addInfo("afr user creation done successfully, user_provider_uuid=".$user_provider_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a afr user for user_reference_uuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr user creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a afr user for user_reference_uuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($user_provider_uuid);
	}
	
}

?>