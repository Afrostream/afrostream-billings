<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class CreateUserRequest extends ActionRequest {

	protected $providerName;
	protected $userReferenceUuid;
	protected $userProviderUuid;
	protected $userOpts;
	
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
	
	public function setUserOpts(array $userOpts = NULL) {
		$this->userOpts = $userOpts;
	}
	
	public function getUserOpts() {
		return($this->userOpts);
	}
	
}

?>