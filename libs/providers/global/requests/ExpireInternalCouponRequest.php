<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class ExpireInternalCouponRequest extends ActionRequest {
	
	protected $internalCouponBillingUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
		
	public function setInternalCouponBillingUuid($internalCouponBillingUuid) {
		$this->internalCouponBillingUuid = $internalCouponBillingUuid;
	}
	
	public function getInternalCouponBillingUuid() {
		return($this->internalCouponBillingUuid);
	}
	
}

?>