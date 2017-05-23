<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/users/ProviderUsersHandler.php';

class RecurlyUsersHandler extends ProviderUsersHandler {
	
	public function doCreateUser(CreateUserRequest $createUserRequest) {
		try {
			config::getLogger()->addInfo($this->provider->getName()." user creation...");
			$account = NULL;
			if($createUserRequest->getUserProviderUuid() != NULL) {
				//
				Recurly_Client::$subdomain = $this->provider->getMerchantId();
				Recurly_Client::$apiKey = $this->provider->getApiSecret();
				//
				$account = Recurly_Account::get($createUserRequest->getUserProviderUuid());
			} else {
				//
				checkUserOptsArray($createUserRequest->getUserOptsArray(), $this->provider->getName(), 'create');
				//
				Recurly_Client::$subdomain = $this->provider->getMerchantId();
				Recurly_Client::$apiKey = $this->provider->getApiSecret();
				//
				$account = new Recurly_Account(guid());
				$account->email = $createUserRequest->getUserOptsArray()['email'];
				$account->first_name = $createUserRequest->getUserOptsArray()['firstName'];
				$account->last_name = $createUserRequest->getUserOptsArray()['lastName'];
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
			checkUserOptsArray($updateUserRequest->getUserOptsArray(), $this->provider->getName());
			//
			Recurly_Client::$subdomain = $this->provider->getMerchantId();
			Recurly_Client::$apiKey = $this->provider->getApiSecret();
			//
			$account = Recurly_Account::get($updateUserRequest->getUserProviderUuid());
			//
			$hasToBeUpdated = false;
			//
			if(array_key_exists('email', $updateUserRequest->getUserOptsArray())) {
				config::getLogger()->addInfo("recurly user data updating 'email'");
				$account->email = $updateUserRequest->getUserOptsArray()['email'];
				$hasToBeUpdated = true;
			}
			if(array_key_exists('firstName', $updateUserRequest->getUserOptsArray())) {
				config::getLogger()->addInfo("recurly user data updating 'firstName'");
				$account->first_name = $updateUserRequest->getUserOptsArray()['firstName'];
				$hasToBeUpdated = true;
			}
			if(array_key_exists('lastName', $updateUserRequest->getUserOptsArray())) {
				config::getLogger()->addInfo("recurly user data updating 'lastName'");
				$account->last_name = $updateUserRequest->getUserOptsArray()['lastName'];
				$hasToBeUpdated = true;
			}
			//
			if($hasToBeUpdated) {
				$account->update();
			}
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