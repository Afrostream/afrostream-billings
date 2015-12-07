<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../libs/recurly/db/dbRecurly.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/utils/utils.php';
require_once __DIR__ . '/../../../libs/utils/BillingsException.php';

class RecurlyUsersHandler {
	
	public function __construct() {
	}
	
	public function doCreateUser($user_reference_uuid, UserOpts $user_opts) {
		$account = NULL;
		try {
			config::getLogger()->addInfo("recurly user creation...");
			//
			Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
			Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
			//
			$account = new Recurly_Account(guid());
			if(isset($user_opts['email'])) {
				$account->email = $user_opts['email'];
			} else {
				$msg = "field 'email' was not provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(isset($user_opts['first_name'])) {
				$account->first_name = $user_opts['first_name'];
			} else {
				$msg = "field 'first_name' was not provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(isset($user_opts['last_name'])) {
				$account->last_name = $user_opts['last_name'];
			} else {
				$msg = "field 'last_name' was not provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$account->create();
			config::getLogger()->addInfo("recurly user creation done successfully, user_provider_uuid=".$account->account_code);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a recurly user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly user creation failed : ".$msg);
			throw $e;
		} catch (Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while creating a recurly user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a recurly user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($account);
	}
}

?>