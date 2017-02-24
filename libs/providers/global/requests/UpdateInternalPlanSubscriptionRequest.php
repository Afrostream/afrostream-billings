<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class UpdateInternalPlanSubscriptionRequest extends ActionRequest {
	
	protected $subscriptionBillingUuid = NULL;
	protected $internalPlanUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setSubscriptionBillingUuid($subscriptionBillingUuid) {
		$this->subscriptionBillingUuid = $subscriptionBillingUuid;
	}
	
	public function getSubscriptionBillingUuid() {
		return($this->subscriptionBillingUuid);
	}
	
	public function setInternalPlanUuid($internalPlanUuid) {
		$this->internalPlanUuid = $internalPlanUuid;
	}
	
	public function getInternalPlanUuid() {
		return($this->internalPlanUuid);
	}
	
}

?>