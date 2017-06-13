<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class UpdateProviderPlanRequest extends ActionRequest {
	
	protected $providerPlanBillingUuid = NULL;
	protected $providerPlanOptsArray = array();
	protected $isVisible = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setProviderPlanBillingUuid($providerPlanBillingUuid) {
		$this->providerPlanBillingUuid = $providerPlanBillingUuid;
	}
	
	public function getProviderPlanBillingUuid() {
		return($this->providerPlanBillingUuid);
	}
	
	public function setProviderPlanOptsArray(array $providerPlanOptsArray) {
		$this->providerPlanOptsArray = $providerPlanOptsArray;
	}
	
	public function getProviderPlanOptsArray() {
		return($this->providerPlanOptsArray);
	}
	
	public function setIsVisible($bool) {
		$this->isVisible = $bool;
	}
	
	public function getIsVisible() {
		return($this->isVisible);
	}
	
}

?>