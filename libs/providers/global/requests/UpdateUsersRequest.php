<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class UpdateUsersRequest extends ActionRequest {
	
	protected $userReferenceUuid = NULL;
	protected $userOptsArray = array();
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setUserReferenceUuid($userReferenceUuid) {
		$this->userReferenceUuid = $userReferenceUuid;
	}
	
	public function getUserReferenceUuid() {
		return($this->userReferenceUuid);
	}
	
	public function setUserOpts(array $userOptsArray) {
		$this->userOptsArray = $userOptsArray;
	}
	
	public function getUserOptsArray() {
		return($this->userOptsArray);
	}
	
}

?>