<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class UpdateUserRequest extends ActionRequest {
	
	protected $userBillingUuid;
	protected $userOpts;
	//
	protected $userProviderUuid;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setUserBillingUuid($userBillingUuid) {
		$this->userBillingUuid = $userBillingUuid;
	}
	
	public function getUserBillingUuid() {
		return($this->userBillingUuid);
	}
	
	public function setUserOpts(array $userOpts = NULL) {
		$this->userOpts = $userOpts;
	}
	
	public function getUserOpts() {
		return($this->userOpts);
	}
	
	public function setUserProviderUuid($userProviderUuid) {
		$this->userProviderUuid = $userProviderUuid;
	}
	
	public function getUserProviderUuid() {
		return($this->userProviderUuid);
	}
	
}

?>