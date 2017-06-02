<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetProviderPlanRequest extends ActionRequest {
	
	protected $providerPlanBillingUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setProviderPlanBillingUuid($providerPlanBillingUuid) {
		$this->providerPlanBillingUuid = $providerPlanBillingUuid;
	}
	
	public function getProviderPlanBillingUuid() {
		return($this->providerPlanBillingUuid);
	}
		
}

?>