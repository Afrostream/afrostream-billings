<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetUserSubscriptionsRequest extends ActionRequest {
	
	protected $userBillingUuid = NULL;
	protected $clientId = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setUserBillingUuid($userBillingUuid) {
		$this->userBillingUuid = $userBillingUuid;
	}
	
	public function getUserBillingUuid() {
		return($this->userBillingUuid);
	}
	
	public function setClientId($clientId) {
		$this->clientId = $clientId;
	}
	
	public function getClientId() {
		return($this->clientId);
	}
	
}

?>