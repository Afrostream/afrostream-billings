<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetInternalCouponsCampaignsRequest extends ActionRequest {
	
	protected $couponsCampaignType = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setCouponsCampaignType($couponsCampaignType) {
		$this->couponsCampaignType = $couponsCampaignType;
	}
	
	public function getCouponsCampaignType() {
		return($this->couponsCampaignType);
	}
	
}

?>