<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetInternalCouponsCampaignRequest extends ActionRequest {
	
	protected $couponsCampaignInternalBillingUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid) {
		$this->couponsCampaignInternalBillingUuid = $couponsCampaignInternalBillingUuid;
	}
	
	public function getCouponsCampaignInternalBillingUuid() {
		return($this->couponsCampaignInternalBillingUuid);
	}
		
}

?>