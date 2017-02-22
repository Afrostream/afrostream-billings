<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class AddInternalPlanToContextRequest extends ActionRequest {
	
	private $contextBillingUuid = NULL;
	private $contextCountry = NULL;
	private $internalPlanUuid = NULL;
	
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
	
	public function setInternalPlanUuid($internalPlanUuid) {
		$this->internalPlanUuid = $internalPlanUuid;
	}
	
	public function getInternalPlanUuid() {
		return($this->internalPlanUuid);
	}
	
}

?>