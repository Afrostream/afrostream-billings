<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class UpdateUserRequest extends ActionRequest {
	
	protected $userBillingUuid = NULL;
	protected $userOptsArray = array();
	//
	protected $userProviderUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setUserBillingUuid($userBillingUuid) {
		$this->userBillingUuid = $userBillingUuid;
	}
	
	public function getUserBillingUuid() {
		return($this->userBillingUuid);
	}
	
	public function setUserOptsArray(array $userOptsArray) {
		$this->userOptsArray = $userOptsArray;
	}
	
	public function getUserOptsArray() {
		return($this->userOptsArray);
	}
	
	public function setUserProviderUuid($userProviderUuid) {
		$this->userProviderUuid = $userProviderUuid;
	}
	
	public function getUserProviderUuid() {
		return($this->userProviderUuid);
	}
	
}

?>