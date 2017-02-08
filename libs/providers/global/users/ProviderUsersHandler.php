<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../requests/CreateUserRequest.php';

class ProviderUsersHandler {
	
	protected $provider = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
	}
	
	public function doCreateUser(CreateUserRequest $createUserRequest) {
		$msg = "unsupported feature for provider named ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
	}
	
	public function doUpdateUserOpts($user_provider_uuid, array $user_opts_array) {
		$msg = "unsupported feature for provider named ".$this->provider->getName();
		config::getLogger()->addWarning($msg);//Just warn for the moment
		//throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
	}
	
}

?>