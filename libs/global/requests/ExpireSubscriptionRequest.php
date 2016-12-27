<?php

class ExpireSubscriptionRequest extends ActionRequest {
	
	private $subscriptionBillingUuid = NULL;
	private $expiresDate = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setSubscriptionBillingUuid($subscriptionBillingUuid) {
		$this->subscriptionBillingUuid = $subscriptionBillingUuid;
	}
	
	public function getSubscriptionBillingUuid() {
		return($this->subscriptionBillingUuid);
	}
	
	public function setExpiresDate(Datetime $date = NULL) {
		$this->expiresDate = $date;
	}
	
	public function getExpiresDate() {
		return($this->expiresDate);
	}
	
}

?>