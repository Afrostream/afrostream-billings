<?php

use GoCardlessPro\Client;
use GoCardlessPro\Core\Exception\GoCardlessProException;

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/users/ProviderUsersHandler.php';

class GocardlessUsersHandler extends ProviderUsersHandler {
	
	public function doCreateUser(CreateUserRequest $createUserRequest) {
		try {
			config::getLogger()->addInfo($this->provider->getName()." user creation...");
			$customer = NULL;
			if($createUserRequest->getUserProviderUuid() != NULL) {
				//
				$client = new Client(array(
						'access_token' => $this->provider->getApiSecret(),
						'environment' => getEnv('GOCARDLESS_API_ENV')
				));
				//
				$customer = $client->customers()->get($createUserRequest->getUserProviderUuid());
			} else {
				//
				$client = new Client(array(
					'access_token' => $this->provider->getApiSecret(),
					'environment' => getEnv('GOCARDLESS_API_ENV')
				));
				//
				checkUserOptsArray($createUserRequest->getUserOptsArray(), $this->provider->getName(), 'create');
				//
				$customer = $client->customers()->create(
						['params' => 
								[
								'email' => $createUserRequest->getUserOptsArray()['email'],
								'given_name' => $createUserRequest->getUserOptsArray()['firstName'], 
								'family_name' => $createUserRequest->getUserOptsArray()['lastName']
								]
						]);
			}
			$user_provider_uuid = $customer->id;
			config::getLogger()->addInfo($this->provider->getName()." user creation done successfully, user_provider_uuid=".$user_provider_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a ".$this->provider->getName()." user for user_reference_uuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($this->provider->getName()." user creation failed : ".$msg);
			throw $e;
		} catch (GoCardlessProException $e) {
			$msg = "a GoCardlessProException occurred while creating a ".$this->provider->getName()." user for user_reference_uuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
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
			config::getLogger()->addInfo("gocardless user data updating...");
			//
			checkUserOptsArray($updateUserRequest->getUserOptsArray(), $this->provider->getName());
			//
			$client = new Client(array(
					'access_token' => $this->provider->getApiSecret(),
					'environment' => getEnv('GOCARDLESS_API_ENV')
			));
			//
			$params = array();
			if(array_key_exists('email', $updateUserRequest->getUserOptsArray())) {
				$params['email'] = $updateUserRequest->getUserOptsArray()['email'];
			}
			if(array_key_exists('firstName', $updateUserRequest->getUserOptsArray())) {
				$params['given_name'] = $updateUserRequest->getUserOptsArray()['firstName'];
			}
			if(array_key_exists('lastName', $updateUserRequest->getUserOptsArray())) {
				$params['family_name'] = $updateUserRequest->getUserOptsArray()['lastName'];
			}
			//
			if(count($params) > 0) {
				$client->customers()->update($updateUserRequest->getUserProviderUuid(), ['params' => $params]);
			}
			config::getLogger()->addInfo("gocardless user data updating done successfully, user_provider_uuid=".$updateUserRequest->getUserProviderUuid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating gocardless user data for user_provider_uuid=".$updateUserRequest->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless user data updating failed : ".$msg);
			throw $e;
		} catch (GoCardlessProException $e) {
			$msg = "a GoCardlessProException occurred while updating gocardless user data for user_provider_uuid=".$updateUserRequest->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless user data updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating gocardless user data for user_provider_uuid=".$updateUserRequest->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless user data updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
	}
	
}

?>