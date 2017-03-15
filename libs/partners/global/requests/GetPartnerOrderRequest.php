<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetPartnerOrderRequest extends ActionRequest {
	
	private $partnerOrderBillingUuid;
		
	public function __construct() {
		parent::__construct();
	}
	
	public function setPartnerOrderBillingUuid($partnerOrderBillingUuid) {
		$this->partnerOrderBillingUuid = $partnerOrderBillingUuid;
	}
	
	public function getPartnerOrderBillingUuid() {
		return($this->partnerOrderBillingUuid);
	}
	
}

?>