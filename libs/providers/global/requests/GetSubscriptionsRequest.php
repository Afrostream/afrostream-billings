<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetSubscriptionsRequest extends ActionRequest {
	
	protected $userReferenceUuid = NULL;
	protected $clientId = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setUserReferenceUuid($userReferenceUuid) {
		$this->userReferenceUuid = $userReferenceUuid;
	}
	
	public function getUserReferenceUuid() {
		return($this->userReferenceUuid);
	}
	
	public function setClientId($clientId) {
		$this->clientId = $clientId;
	}
	
	public function getClientId() {
		return($this->clientId);
	}
	
}

?>