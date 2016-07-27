<?php

require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/utils/utils.php';

class BillingsCouponsGenerator {
	
	public function __construct() {

	}
	
	public function doGenerateCoupons($couponscampaignuuid) {
		try {
			ScriptsConfig::getLogger()->addInfo("generating coupons for couponscampaignuuid=".$couponscampaignuuid."...");
			$coupons_campaign = CouponsCampaignDAO::getCouponsCampaignByUuid($couponscampaignuuid);
			if($coupons_campaign == NULL) {
				throw new Exception("CouponsCampaign with couponscampaignuuid=".$couponscampaignuuid." not found");
			}
			//
			$coupon_counter = CouponDAO::getCouponsTotalNumberByCouponsCampaignId($coupons_campaign->getId());
			$coupon_total_number = $coupons_campaign->getTotalNumber();
			$coupon_counter_missing = $coupon_total_number - $coupon_counter;
			ScriptsConfig::getLogger()->addInfo("generating ".$coupon_counter_missing." missing coupons out of ".$coupon_total_number." for couponscampaignuuid=".$couponscampaignuuid."...");
			while($coupon_counter < $coupon_total_number) {
				$coupon = new Coupon();
				$coupon->setCouponBillingUuid(guid());
				$coupon->setCouponsCampaignId($coupons_campaign->getId());
				$coupon->setProviderId($coupons_campaign->getProviderId());
				$coupon->setProviderPlanId($coupons_campaign->getProviderPlanId());
				$coupon->setCode(strtoupper($coupons_campaign->getPrefix()."-".$this->getRandomString($coupons_campaign->getGeneratedCodeLength())));
				CouponDAO::addCoupon($coupon);
				$coupon_counter++;
				ScriptsConfig::getLogger()->addInfo("(".$coupon_counter."/".$coupon_total_number.") coupon with code ".$coupon->getCode()." for couponscampaignuuid=".$couponscampaignuuid." generated successfully");
			}
			ScriptsConfig::getLogger()->addInfo("generating coupons for couponscampaignuuid=".$couponscampaignuuid." done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("generating coupons for couponscampaignuuid=".$couponscampaignuuid." failed, error_code=".$e->getCode().", error_message=".$e->getMessage());
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