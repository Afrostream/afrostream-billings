<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../libs/recurly/db/dbRecurly.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/utils/utils.php';

class RecurlyUsersHandler {
	
	public function __construct() {
	}
	
	public function doCreateUser($user_reference_uuid, $user_opts) {
		//
		Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
		Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
		//
		$guid = NULL;
		try {
			$guid = guid();
			$account = new Recurly_Account($guid);
			if(isset($user_opts['email'])) {
				$account->email = $user_opts['email'];
			} else {
				//TODO
			}
			if(isset($user_opts['first_name'])) {
				$account->first_name = $user_opts['first_name'];
			} else {
				//TODO
			}
			if(isset($user_opts['last_name'])) {
				$account->last_name = $user_opts['last_name'];
			} else {
				//TODO
			}
			$account->create();
		} catch (Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while creating an account for user_reference_id=".$user_reference_id.", message=".$e->getMessage();
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating an account for user_reference_id=".$user_reference_id.", message=".$e->getMessage();
			config::getLogger()->addError($msg);
			throw new Exception($msg);
		}
		return($guid);
	}
}

?>