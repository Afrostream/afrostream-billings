<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/recurly/users/RecurlyUsersHandler.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';

class UsersHandler {
	
	public function __construct() {
	}

	public function doCreateUser($provider_name, $user_reference_uuid, $user_opts_array) {
		$provider = ProviderDAO::getProviderByName($provider_name);
		
		if($provider == NULL) {
			$msg = "unknown provider named : ".$provider_name;
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		$user_provider_uuid = NULL;
		switch($provider->getName()) {
			case 'recurly':
				$recurlyUsersHandler = new RecurlyUsersHandler();
				$user_provider_uuid = $recurlyUsersHandler->doCreateUser($user_reference_uuid, $user_opts_array);
				break;
			case 'celery' :
				$msg = "unsupported feature for provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new Exception($msg);
				break;
			default:
				$msg = "unsupported feature for provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new Exception($msg);
				break;
		}
		//user created recurly side, save it in billings database
		//START TRANSACTION
		pg_query("BEGIN");
		//USER
		$user = new User();
		$user->setProviderId($provider->getId());
		$user->setUserReferenceUuid($user_reference_uuid);
		$user->setUserProviderUuid($user_provider_uuid);
		$user = UserDAO::addUser($user);
		//USER_OPTS
		$user_opts = new UserOpts();
		$user_opts->setUserId($user->getId());
		$user_opts->setOpts($user_opts_array);
		$user_opts = UserOptsDAO::addUserOpts($user_opts);
		//COMMIT
		pg_query("COMMIT");
		return($user);
	}
}

?>