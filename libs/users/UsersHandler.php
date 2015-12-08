<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/providers/recurly/users/RecurlyUsersHandler.php';
require_once __DIR__ . '/../../libs/providers/gocardless/users/GocardlessUsersHandler.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';

class UsersHandler {
	
	public function __construct() {
	}

	public function doCreateUser($provider_name, $user_reference_uuid, $user_opts_array) {
		$db_user = NULL;
		try {
			config::getLogger()->addInfo("user creation...");
			
			$provider = ProviderDAO::getProviderByName($provider_name);
			
			if($provider == NULL) {
				$msg = "unknown provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//user creation provider side
			$user_provider_uuid = NULL;
			switch($provider->getName()) {
				case 'recurly':
					$recurlyUsersHandler = new RecurlyUsersHandler();
					$user_provider_uuid = $recurlyUsersHandler->doCreateUser($user_reference_uuid, $user_opts_array);
					break;
				case 'gocardless' :
					$gocardlessUsersHandler = new GocardlessUsersHandler();
					$user_provider_uuid = $gocardlessUsersHandler->doCreateUser($user_reference_uuid, $user_opts_array);
					break;
				case 'celery' :
					$msg = "unsupported feature for provider named : ".$provider_name;
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
				default:
					$msg = "unsupported feature for provider named : ".$provider_name;
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			//user created provider side, save it in billings database
			//START TRANSACTION
			pg_query("BEGIN");
			//USER
			$db_user = new User();
			$db_user->setProviderId($provider->getId());
			$db_user->setUserReferenceUuid($user_reference_uuid);
			$db_user->setUserProviderUuid($user_provider_uuid);
			$db_user = UserDAO::addUser($db_user);
			//USER_OPTS
			$user_opts = new UserOpts();
			$user_opts->setUserId($db_user->getId());
			$user_opts->setOpts($user_opts_array);
			$user_opts = UserOptsDAO::addUserOpts($user_opts);
			//COMMIT
			pg_query("COMMIT");
			config::getLogger()->addInfo("user creation done successfully, userid=".$db_user->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating an user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a user for user_reference_uuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_user);
	}
}

?>