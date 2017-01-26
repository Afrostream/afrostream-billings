<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class AddInternalCouponsCampaignToPartnerOrderRequest extends ActionRequest {
	
	private $partnerOrderBillingUuid;
	private $internalCouponsCampaignBillingUuid;
	private $wishedCouponsCounter;
		
	public function __construct() {
		parent::__construct();
	}
	
	public function setPartnerOrderBillingUuid($partnerOrderBillingUuid) {
		$this->partnerOrderBillingUuid = $partnerOrderBillingUuid;
	}
	
	public function getPartnerOrderBillingUuid() {
		return($this->partnerOrderBillingUuid);
	}
	
	public function setInternalCouponsCampaignBillingUuid($internalCouponsCampaignBillingUuid) {
		$this->internalCouponsCampaignBillingUuid = $internalCouponsCampaignBillingUuid;
	}
	
	public function getInternalCouponsCampaignBillingUuid() {
		return($this->internalCouponsCampaignBillingUuid);
	}
	
	public function setWishedCouponsCounter($wishedCouponsCounter) {
		$this->wishedCouponsCounter = $wishedCouponsCounter;
	}
	
	public function getWishedCouponsCounter() {
		return($this->wishedCouponsCounter);
	}
	
}

?>