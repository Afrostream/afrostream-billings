<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class ApplyCouponRequest extends ActionRequest {
	
	protected $subscriptionBillingUuid = NULL;
	protected $couponCode = NULL;
	protected $force = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setSubscriptionBillingUuid($subscriptionBillingUuid) {
		$this->subscriptionBillingUuid = $subscriptionBillingUuid;
	}
	
	public function getSubscriptionBillingUuid() {
		return($this->subscriptionBillingUuid);
	}
	
	public function setCouponCode($couponCode) {
		$this->couponCode = $couponCode;
	}
	
	public function getCouponCode() {
		return($this->couponCode);
	}
	
	public function setForce($force) {
		$this->force = $force;
	}
	
	public function getForce() {
		return($this->force);
	}
	
}

?>