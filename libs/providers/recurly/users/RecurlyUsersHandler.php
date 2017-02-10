<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/users/ProviderUsersHandler.php';
require_once __DIR__ . '/../../global/requests/CreateUserRequest.php';
require_once __DIR__ . '/../../global/requests/UpdateUserRequest.php';
require_once __DIR__ . '/../../global/requests/UpdateUsersRequest.php';

class RecurlyUsersHandler extends ProviderUsersHandler {
	
	public function doCreateUser(CreateUserRequest $createUserRequest) {
		try {
			config::getLogger()->addInfo($this->provider->getName()." user creation...");
			$account = NULL;
			if($createUserRequest->getUserProviderUuid() != NULL) {
				//
				Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
				Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
				//
				$account = Recurly_Account::get($createUserRequest->getUserProviderUuid());
			} else {
				//
				checkUserOptsArray($createUserRequest->getUserOpts(), $this->provider->getName());
				//
				Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
				Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
				//
				$account = new Recurly_Account(guid());
				$account->email = $createUserRequest->getUserOpts()['email'];
				$account->first_name = $createUserRequest->getUserOpts()['firstName'];
				$account->last_name = $createUserRequest->getUserOpts()['lastName'];
				//
				$account->create();
				//
			}
			$user_provider_uuid = $account->account_code;
			config::getLogger()->addInfo($this->provider->getName()." user creation done successfully, user_provider_uuid=".$user_provider_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a ".$this->provider->getName()." user for user_reference_uuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." user creation failed : ".$msg);
			throw $e;
		} catch(Recurly_NotFoundError $e) {
			$msg = "a not found error exception occurred while creating a ".$this->provider->getName()." user for user_reference_uuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);	
		} catch (Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while creating a ".$this->provider->getName()." user for user_reference_uuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a ".$this->provider->getName()." user for user_reference_uuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($user_provider_uuid);
	}
	
	public function doUpdateUserOpts(UpdateUserRequest $updateUserRequest) {
		try {
			config::getLogger()->addInfo("recurly user data updating...");
			//
			checkUserOptsArray($updateUserRequest->getUserOpts(), $this->provider->getName());
			//
			Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
			Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
			//
			$account = Recurly_Account::get($updateUserRequest->getUserProviderUuid());
			$account->email = $updateUserRequest->getUserOpts()['email'];
			$account->first_name = $updateUserRequest->getUserOpts()['firstName'];
			$account->last_name = $updateUserRequest->getUserOpts()['lastName'];
			//
			$account->update();
			config::getLogger()->addInfo("recurly user data updating done successfully, user_provider_uuid=".$updateUserRequest->getUserProviderUuid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating recurly user data for user_provider_uuid=".$updateUserRequest->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly user data updating failed : ".$msg);
			throw $e;
		} catch(Recurly_NotFoundError $e) {
			$msg = "a not found error exception occurred while updating a recurly user data for user_provider_uuid=".$updateUserRequest->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly user data updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while updating a recurly user data for user_provider_uuid=".$updateUserRequest->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly user data updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating a recurly user data for user_provider_uuid=".$updateUserRequest->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly user data updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}			
	}
	
}

?>