<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';

class CouponsCampaignsHandler {
	
	public function __construct() {
	}
	
	public function doGetCouponsCampaigns($provider_name = NULL) {
		$db_coupons_campaigns = NULL;
		try {
			config::getLogger()->addInfo("CouponsCampaigns getting...");
			$provider_id = NULL;
			if(isset($provider_name)) {
				$provider = ProviderDAO::getProviderByName($provider_name);
				if($provider == NULL) {
					$msg = "unknown provider named : ".$provider_name;
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$provider_id = $provider->getId();
			}
			$db_coupons_campaigns =  CouponsCampaignDAO::getCouponsCampaigns($provider_id);
			config::getLogger()->addInfo("CouponsCampaigns getting done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting CouponsCampaigns, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("CouponsCampaigns getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting CouponsCampaigns, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("CouponsCampaigns getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_coupons_campaigns);
	}
	
	public function doGetCouponsCampaign($couponsCampaignBillingUuid) {
		$db_coupons_campaign = NULL;
		try {
			config::getLogger()->addInfo("CouponsCampaign getting, couponsCampaignBillingUuid=".$couponsCampaignBillingUuid."....");
			//
			$db_coupons_campaign = CouponsCampaignDAO::getCouponsCampaignByUuid($couponsCampaignBillingUuid);
			//
			config::getLogger()->addInfo("CouponsCampaign getting, couponsCampaignBillingUuid=".$couponsCampaignBillingUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting a CouponsCampaign for couponsCampaignBillingUuid=".$couponsCampaignBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("CouponsCampaign getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a CouponsCampaign for couponsCampaignBillingUuid=".$couponsCampaignBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("CouponsCampaign getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_coupons_campaign);
	}
	
}

?>