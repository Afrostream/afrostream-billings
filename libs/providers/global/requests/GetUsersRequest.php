<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetUsersRequest extends ActionRequest {
	
	protected $userReferenceUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setUserReferenceUuid($userReferenceUuid) {
		$this->userReferenceUuid = $userReferenceUuid;
	}
	
	public function getUserReferenceUuid() {
		return($this->userReferenceUuid);
	}
	
}

?>