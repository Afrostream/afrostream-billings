<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class CreateUserEphemeralKeyRequest extends ActionRequest {

	protected $userBillingUuid = NULL;
	//
	protected $apiVersion = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setUserBillingUuid($userBillingUuid) {
		$this->userBillingUuid = $userBillingUuid;
	}
	
	public function getUserBillingUuid() {
		return($this->userBillingUuid);
	}
	
	public function setApiVersion($apiVersion) {
		$this->apiVersion = $apiVersion;
	}
	
	public function getApiVersion() {
		return($this->apiVersion);
	}
	
}

?>