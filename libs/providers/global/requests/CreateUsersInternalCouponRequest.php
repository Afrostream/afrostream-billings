<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class CreateUsersInternalCouponRequest extends ActionRequest {
	
	protected $userBillingUuid = NULL;
	protected $internalCouponsCampaignBillingUuid = NULL;
	protected $internalPlanUuid = NULL;
	protected $couponOptsArray = array();
		
	public function __construct() {
		parent::__construct();
	}
	
	public function setUserBillingUuid($userBillingUuid) {
		$this->userBillingUuid = $userBillingUuid;
	}
	
	public function getUserBillingUuid() {
		return($this->userBillingUuid);
	}
	
	public function setInternalCouponsCampaignBillingUuid($internalCouponsCampaignBillingUuid) {
		$this->internalCouponsCampaignBillingUuid = $internalCouponsCampaignBillingUuid;
	}
	
	public function getInternalCouponsCampaignBillingUuid() {
		return($this->internalCouponsCampaignBillingUuid);
	}
	
	public function setInternalPlanUuid($internalPlanUuid) {
		$this->internalPlanUuid = $internalPlanUuid;
	}
	
	public function getInternalPlanUuid() {
		return($this->internalPlanUuid);
	}
	
	public function setCouponOptsArray(array $couponOptsArray) {
		$this->couponOptsArray = $couponOptsArray;
	}
	
	public function getCouponOptsArray() {
		return($this->couponOptsArray);
	}
	
}

?>