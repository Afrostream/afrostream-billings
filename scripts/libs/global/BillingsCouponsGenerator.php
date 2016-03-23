<?php

require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/utils/utils.php';

class BillingsCouponsGenerator {
	
	public function __construct() {

	}
	
	public function doGenerateCoupons($couponcampaignuuid) {
		try {
			ScriptsConfig::getLogger()->addInfo("generating coupons for couponcampaignuuid=".$couponcampaignuuid."...");
			$coupon_campaign = CouponCampaignDAO::getCouponCampaignByUuid($couponcampaignuuid);
			if($coupon_campaign == NULL) {
				throw new Exception("CouponCampaign with couponcampaignuuid=".$couponcampaignuuid." not found");
			}
			//
			$coupon_counter = CouponDAO::getCouponsTotalNumberByCouponCampaignId($coupon_campaign->getId());
			$coupon_total_number = $coupon_campaign->getTotalNumber();
			$coupon_counter_missing = $coupon_total_number - $coupon_counter;
			ScriptsConfig::getLogger()->addInfo("generating ".$coupon_counter_missing." missing coupons out of ".$coupon_total_number." for couponcampaignuuid=".$couponcampaignuuid."...");
			while($coupon_counter < $coupon_total_number) {
				$coupon = new Coupon();
				$coupon->setCouponCampaignId($coupon_campaign->getId());
				$coupon->setProviderId($coupon_campaign->getProviderId());
				$coupon->setProviderPlanId($coupon_campaign->getProviderPlanId());
				$coupon->setCode($coupon_campaign->getPrefix()."-".$this->getRandomString($coupon_campaign->getGeneratedCodeLength()));
				CouponDAO::addCoupon($coupon);
				$coupon_counter++;
				ScriptsConfig::getLogger()->addInfo("(".$coupon_counter."/".$coupon_total_number.") coupon with code ".$coupon->getCode()." for couponcampaignuuid=".$couponcampaignuuid." generated successfully");
			}
			ScriptsConfig::getLogger()->addInfo("generating coupons for couponcampaignuuid=".$couponcampaignuuid." done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("generating coupons for couponcampaignuuid=".$couponcampaignuuid." failed, error_code=".$e->getCode().", error_message=".$e->getMessage());
			throw $e;
		}
	}
	
	public function getRandomString($length) {
		$strAlphaNumericString = '23456789bcdfghjkmnpqrstvwxz';
		$strReturnString = '';
		for ($intCounter = 0; $intCounter < $length; $intCounter++) {
			$strReturnString .= $strAlphaNumericString[rand(0, strlen($strAlphaNumericString) - 1)];
		}
		return $strReturnString;
	}
	
}
	
?>