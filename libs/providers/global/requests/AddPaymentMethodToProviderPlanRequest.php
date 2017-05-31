<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class AddPaymentMethodToProviderPlanRequest extends ActionRequest {
	
	protected $providerPlanBillingUuid = NULL;
	protected $paymentMethodType = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setProviderPlanBillingUuid($providerPlanBillingUuid) {
		$this->providerPlanBillingUuid = $providerPlanBillingUuid;
	}
	
	public function getProviderPlanBillingUuid() {
		return($this->providerPlanBillingUuid);
	}
	
	public function setPaymentMethodType($paymentMethodType) {
		$this->paymentMethodType = $paymentMethodType;
	}
	
	public function getPaymentMethodType() {
		return($this->paymentMethodType);
	}
	
}

?>