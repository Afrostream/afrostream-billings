<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetInternalPlansRequest extends ActionRequest {
	
	protected $providerName = NULL;
	protected $contextBillingUuid = NULL;
	protected $contextCountry = NULL;
	protected $isVisible = NULL;
	protected $country = NULL;
	protected $filteredArray = array();
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setProviderName($providerName) {
		$this->providerName = $providerName;
	}
	
	public function getProviderName() {
		return($this->providerName);
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
	
	public function setIsVisible($isVisible) {
		$this->isVisible = $isVisible;
	}
	
	public function getIsVisible() {
		return($this->isVisible);
	}
	
	public function setCountry($country) {
		$this->country = $country;
	}
	
	public function getCountry() {
		return($this->country);
	}
	
	public function setFilteredArray(array $filteredArray) {
		$this->filteredArray = $filteredArray;
	}
	
	public function getFilteredArray() {
		return($this->filteredArray);
	}
	
}

?>