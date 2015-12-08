<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';
require_once __DIR__ . '/../../../../libs/utils/BillingsException.php';

class RecurlyUsersHandler {
	
	public function __construct() {
	}
	
	public function doCreateUser($user_reference_uuid, UserOpts $user_opts) {
		$user_provider_uuid = NULL;
		try {
			config::getLogger()->addInfo("recurly user creation...");
			//
			Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
			Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
			//
			$account = new Recurly_Account(guid());
			if(!isset($user_opts->getOpts()['email'])) {
				$msg = "field 'email' was not provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!isset($user_opts->getOpts()['first_name'])) {
				$msg = "field 'first_name' was not provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!isset($user_opts->getOpts()['last_name'])) {
				$msg = "field 'last_name' was not provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			$account->email = $user_opts->getOpts()['email'];
			$account->first_name = $user_opts->getOpts()['first_name'];
			$account->last_name = $user_opts->getOpts()['last_name'];
			//
			$account->create();
			//
			$user_provider_uuid = $account->account_code;
			config::getLogger()->addInfo("recurly user creation done successfully, user_provider_uuid=".user_provider_uuid);
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
		return(user_provider_uuid);
	}
}

?>