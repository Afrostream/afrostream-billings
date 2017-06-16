<?php

require_once __DIR__ . '/../../../global/requests/ActionHitsRequest.php';

class GetInternalCouponsRequest extends ActionHitsRequest {
	
	protected $internalCouponsCampaignBillingUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setInternalCouponsCampaignBillingUuid($internalCouponsCampaignBillingUuid) {
		$this->internalCouponsCampaignBillingUuid = $internalCouponsCampaignBillingUuid;
	}
	
	public function getInternalCouponsCampaignBillingUuid() {
		return($this->internalCouponsCampaignBillingUuid);
	}
	
}

?>