<?php

require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/utils/utils.php';
require_once __DIR__ . '/../../../libs/internalCouponsCampaigns/InternalCouponsCampaignsHandler.php';
require_once __DIR__ . '/../../../libs/providers/global/requests/GenerateInternalCouponsRequest.php';
class BillingsCouponsGenerator {
	
	public function __construct() {

	}
	
	public function doGenerateCoupons($couponsCampaignInternalBillingUuid, $platformId) {
		try {
			ScriptsConfig::getLogger()->addInfo("generating coupons for couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid."...");
			$internalCouponsCampaignsHandler = new InternalCouponsCampaignsHandler();
			$generateInternalCouponsRequest = new GenerateInternalCouponsRequest();
			$generateInternalCouponsRequest->setOrigin('script');
			$generateInternalCouponsRequest->setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid);
			$generateInternalCouponsRequest->setPlatform(BillingPlatformDAO::getPlatformById($platformId));
			$internalCouponsCampaignsHandler->doGenerateInternalCoupons($generateInternalCouponsRequest);
			ScriptsConfig::getLogger()->addInfo("generating coupons for couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid." done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("generating coupons for couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid." failed, error_code=".$e->getCode().", error_message=".$e->getMessage());
			throw $e;
		}
	}
	
}
	
?>