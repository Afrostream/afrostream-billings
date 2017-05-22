<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../global/couponsCampaigns/ProviderCouponsCampaignsHandler.php';

class AfrCouponsCampaignsHandler extends ProviderCouponsCampaignsHandler {
	
	public function createProviderCouponsCampaign(BillingInternalCouponsCampaign $billingInternalCouponsCampaign) {
		//Verifications ...
		$supported_coupon_types = [
				new CouponCampaignType(CouponCampaignType::standard),
				new CouponCampaignType(CouponCampaignType::prepaid),
				new CouponCampaignType(CouponCampaignType::sponsorship)
		];
		if(!in_array($billingInternalCouponsCampaign->getCouponType(), $supported_coupon_types)) {
			$msg = "unsupported couponsCampaignType : ".$billingInternalCouponsCampaign->getCouponType()->getValue()." by provider named : ".$this->provider->getName();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//Verifications OK
		return(guid());
	}
	
}

?>