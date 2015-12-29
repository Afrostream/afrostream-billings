<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/providers/celery/users/CeleryUsersHandler.php';
require_once __DIR__ . '/../../libs/providers/recurly/users/RecurlyUsersHandler.php';
require_once __DIR__ . '/../../libs/providers/gocardless/users/GocardlessUsersHandler.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/utils/utils.php';

class UsersHandler {
	
	public function __construct() {
	}
	
	public function doGetUserByUserBillingUuid($userBillingUuid) {
		$db_user = NULL;
		try {
			config::getLogger()->addInfo("user getting, userBillingUuid=".$userBillingUuid."....");
			//
			$db_user = UserDAO::getUserByUserBillingUuid($userBillingUuid);
			//
			config::getLogger()->addInfo("user getting, userBillingUuid=".$userBillingUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting an user for userBillingUuid=".$userBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an user for userBillingUuid=".$userBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_user);
	}
	
	public function doGetUser($provider_name, $user_reference_uuid) {
		$db_user = NULL;
		try {
			config::getLogger()->addInfo("user getting...");
			$provider = ProviderDAO::getProviderByName($provider_name);
			
			if($provider == NULL) {
				$msg = "unknown provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			$db_users = UserDAO::getUsersByUserReferenceUuid($user_reference_uuid, $provider->getId());
			$count_users = count($db_users);
			if($count_users == 1) {
				$db_user = $db_users[0];
			} else if($count_users > 1) {
				$msg = "multiple users with userReferenceUuid=".$user_reference_uuid." exist for provider : ".$provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			config::getLogger()->addInfo("user getting done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting an user for userReferenceUuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an user for userReferenceUuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_user);
	}
	
	public function doGetUsers($user_reference_uuid) {
		$db_users = NULL;
		try {
			config::getLogger()->addInfo("users getting for userReferenceUuid=".$user_reference_uuid."...");
			$db_users = UserDAO::getUsersByUserReferenceUuid($user_reference_uuid);
			config::getLogger()->addInfo("users getting for userReferenceUuid=".$user_reference_uuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting users for userReferenceUuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting users for userReferenceUuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_users);		
	}
	
	public function doGetOrCreateUser($provider_name, $user_reference_uuid, $user_provider_uuid, array $user_opts_array) {
		$db_user = NULL;
		try {
			config::getLogger()->addInfo("user getting/creating...");
			$this->checkUserOptsArray($user_opts_array);
			$provider = ProviderDAO::getProviderByName($provider_name);
				
			if($provider == NULL) {
				$msg = "unknown provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($user_provider_uuid == NULL) {
				//as usual
				$db_users = UserDAO::getUsersByUserReferenceUuid($user_reference_uuid, $provider->getId());
				$count_users = count($db_users);
				if($count_users == 1) {
					$db_user = $db_users[0];
				} else if($count_users > 1) {
					$msg = "multiple users with userReferenceUuid=".$user_reference_uuid." exist for provider : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
			} else {
				//check : Does this user_provider_uuid already exist in the Database ?
				$db_tmp_user = UserDAO::getUserByUserProviderUuid($provider->getId(), $user_provider_uuid);
				if($db_tmp_user == NULL) {
					//nothing to do
				} else {
					if($db_tmp_user->getUserReferenceUuid() != $user_reference_uuid) {
						//Exception
						$msg = "userProviderUuid=".$user_provider_uuid." is already linked to another userReferenceUuid";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					//
					//done
					$db_user = $db_tmp_user;
				}
			}
			if($db_user == NULL) {
				$db_user = $this->doCreateUser($provider_name, $user_reference_uuid, $user_provider_uuid, $user_opts_array);
			} else {
				//update user_opts
				//START TRANSACTION
				pg_query("BEGIN");
				//USER_OPTS
				//DELETE USER_OPTS
				UserOptsDAO::deleteUserOptsByUserId($db_user->getId());
				//RECREATE USER_OPTS
				$user_opts = new UserOpts();
				$user_opts->setUserId($db_user->getId());
				$user_opts->setOpts($user_opts_array);
				$user_opts = UserOptsDAO::addUserOpts($user_opts);
				pg_query("COMMIT");
			}
			config::getLogger()->addInfo("user getting/creating done successfully, userid=".$db_user->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting/creating an user for userReferenceUuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting/creating an user for userReferenceUuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_user);
	}
	
	private function doCreateUser($provider_name, $user_reference_uuid, $user_provider_uuid, array $user_opts_array) {
		$db_user = NULL;
		try {
			config::getLogger()->addInfo("user creating...");
			$this->checkUserOptsArray($user_opts_array);
			$provider = ProviderDAO::getProviderByName($provider_name);
			
			if($provider == NULL) {
				$msg = "unknown provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//user creation provider side
			switch($provider->getName()) {
				case 'recurly' :
					$recurlyUsersHandler = new RecurlyUsersHandler();
					$user_provider_uuid = $recurlyUsersHandler->doCreateUser($user_reference_uuid, $user_provider_uuid, $user_opts_array);
					break;
				case 'gocardless' :
					$gocardlessUsersHandler = new GocardlessUsersHandler();
					$user_provider_uuid = $gocardlessUsersHandler->doCreateUser($user_reference_uuid, $user_provider_uuid, $user_opts_array);
					break;
				case 'celery' :
					$celeryUsersHandler = new CeleryUsersHandler();
					$user_provider_uuid = $gocardlessUsersHandler->doCreateUser($user_reference_uuid, $user_provider_uuid, $user_opts_array);
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
			$db_user->setUserBillingUuid(guid());
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
			config::getLogger()->addInfo("user creating done successfully, userid=".$db_user->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating an user for userReferenceUuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating an user for userReferenceUuid=".$user_reference_uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user creating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_user);
	}
	
	private function checkUserOptsArray($user_opts_as_array) {
		if(!isset($user_opts_as_array['email'])) {
			//exception
			$msg = "userOpts field 'email' is missing";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if(!isset($user_opts_as_array['firstName'])) {
			//exception
			$msg = "userOpts field 'firstName' is missing";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if(!isset($user_opts_as_array['lastName'])) {
			//exception
			$msg = "userOpts field 'lastName' is missing";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
}

?>