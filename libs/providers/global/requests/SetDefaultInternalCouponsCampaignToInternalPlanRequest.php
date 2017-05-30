<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class SetDefaultInternalCouponsCampaignToInternalPlanRequest extends ActionRequest {

	protected $internalPlanUuid = NULL;
	protected $couponsCampaignInternalBillingUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
		
	public function setInternalPlanUuid($internalPlanUuid) {
		$this->internalPlanUuid = $internalPlanUuid;
	}
	
	public function getInternalPlanUuid() {
		return($this->internalPlanUuid);
	}
	
	public function setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid) {
		$this->couponsCampaignInternalBillingUuid = $couponsCampaignInternalBillingUuid;
	}
	
	public function getCouponsCampaignInternalBillingUuid() {
		return($this->couponsCampaignInternalBillingUuid);
	}
	
}

?>