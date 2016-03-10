<?php

use GoCardlessPro\Client;
use GoCardlessPro\Core\Exception\GoCardlessProException;

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class GocardlessUsersHandler {
	
	public function __construct() {
	}
	
	public function doCreateUser($user_reference_uuid, $user_provider_uuid, array $user_opts_array) {
		try {
			config::getLogger()->addInfo("gocardless user creation...");
			if(isset($user_provider_uuid)) {
				//
				$client = new Client(array(
						'access_token' => getEnv('GOCARDLESS_API_KEY'),
						'environment' => getEnv('GOCARDLESS_API_ENV')
				));
				//
				$customer = $client->customers()->get($user_provider_uuid);
			} else {
				//
				$client = new Client(array(
					'access_token' => getEnv('GOCARDLESS_API_KEY'),
					'environment' => getEnv('GOCARDLESS_API_ENV')
				));
				//
				checkUserOptsArray($user_opts_array);
				//
				$customer = $client->customers()->create(
						['params' => 
								[
								'email' => $user_opts_array['email'],
								'given_name' => $user_opts_array['firstName'], 
								'family_name' => $user_opts_array['lastName']
								]
						]);
			}
			$user_provider_uuid = $customer->id;
			config::getLogger()->addInfo("gocardless user creation done successfully, user_provider_uuid=".$user_provider_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a gocardless user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless user creation failed : ".$msg);
			throw $e;
		} catch (GoCardlessProException $e) {
			$msg = "a GoCardlessProException occurred while creating a gocardless user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a gocardless user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($user_provider_uuid);
	}
	
	public function doUpdateUserOpts($user_provider_uuid, array $user_opts_array) {
		try {
			config::getLogger()->addInfo("gocardless user data updating...");
			//
			checkUserOptsArray($user_opts_array);
			//
			$client = new Client(array(
					'access_token' => getEnv('GOCARDLESS_API_KEY'),
					'environment' => getEnv('GOCARDLESS_API_ENV')
			));
			//
			$client->customers()->update($user_provider_uuid,
					['params' =>
							[
									'email' => $user_opts_array['email'],
									'given_name' => $user_opts_array['firstName'],
									'family_name' => $user_opts_array['lastName']
							]
					]);
			config::getLogger()->addInfo("gocardless user data updating done successfully, user_provider_uuid=".$user_provider_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating gocardless user data for user_provider_uuid=".$user_provider_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless user data updating failed : ".$msg);
			throw $e;
		} catch (GoCardlessProException $e) {
			$msg = "a GoCardlessProException occurred while updating gocardless user data for user_provider_uuid=".$user_provider_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless user data updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating gocardless user data for user_provider_uuid=".$user_provider_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("gocardless user data updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
	}
	
}

?>