<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetInternalCouponRequest extends ActionRequest {
	
	protected $couponCode = NULL;
	protected $internalCouponBillingUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setCouponCode($couponCode) {
		$this->couponCode = $couponCode;
	}
	
	public function getCouponCode() {
		return($this->couponCode);
	}
	
	public function setInternalCouponBillingUuid($internalCouponBillingUuid) {
		$this->internalCouponBillingUuid = $internalCouponBillingUuid;
	}
	
	public function getInternalCouponBillingUuid() {
		return($this->internalCouponBillingUuid);
	}
	
}

?>