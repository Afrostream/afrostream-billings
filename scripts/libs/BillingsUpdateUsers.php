<?php

require_once __DIR__ . '/../../client/BillingsApiClient.php';
require_once __DIR__ . '/../../client/BillingsApiUsers.php';
require_once __DIR__ . '/../../client/BillingsApiSubscriptions.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/utils/utils.php';
require_once __DIR__ . '/../db/dbGlobal.php';

class BillingsUpdateUsers {
	
	private $billingsApiClient = NULL;
	
	public function __construct() {
		$this->billingsApiClient = new BillingsApiClient();
	}
	
	public function doUpdateUsers($firstId = NULL, $limit = 100, $offset = 0) {
		ScriptsConfig::getLogger()->addInfo("updating users data...");
		try {		
			$last_id = NULL;
			$todo_count = 0;
			$done_count = 0;
			$error_count = 0;
			while(count($billingsUsers = UserDAO::getUsers($firstId, $limit, $offset)) > 0) {
				$offset = $offset + $limit;
				//
				foreach ($billingsUsers as $billingsUser) {
					$last_id = $billingsUser->getId();
					try {
						$todo_count++;
						$this->doUpdateUser($billingsUser);
						$done_count++;
					} catch(Exception $e) {
						$error_count++;
						ScriptsConfig::getLogger()->addError("an error occurred while updating an user data, message=".$e->getMessage());
					}
				}
			}
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("unexpected exception while updating users data, message=".$e->getMessage());
		}
		ScriptsConfig::getLogger()->addInfo("updating users data done, last_id=".$last_id.", last offset=".$offset);
	}
	
	public function doUpdateUser(User $user) {
		try {
			ScriptsConfig::getLogger()->addInfo("updating user data for user which id=".$user->getId()."...");
			$afrUser = AfrUserDAO::getAfrUserById($user->getUserReferenceUuid());
			if($afrUser == NULL) {
				throw new Exception("user with userReferenceUuid=".$user->getUserReferenceUuid()." does not exist in afr database");
			}
			$userOpts = array();
			$firstName = $afrUser->getFirstName();
			if(isset($firstName) && strlen(trim($firstName)) && strpos($firstName, '@') === false) {
				$userOpts['firstName'] = $firstName;
			}
			$lastName = $afrUser->getLastName();
			if(isset($lastName) && strlen(trim($lastName)) && strpos($lastName, '@') === false) {
				$userOpts['lastName'] = $lastName;
			}			
			$email = $afrUser->getEmail();
			if(isset($email) && strlen(trim($email))) {
				$userOpts['email'] = $email;
			}
			if(count($userOpts) == 0) {
				ScriptsConfig::getLogger()->addInfo("updating user data for user which id=".$user->getId()." nothing to do");
			} else {
				ScriptsConfig::getLogger()->addInfo("updating user data for user which id=".$user->getId()." calling api...");
				$apiUpdateUserRequest = new ApiUpdateUserRequest();
				$apiUpdateUserRequest->setUserBillingUuid($user->getUserBillingUuid());
				foreach ($userOpts as $key => $value) {
					ScriptsConfig::getLogger()->addInfo("updating user data for user which id=".$user->getId()." ".$key."=".$value."...");
					$apiUpdateUserRequest->setUserOpts($key, $value);
				}
				$this->billingsApiClient->getBillingsApiUsers()->updateUser($apiUpdateUserRequest);
				ScriptsConfig::getLogger()->addInfo("updating user data for user which id=".$user->getId()." calling api done successfully");
			}
			ScriptsConfig::getLogger()->addInfo("updating user data for user which id=".$user->getId()." done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("unexpected exception while updating user data for user which id=".$user->getId().", message=".$e->getMessage());
			throw $e;
		}
	}
	
}