<?php

require_once __DIR__ . '/ActionRequest.php';

class ExpireSubscriptionRequest extends ActionRequest {
	
	private $subscriptionBillingUuid = NULL;
	private $expiresDate = NULL;
	private $isForced = false;
	
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
		if($this->getIsAnApiRequest() == true) {
			$this->expiresDate = new DateTime();
		} else if($this->expiresDate == NULL) {
			$this->expiresDate = new DateTime();
		}
		return($this->expiresDate);
	}
	
	public function setIsForced($isForced) {
		$this->isForced = $isForced;
	}
	
	public function getIsForced() {
		return($this->isForced);
	}
	
}

?>