<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class SetInternalPlanIndexInContextRequest extends ActionRequest {
	
	protected $contextBillingUuid = NULL;
	protected $contextCountry = NULL;
	protected $internalPlanUuid = NULL;
	protected $index = NULL;
	
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
	
	public function setIndex($index) {
		$this->index = $index;
	}
	
	public function getIndex() {
		return($this->index);
	}
	
}

?>