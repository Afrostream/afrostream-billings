<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class UpdateInternalPlanRequest extends ActionRequest {
	
	protected $internalPlanUuid = NULL;
	protected $internalplanOptsArray = array();
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setInternalPlanUuid($internalPlanUuid) {
		$this->internalPlanUuid = $internalPlanUuid;
	}
	
	public function getInternalPlanUuid() {
		return($this->internalPlanUuid);
	}
	
	public function setInternalPlanOpts(array $internalplanOptsArray) {
		$this->internalplanOptsArray = $internalplanOptsArray;
	}
	
	public function getInternalplanOptsArray() {
		return($this->internalplanOptsArray);
	}
	
}

?>