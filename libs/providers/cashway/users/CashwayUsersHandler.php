<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/users/ProviderUsersHandler.php';

class CashwayUsersHandler extends ProviderUsersHandler {
	
	public function doCreateUser($user_reference_uuid, $user_billing_uuid, $user_provider_uuid, array $user_opts_array) {
		try {
			config::getLogger()->addInfo("cashway user creation...");
			if(isset($user_provider_uuid)) {
				$msg = "unsupported feature for provider named cashway, userProviderUuid has NOT to be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$user_provider_uuid = guid();
			config::getLogger()->addInfo("cashway user creation done successfully, user_provider_uuid=".$user_provider_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a cashway user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway user creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a cashway user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($user_provider_uuid);
	}
	
}

?>