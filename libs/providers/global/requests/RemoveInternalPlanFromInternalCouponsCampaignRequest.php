<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class RemoveInternalPlanFromInternalCouponsCampaignRequest extends ActionRequest {
	
	protected $couponsCampaignInternalBillingUuid = NULL;
	protected $internalPlanUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid) {
		$this->couponsCampaignInternalBillingUuid = $couponsCampaignInternalBillingUuid;
	}
	
	public function getCouponsCampaignInternalBillingUuid() {
		return($this->couponsCampaignInternalBillingUuid);
	}
	
	public function setInternalPlanUuid($internalPlanUuid) {
		$this->internalPlanUuid = $internalPlanUuid;
	}
	
	public function getInternalPlanUuid() {
		return($this->internalPlanUuid);
	}
	
}

?>