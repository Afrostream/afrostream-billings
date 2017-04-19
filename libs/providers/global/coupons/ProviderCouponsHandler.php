<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class ProviderCouponsHandler {
	
	protected $provider = NULL;
	protected $platform = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
		$this->platform = BillingPlatformDAO::getPlatformById($this->provider->getPlatformId());
	}
	
	public function doCreateCoupon(User $user,
			UserOpts $userOpts,
			BillingInternalCouponsCampaign $internalCouponsCampaign,
			BillingProviderCouponsCampaign $providerCouponsCampaign,
			InternalPlan $internalPlan = NULL,
			$coupon_billing_uuid,
			BillingsCouponsOpts $billingCouponsOpts) {
		$msg = "unsupported feature - create user coupon - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	public function createDbCouponFromApiCouponUuid(User $user,
			UserOpts $userOpts,
			BillingInternalCouponsCampaign $internalCouponsCampaign,
			BillingProviderCouponsCampaign $providerCouponsCampaign,
			InternalPlan $internalPlan = NULL,
			$coupon_billing_uuid,
			$coupon_provider_uuid) {
		$msg = "unsupported feature - create user coupon from api coupon uuid - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
}

?>