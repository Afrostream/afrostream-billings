<?php

require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/utils/utils.php';

class BillingOrderTool {
	
	protected $partner;
	
	public function __construct(BillingPartner $partner) {
		$this->partner = $partner;
	}
	
	public function create($type, $name) {
		$billingPartnerOrder = new BillingPartnerOrder();
		$billingPartnerOrder->setPartnerOrderBillingUuid(guid());
		$billingPartnerOrder->setPartnerId($this->partner->getId());
		$billingPartnerOrder->setType($type);
		$billingPartnerOrder->setName($name);
		$billingPartnerOrder = BillingPartnerOrderDAO::addBillingPartnerOrder($billingPartnerOrder);	
		return($billingPartnerOrder);
	}
	
	
}

?>