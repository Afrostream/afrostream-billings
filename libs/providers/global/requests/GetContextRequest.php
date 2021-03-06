<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetContextRequest extends ActionRequest {
	
	protected $contextBillingUuid = NULL;
	protected $contextCountry = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setContextBillingUuid($contextBillingUuid) {
		$this->contextBillingUuid = $contextBillingUuid;
	}
	
	public function getContextBillingUuid() {
		return($this->contextBillingUuid);
	}
	
	public function setContextCountry($contextCountry) {
		$this->contextCountry = $contextCountry;
	}
	
	public function getContextCountry() {
		return($this->contextCountry);
	}
	
}

?>