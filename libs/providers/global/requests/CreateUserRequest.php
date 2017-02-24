<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class CreateUserRequest extends ActionRequest {

	protected $providerName = NULL;
	protected $userReferenceUuid = NULL;
	protected $userProviderUuid = NULL;
	protected $userOptsArray = array();
	//
	protected $userBillingUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setProviderName($providerName) {
		$this->providerName = $providerName;
	}
	
	public function getProviderName() {
		return($this->providerName);
	}
	
	public function setUserReferenceUuid($userReferenceUuid) {
		$this->userReferenceUuid = $userReferenceUuid;
	}
	
	public function getUserReferenceUuid() {
		return($this->userReferenceUuid);
	}
	
	public function setUserProviderUuid($userProviderUuid) {
		$this->userProviderUuid = $userProviderUuid;
	}
	
	public function getUserProviderUuid() {
		return($this->userProviderUuid);
	}
	
	public function setUserOptsArray(array $userOptsArray) {
		$this->userOptsArray = $userOptsArray;
	}
	
	public function getUserOptsArray() {
		return($this->userOptsArray);
	}
	
	public function setUserBillingUuid($userBillingUuid) {
		$this->userBillingUuid = $userBillingUuid;
	}
	
	public function getUserBillingUuid() {
		return($this->userBillingUuid);
	}
	
}

?>