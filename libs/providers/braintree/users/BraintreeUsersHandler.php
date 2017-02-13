<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/users/ProviderUsersHandler.php';
require_once __DIR__ . '/../../global/requests/CreateUserRequest.php';
require_once __DIR__ . '/../../global/requests/UpdateUserRequest.php';
require_once __DIR__ . '/../../global/requests/UpdateUsersRequest.php';

class BraintreeUsersHandler extends ProviderUsersHandler {
	
	public function doCreateUser(CreateUserRequest $createUserRequest) {
		try {
			config::getLogger()->addInfo("braintree user creation...");
			$account = NULL;
			if($createUserRequest->getUserProviderUuid() != NULL) {
				//
				Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
				Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
				Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
				Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
				//
				$account = Braintree\Customer::find($createUserRequest->getUserProviderUuid());
				//
			} else {
				//
				checkUserOptsArray($createUserRequest->getUserOpts(), $this->provider->getName());
				//
				Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
				Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
				Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
				Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
				//
				$attribs = array();
				$attribs['email'] = $createUserRequest->getUserOpts()['email'];
				$attribs['firstName'] = $createUserRequest->getUserOpts()['firstName'];
				$attribs['lastName'] = $createUserRequest->getUserOpts()['lastName'];
				//
				$result = Braintree\Customer::create($attribs);
				if ($result->success) {
					$account = $result->customer;	
				} else {
					$msg = 'a braintree api error occurred : ';
					$errorString = $result->message;
					foreach($result->errors->deepAll() as $error) {
						$errorString.= '; Code=' . $error->code . ", msg=" . $error->message;
    				}
    				throw new Exception($msg.$errorString);			
				}
			}
			$user_provider_uuid = $account->id;
			config::getLogger()->addInfo("braintree user creation done successfully, user_provider_uuid=".$user_provider_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a braintree user for user_reference_uuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree user creation failed : ".$msg);
			throw $e;
		} catch(Braintree\Exception\NotFound $e) {
			$msg = "a not found error exception occurred while creating a braintree user for user_reference_uuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);	
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a braintree user for user_reference_uuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($user_provider_uuid);
	}
	
	public function doUpdateUserOpts(UpdateUserRequest $updateUserRequest) {
		try {
			config::getLogger()->addInfo("braintree user data updating...");
			//
			checkUserOptsArray($updateUserRequest->getUserOpts(), $this->provider->getName());
			//
			Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
			Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
			Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
			Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
			//
			$attribs = array();
			$attribs['email'] = $updateUserRequest->getUserOpts()['email'];
			$attribs['firstName'] = $updateUserRequest->getUserOpts()['firstName'];
			$attribs['lastName'] = $updateUserRequest->getUserOpts()['lastName'];
			//
			$result = Braintree\Customer::update($updateUserRequest->getUserProviderUuid(), $attribs);
			if (!$result->success) {
				$msg = 'a braintree api error occurred : ';
				$errorString = $result->message;
				foreach($result->errors->deepAll() as $error) {
					$errorString.= '; Code=' . $error->code . ", msg=" . $error->message;
				}
				throw new Exception($msg.$errorString);
			}			
			config::getLogger()->addInfo("braintree user data updating done successfully, user_provider_uuid=".$updateUserRequest->getUserProviderUuid());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating braintree user data for user_provider_uuid=".$updateUserRequest->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree user data updating failed : ".$msg);
			throw $e;
		} catch(Braintree\Exception\NotFound $e) {
			$msg = "a not found error exception occurred while updating braintree user data for user_provider_uuid=".$updateUserRequest->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating a braintree user data for user_provider_uuid=".$updateUserRequest->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree user data updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}		
	}
	
}

?>