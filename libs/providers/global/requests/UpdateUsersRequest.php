<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class UpdateUsersRequest extends ActionRequest {
	
	protected $userReferenceUuid;
	protected $userOpts;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setUserReferenceUuid($userReferenceUuid) {
		$this->userReferenceUuid = $userReferenceUuid;
	}
	
	public function getUserReferenceUuid() {
		return($this->userReferenceUuid);
	}
	
	public function setUserOpts(array $userOpts = NULL) {
		$this->userOpts = $userOpts;
	}
	
	public function getUserOpts() {
		return($this->userOpts);
	}
	
}

?>