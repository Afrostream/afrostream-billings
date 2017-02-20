<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class CancelSubscriptionRequest extends ActionRequest {
	
	private $subscriptionBillingUuid = NULL;
	private $cancelDate = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setSubscriptionBillingUuid($subscriptionBillingUuid) {
		$this->subscriptionBillingUuid = $subscriptionBillingUuid;
	}
	
	public function getSubscriptionBillingUuid() {
		return($this->subscriptionBillingUuid);
	}
	
	public function setCancelDate(DateTime $date = NULL) {
		$this->cancelDate = $date;
	}
	
	public function getCancelDate() {
		if($this->getOrigin() == 'api') {
			$this->cancelDate = new DateTime();
		} else if($this->cancelDate == NULL) {
			$this->cancelDate = new DateTime();
		}
		return($this->cancelDate);
	}
	
}

?>