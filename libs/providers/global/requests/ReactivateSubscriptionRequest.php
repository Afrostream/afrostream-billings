<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class ReactivateSubscriptionRequest extends ActionRequest {
	
	protected $subscriptionBillingUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setSubscriptionBillingUuid($subscriptionBillingUuid) {
		$this->subscriptionBillingUuid = $subscriptionBillingUuid;
	}
	
	public function getSubscriptionBillingUuid() {
		return($this->subscriptionBillingUuid);
	}
	
}

?>