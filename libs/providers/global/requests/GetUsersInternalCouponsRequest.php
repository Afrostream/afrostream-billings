<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetUsersInternalCouponsRequest extends ActionRequest {
	
	protected $userBillingUuid = NULL;
	protected $couponsCampaignType = NULL;
	protected $internalCouponsCampaignBillingUuid = NULL;
	protected $recipientIsFilled = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setUserBillingUuid($userBillingUuid) {
		$this->userBillingUuid = $userBillingUuid;
	}
	
	public function getUserBillingUuid() {
		return($this->userBillingUuid);
	}
	
	public function setCouponsCampaignType($couponsCampaignType) {
		$this->couponsCampaignType = $couponsCampaignType;
	}
	
	public function getCouponsCampaignType() {
		return($this->couponsCampaignType);
	}
	
	public function setInternalCouponsCampaignBillingUuid($internalCouponsCampaignBillingUuid) {
		$this->internalCouponsCampaignBillingUuid = $internalCouponsCampaignBillingUuid;
	}
	
	public function getInternalCouponsCampaignBillingUuid() {
		return($this->internalCouponsCampaignBillingUuid);
	}
	
	public function setRecipientIsFilled($recipientIsFilled) {
		$this->recipientIsFilled = $recipientIsFilled;
	}
	
	public function getRecipientIsFilled() {
		return($this->recipientIsFilled);
	}
	
}

?>