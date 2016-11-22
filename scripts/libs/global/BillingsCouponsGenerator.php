<?php

require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/utils/utils.php';

class BillingsCouponsGenerator {
	
	public function __construct() {

	}
	
	public function doGenerateCoupons($couponsCampaignInternalBillingUuid) {
		try {
			ScriptsConfig::getLogger()->addInfo("generating coupons for couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid."...");
			$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($couponsCampaignInternalBillingUuid);
			if($internalCouponsCampaign == NULL) {
				throw new Exception("internalCouponsCampaign with couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid." not found");
			}
			//
			$coupon_counter = BillingInternalCouponDAO::getBillingInternalCouponsTotalNumberByInternalCouponsCampaignsId($internalCouponsCampaign->getId());
			$coupon_total_number = $internalCouponsCampaign->getGeneratedMode() == 'single' ? 1 : $internalCouponsCampaign->getTotalNumber();
			$coupon_counter_missing = $coupon_total_number - $coupon_counter;
			ScriptsConfig::getLogger()->addInfo("generating ".$coupon_counter_missing." missing coupons out of ".$coupon_total_number." for couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid."...");
			while($coupon_counter < $coupon_total_number) {
				$code = NULL;
				if($internalCouponsCampaign->getGeneratedMode() == 'single') {
					$code = strtoupper($internalCouponsCampaign->getPrefix());
				} else {
					$code = strtoupper($internalCouponsCampaign->getPrefix()."-".$this->getRandomString($internalCouponsCampaign->getGeneratedCodeLength()));
				}
				$internalCoupon = new BillingInternalCoupon();
				$internalCoupon->setInternalCouponsCampaignsId($internalCouponsCampaign->getId());
				$internalCoupon->setCode($code);
				$internalCoupon->setUuid(guid());
				$internalCoupon->setExpiresDate($internalCouponsCampaign->getExpiresDate());
				$internalCoupon = BillingInternalCouponDAO::addBillingInternalCoupon($internalCoupon);
				$coupon_counter++;
				ScriptsConfig::getLogger()->addInfo("(".$coupon_counter."/".$coupon_total_number.") coupon with code ".$internalCoupon->getCode()." for couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid." generated successfully");
			}
			ScriptsConfig::getLogger()->addInfo("generating coupons for couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid." done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("generating coupons for couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid." failed, error_code=".$e->getCode().", error_message=".$e->getMessage());
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