<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetUsersInternalCouponRequest extends ActionRequest {
	
	protected $internalUserCouponBillingUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
		
	public function setInternalUserCouponBillingUuid($internalUserCouponBillingUuid) {
		$this->internalUserCouponBillingUuid = $internalUserCouponBillingUuid;
	}
	
	public function getInternalUserCouponBillingUuid() {
		return($this->internalUserCouponBillingUuid);
	}
	
}

?>