<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/users/ProviderUsersHandler.php';
require_once __DIR__ . '/../../global/requests/CreateUserRequest.php';

class OrangeUsersHandler extends ProviderUsersHandler {
	
	public function doCreateUser(CreateUserRequest $createUserRequest) {
		try {
			config::getLogger()->addInfo($this->provider->getName()." user creation...");
			if($createUserRequest->getUserProviderUuid() != NULL) {
				//TODO : (should check provider side...)
			} else {
				$msg = "unsupported feature for provider named ".$this->provider->getName().", userProviderUuid has to be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			config::getLogger()->addInfo($this->provider->getName()." user creation done successfully, user_provider_uuid=".$createUserRequest->getUserProviderUuid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a ".$this->provider->getName()." user for user_reference_uuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." user creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a ".$this->provider->getName()." user for user_reference_uuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($createUserRequest->getUserProviderUuid());
	}
	
}

?>