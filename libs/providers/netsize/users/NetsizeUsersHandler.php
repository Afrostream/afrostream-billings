<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class NetsizeUsersHandler {
	
	public function __construct() {
	}
	
	public function doCreateUser($user_reference_uuid, $user_billing_uuid, $user_provider_uuid, array $user_opts_array) {
		try {
			config::getLogger()->addInfo("netsize user creation...");
			if(isset($user_provider_uuid)) {
				//TODO : transactionId may be in $user_opts_array, maybe should we check it later
				//REMOVE CHECK
				/*$netsizeClient = new NetsizeClient();
				$getStatusRequest = new GetStatusRequest();
				$getStatusRequest->setTransactionId($user_provider_uuid);
				$getStatusResponse = $netsizeClient->getStatus($getStatusRequest);
				//1 - A real MSISDN
				//2 - An encrypted MSISDN
				//4 - IMSI
				$array_userIdType_ok = [1, 2, 4];
				if(!in_array($getStatusResponse->getUserIdType(), $array_userIdType_ok)) {
					$msg = "user-id-type ".$getStatusResponse->getUserIdType()." is not correct";
					config::getLogger()->addError("netsize user creation failed : ".$msg);
					throw new BillingsException(new ExceptionType(ExceptionType::provider), $msg);
				}*/
			} else {
				$msg = "unsupported feature for provider named netsize, userProviderUuid has to be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			config::getLogger()->addInfo("netsize user creation done successfully, user_provider_uuid=".$user_provider_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a netsize user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("netsize user creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a netsize user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("netsize user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($user_provider_uuid);
	}
	
}

?>