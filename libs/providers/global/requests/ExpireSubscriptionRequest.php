<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class ExpireSubscriptionRequest extends ActionRequest {
	
	protected $subscriptionBillingUuid = NULL;
	protected $expiresDate = NULL;
	protected $forceBeforeEndsDate = false;
	protected $isRefundEnabled = false;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setSubscriptionBillingUuid($subscriptionBillingUuid) {
		$this->subscriptionBillingUuid = $subscriptionBillingUuid;
	}
	
	public function getSubscriptionBillingUuid() {
		return($this->subscriptionBillingUuid);
	}
	
	public function setExpiresDate(DateTime $date = NULL) {
		$this->expiresDate = $date;
	}
	
	public function getExpiresDate() {
		if($this->getOrigin() == 'api') {
			$this->expiresDate = new DateTime();
		} else if($this->expiresDate == NULL) {
			$this->expiresDate = new DateTime();
		}
		return($this->expiresDate);
	}
	
	public function setForceBeforeEndsDate($forceBeforeEndsDate) {
		$this->forceBeforeEndsDate = $forceBeforeEndsDate;
	}
	
	public function getForceBeforeEndsDate() {
		return($this->forceBeforeEndsDate);
	}
	
	public function setIsRefundEnabled($isRefundEnabled) {
		$this->isRefundEnabled = $isRefundEnabled;
	}
	
	public function getIsRefundEnabled() {
		return($this->isRefundEnabled);
	}
	
}

?>