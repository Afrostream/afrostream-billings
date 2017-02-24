<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class RemoveInternalPlanFromCountryRequest extends ActionRequest {
	
	protected $internalPlanUuid = NULL;
	protected $country = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setInternalPlanUuid($internalPlanUuid) {
		$this->internalPlanUuid = $internalPlanUuid;
	}
	
	public function getInternalPlanUuid() {
		return($this->internalPlanUuid);
	}
	
	public function setCountry($country) {
		$this->country = $country;
	}
	
	public function getCountry() {
		return($this->country);
	}
	
}

?>