<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../utils/utils.php';
require_once __DIR__ . '/../providers/global/ProviderHandlersBuilder.php';
require_once __DIR__ . '/../providers/global/requests/GetUserRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetUsersRequest.php';
require_once __DIR__ . '/../providers/global/requests/CreateUserRequest.php';
require_once __DIR__ . '/../providers/global/requests/UpdateUserRequest.php';
require_once __DIR__ . '/../providers/global/requests/UpdateUsersRequest.php';

class UsersHandler {
	
	public function __construct() {
	}
	
	public function doGetUser(GetUserRequest $getUserRequest) {
		if($getUserRequest->getUserBillingUuid() != NULL) {
			return($this->doGetUserByUserBillingUuid($getUserRequest->getUserBillingUuid()));
		} else {
			return($this->doGetUserByUserReferenceUuid($getUserRequest->getProviderName(), $getUserRequest->getUserReferenceUuid()));
		}
	}
	
	protected function doGetUserByUserBillingUuid($userBillingUuid) {
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
	
	protected function doGetUserByUserReferenceUuid($providerName, $userReferenceUuid) {
		$db_user = NULL;
		try {
			config::getLogger()->addInfo("user getting...");
			$provider = ProviderDAO::getProviderByName($providerName);
			
			if($provider == NULL) {
				$msg = "unknown provider named : ".$providerName;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			$db_users = UserDAO::getUsersByUserReferenceUuid($userReferenceUuid, $provider->getId());
			$count_users = count($db_users);
			if($count_users == 1) {
				$db_user = $db_users[0];
			} else if($count_users > 1) {
				$msg = "multiple users with userReferenceUuid=".$userReferenceUuid." exist for provider : ".$provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			config::getLogger()->addInfo("user getting done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting an user for userReferenceUuid=".$userReferenceUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an user for userReferenceUuid=".$userReferenceUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_user);
	}
	
	public function doGetUsers(GetUsersRequest $getUsersRequest) {
		$db_users = NULL;
		try {
			config::getLogger()->addInfo("users getting for userReferenceUuid=".$getUsersRequest->getUserReferenceUuid()."...");
			$db_users = UserDAO::getUsersByUserReferenceUuid($getUsersRequest->getUserReferenceUuid());
			config::getLogger()->addInfo("users getting for userReferenceUuid=".$getUsersRequest->getUserReferenceUuid()." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting users for userReferenceUuid=".$getUsersRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting users for userReferenceUuid=".$getUsersRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_users);		
	}
	
	public function doGetOrCreateUser(CreateUserRequest $createUserRequest) {
		$db_user = NULL;
		try {
			config::getLogger()->addInfo("user getting/creating...");
			checkUserOptsArray($createUserRequest->getUserOpts(), $createUserRequest->getProviderName());
			$provider = ProviderDAO::getProviderByName($createUserRequest->getProviderName());
				
			if($provider == NULL) {
				$msg = "unknown provider named : ".$createUserRequest->getProviderName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			if($createUserRequest->getUserReferenceUuid() == 'generate') {
				$createUserRequest->setUserReferenceUuid('generated_'.guid());
			}
			//as usual
			$db_tmp_user = NULL;
			$db_users = UserDAO::getUsersByUserReferenceUuid($createUserRequest->getUserReferenceUuid(), $provider->getId());
			$count_users = count($db_users);
			if($count_users == 1) {
				$db_tmp_user = $db_users[0];
			} else if($count_users > 1) {
				$msg = "multiple users with userReferenceUuid=".$createUserRequest->getUserReferenceUuid()." exist for provider : ".$provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($createUserRequest->getUserProviderUuid() == NULL) {
				$db_user = $db_tmp_user;
			} else {
				//HACK STAGING NETSIZE
				if(getEnv('BILLINGS_ENV') == 'staging') {
					if($provider->getName() == 'netsize') {
						$createUserRequest->setUserProviderUuid($createUserRequest->getUserProviderUuid().'_'.guid());
					}
				}
				//check
				if($db_tmp_user == NULL) {
					//nothing to do
				} else {
					if($db_tmp_user->getUserProviderUuid() == $createUserRequest->getUserProviderUuid()) {
						$db_user = $db_tmp_user;
					} else {
						$msg = "another user with userReferenceUuid=".$createUserRequest->getUserReferenceUuid()." exist for provider : ".$provider->getName();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
				}
				//
				if($db_user == NULL) {
					//check : Does this user_provider_uuid already exist in the Database ?
					$db_tmp_user = UserDAO::getUserByUserProviderUuid($provider->getId(), $createUserRequest->getUserProviderUuid());
					if($db_tmp_user == NULL) {
						//nothing to do
					} else {
						if($db_tmp_user->getUserReferenceUuid() != $createUserRequest->getUserReferenceUuid()) {
							//Exception
							$msg = "userProviderUuid=".$createUserRequest->getUserProviderUuid()." is already linked to another userReferenceUuid";
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						}
						//
						//done
						$db_user = $db_tmp_user;
					}
				}
			}
			if($db_user == NULL) {
				$db_user = $this->doCreateUser($createUserRequest);
			} else {
				//update user_opts
				try {
					//START TRANSACTION
					pg_query("BEGIN");
					//USER_OPTS
					//DELETE USER_OPTS
					UserOptsDAO::deleteUserOptsByUserId($db_user->getId());
					//RECREATE USER_OPTS
					$userOpts = new UserOpts();
					$userOpts->setUserId($db_user->getId());
					$userOpts->setOpts($createUserRequest->getUserOpts());
					$userOpts = UserOptsDAO::addUserOpts($userOpts);
					//COMMIT
					pg_query("COMMIT");
				} catch(Exception $e) {
					pg_query("ROLLBACK");
					throw $e;
				}
			}
			config::getLogger()->addInfo("user getting/creating done successfully, userid=".$db_user->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting/creating an user for userReferenceUuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting/creating an user for userReferenceUuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_user);
	}
	
	protected function doCreateUser(CreateUserRequest $createUserRequest) {
		$db_user = NULL;
		try {
			config::getLogger()->addInfo("user creating...");
			checkUserOptsArray($createUserRequest->getUserOpts(), $createUserRequest->getProviderName());
			$provider = ProviderDAO::getProviderByName($createUserRequest->getProviderName());
			
			if($provider == NULL) {
				$msg = "unknown provider named : ".$createUserRequest->getProviderName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//user creation provider side
			$createUserRequest->setUserBillingUuid(guid());
			$providerUsersHandler = ProviderHandlersBuilder::getProviderUsersHandlerInstance($provider);
			$userProviderUuid = $providerUsersHandler->doCreateUser($createUserRequest);
			//user created provider side, save it in billings database
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				//USER
				$db_user = new User();
				$db_user->setUserBillingUuid($createUserRequest->getUserBillingUuid());
				$db_user->setProviderId($provider->getId());
				$db_user->setUserReferenceUuid($createUserRequest->getUserReferenceUuid());
				$db_user->setUserProviderUuid($userProviderUuid);
				$db_user = UserDAO::addUser($db_user);
				//USER_OPTS
				$userOpts = new UserOpts();
				$userOpts->setUserId($db_user->getId());
				$userOpts->setOpts($createUserRequest->getUserOpts());
				$userOpts = UserOptsDAO::addUserOpts($userOpts);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			config::getLogger()->addInfo("user creating done successfully, userid=".$db_user->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating an user for userReferenceUuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating an user for userReferenceUuid=".$createUserRequest->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user creating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_user);
	}
	
	public function doUpdateUserOpts(UpdateUserRequest $updateUserRequest) {
		$db_user = NULL;
		try {
			config::getLogger()->addInfo("userOpts updating...");
			$db_user = UserDAO::getUserByUserBillingUuid($updateUserRequest->getUserBillingUuid());
			if($db_user == NULL) {
				$msg = "unknown userBillingUuid : ".$updateUserRequest->getUserBillingUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$provider = ProviderDAO::getProviderById($db_user->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider id : ".$user->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			checkUserOptsValues($updateUserRequest->getUserOpts(), $provider->getName());
			$db_user_opts = UserOptsDAO::getUserOptsByUserId($db_user->getId());
			$current_user_opts_array = $db_user_opts->getOpts();
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				foreach ($updateUserRequest->getUserOpts() as $key => $value) {
					if(array_key_exists($key, $current_user_opts_array)) {
						//UPDATE OR DELETE
						if(isset($value)) {
							UserOptsDAO::updateUserOptsKey($db_user->getId(), $key, $value);
						} else {
							UserOptsDAO::deleteUserOptsKey($db_user->getId(), $key);
						}
					} else {
						//ADD
						UserOptsDAO::addUserOptsKey($db_user->getId(), $key, $value);
					}
				}
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			//done in db
			$db_user_opts = UserOptsDAO::getUserOptsByUserId($db_user->getId());
			$db_user = UserDAO::getUserById($db_user->getId());
			//user creation provider side
			$providerUsersHandler = ProviderHandlersBuilder::getProviderUsersHandlerInstance($provider);
			$updateUserRequest->setUserProviderUuid($db_user->getUserProviderUuid());
			$updateUserRequest->setUserOpts($db_user_opts->getOpts());
			$providerUsersHandler->doUpdateUserOpts($updateUserRequest);		
			config::getLogger()->addInfo("userOpts updating done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating userOpts, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating userOpts failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating userOpts, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating userOpts failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_user);
	}
	
}

?>