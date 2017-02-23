<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class RenewSubscriptionRequest extends ActionRequest {
	
	protected $subscriptionBillingUuid = NULL;
	protected $startDate = NULL;
	protected $endDate = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setSubscriptionBillingUuid($subscriptionBillingUuid) {
		$this->subscriptionBillingUuid = $subscriptionBillingUuid;
	}
	
	public function getSubscriptionBillingUuid() {
		return($this->subscriptionBillingUuid);
	}
	
	public function setStartDate(DateTime $startDate = NULL) {
		$this->startDate = $startDate;
	}
	
	public function getStartDate() {
		return($this->startDate);
	}
	
	public function setEndDate(DateTime $endDate = NULL) {
		$this->endDate = $endDate;
	}
	
	public function getEndDate() {
		return($this->endDate);
	}
	
}

?>