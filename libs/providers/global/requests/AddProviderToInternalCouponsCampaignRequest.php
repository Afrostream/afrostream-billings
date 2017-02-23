<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class AddProviderToInternalCouponsCampaignRequest extends ActionRequest {
	
	protected $couponsCampaignInternalBillingUuid = NULL;
	protected $providerName = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid) {
		$this->couponsCampaignInternalBillingUuid = $couponsCampaignInternalBillingUuid;
	}
	
	public function getCouponsCampaignInternalBillingUuid() {
		return($this->couponsCampaignInternalBillingUuid);
	}
	
	public function setProviderName($providerName) {
		$this->providerName = $providerName;
	}
	
	public function getProviderName() {
		return($this->providerName);
	}
		
}

?>