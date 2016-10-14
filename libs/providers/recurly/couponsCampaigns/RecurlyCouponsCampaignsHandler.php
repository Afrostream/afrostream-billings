<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';

class RecurlyCouponsCampaignsHandler {
	
	public function __construct() {
	}
	
	public function createProviderCouponsCampaign(BillingInternalCouponsCampaign $billingInternalCouponsCampaign) {
		$couponsCampaignProviderBillingUuid = NULL;
		try {
			config::getLogger()->addInfo("recurly couponsCampaign creation...");
			//Check Compatibility
			//
			Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
			Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
			//
			$recurly_coupon = new Recurly_Coupon();
			//
			$recurly_coupon->coupon_code = $billingInternalCouponsCampaign->getPrefix();
			$recurly_coupon->name = $billingInternalCouponsCampaign->getName();
			$recurly_coupon->description = $billingInternalCouponsCampaign->getDescription();
			switch($billingInternalCouponsCampaign->getDiscountType()) {
				case 'amount' :
					$recurly_coupon->discount_type = 'dollars';
  					$recurly_coupon->discount_in_cents->addCurrency($billingInternalCouponsCampaign->getCurrency(), $billingInternalCouponsCampaign->getAmountInCents());
					break;
				case 'percent':
					$recurly_coupon->discount_type = 'percent';
					$recurly_coupon->discount_percent = $billingInternalCouponsCampaign->getPercent();
					break;
				default :
					//exception
					break;
			}
			switch($billingInternalCouponsCampaign->getDiscountDuration()) {
				case 'once' :
					break;
				case 'forever' :
					break;
				case 'repeating' :
					break;
				default :
					//exception
					break;
			}
			//
			$recurly_coupon->create();
			$couponsCampaignProviderBillingUuid = $recurly_coupon->coupon_code;
			config::getLogger()->addInfo("recurly couponsCampaign done successfully, recurly_coupon_campaign_uuid=".$couponsCampaignProviderBillingUuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a recurly couponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly couponsCampaign creation failed : ".$msg);
			throw $e;
		} catch (Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while creating a recurly couponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly couponsCampaign creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a recurly couponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("recurly couponsCampaign creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($couponsCampaignProviderBillingUuid);
	}
	
}

?>