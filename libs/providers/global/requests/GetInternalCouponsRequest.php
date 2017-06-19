<?php

require_once __DIR__ . '/../../../global/requests/ActionHitsRequest.php';

class GetInternalCouponsRequest extends ActionHitsRequest {
	
	protected $internalCouponsCampaignBillingUuid = NULL;
	protected $isExport = false;
	protected $filepath = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setInternalCouponsCampaignBillingUuid($internalCouponsCampaignBillingUuid) {
		$this->internalCouponsCampaignBillingUuid = $internalCouponsCampaignBillingUuid;
	}
	
	public function getInternalCouponsCampaignBillingUuid() {
		return($this->internalCouponsCampaignBillingUuid);
	}
	
	public function setIsExport($bool) {
		$this->isExport = $bool;
	}
	
	public function getIsExport() {
		return($this->isExport);
	}
	
	public function setFilepath($filepath) {
		$this->filepath = $filepath;	
	}
	
	public function getFilepath() {
		return($this->filepath);
	}
		
}

?>