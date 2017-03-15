<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../global/couponsCampaigns/ProviderCouponsCampaignsHandler.php';

class AfrCouponsCampaignsHandler extends ProviderCouponsCampaignsHandler {
	
	public function createProviderCouponsCampaign(BillingInternalCouponsCampaign $billingInternalCouponsCampaign) {
		return(guid());
	}
	
}

?>