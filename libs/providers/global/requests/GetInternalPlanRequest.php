<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetInternalPlanRequest extends ActionRequest {
	
	protected $internalPlanUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setInternalPlanUuid($internalPlanUuid) {
		$this->internalPlanUuid = $internalPlanUuid;
	}
	
	public function getInternalPlanUuid() {
		return($this->internalPlanUuid);
	}
	
}

?>