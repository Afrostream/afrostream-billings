<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';

class UsersInternalCouponsHandler {
	
	public function __construct() {
	}
	
	public function doGetList($userBillingUuid, $internalCouponCampaignType = null, $couponsCampaignInternalBillingUuid = null)
	{
		$user = UserDAO::getUserByUserBillingUuid($userBillingUuid);
		if($user == NULL) {
			$msg = "unknown user_billing_uuid : ".$userBillingUuid;
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	
		$internalCouponsCampaignBillingId = null;
		if (!is_null($couponsCampaignInternalBillingUuid)) {
			$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($couponsCampaignInternalBillingUuid);
	
			if($internalCouponsCampaign == NULL) {
				$msg = "unknown couponsCampaignInternalBillingUuid : ".$couponsCampaignInternalBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
	
			$internalCouponsCampaignBillingId = $internalCouponsCampaign->getId();
		}
		
		$list = BillingUserInternalCouponDAO::getBillingUserInternalCouponsByUserId($user->getId(), $internalCouponCampaignType, $internalCouponsCampaignBillingId);
	
		return $list;
	}
	
	public function doCreateCoupon($userBillingUuid, $couponsCampaignBillingUuid, array $couponOpts) {
		$db_coupon = NULL;
		try {
			config::getLogger()->addInfo("user coupon creating....");
			//user
			$user = UserDAO::getUserByUserBillingUuid($userBillingUuid);
			if($user == NULL) {
				$msg = "unknown user_billing_uuid : ".$userBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			//$couponsCampaign
			$couponsCampaign = CouponsCampaignDAO::getCouponsCampaignByUuid($couponsCampaignBillingUuid);
			if($couponsCampaign == NULL) {
				$msg = "unknown couponsCampaignBillingUuid : ".$couponsCampaignBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//provider_from_user
			$provider_from_user = ProviderDAO::getProviderById($user->getProviderId());
			if($provider_from_user == NULL) {
				$msg = "unknown provider with id : ".$user->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//provider_from_coupons_campaign
			$provider_from_coupons_campaign = ProviderDAO::getProviderById($couponsCampaign->getProviderId());
			if($provider_from_coupons_campaign == NULL) {
				$msg = "unknown provider with id : ".$couponsCampaign->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($provider_from_user->getId() != $provider_from_coupons_campaign->getId()) {
				$msg = "providers do not match beetween the user with user_billing_uuid=".$userBillingUuid." and the coupons_campaign with couponsCampaignBillingUuid=".$couponsCampaignBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
				
			$billingCouponsOpts = new BillingsCouponsOpts($couponOpts);
	
				
			$provider = $provider_from_user;
			//
			$coupon_billing_uuid = guid();
			$coupon_provider_uuid = NULL;
			switch($provider->getName()) {
				case 'cashway' :
					$cashwayCouponsHandler = new CashwayCouponsHandler();
					$coupon_provider_uuid = $cashwayCouponsHandler->doCreateCoupon($user, $userOpts, $couponsCampaign, $coupon_billing_uuid, $billingCouponsOpts);
					break;
				case 'afr' :
					$afrCouponHandler = new AfrCouponsHandler();
					$coupon_provider_uuid = $afrCouponHandler->doCreateCoupon($user, $userOpts, $couponsCampaign, $coupon_billing_uuid, $billingCouponsOpts);
					break;
				default :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
	
			switch ($provider->getName()) {
				case 'cashway' :
					$cashwayCouponsHandler = new CashwayCouponsHandler();
					$db_coupon = $cashwayCouponsHandler->createDbCouponFromApiCouponUuid($user, $userOpts, $couponsCampaign, $coupon_billing_uuid, $coupon_provider_uuid);
					break;
				case 'afr' :
					$afrCouponHandler = new AfrCouponsHandler();
					$db_coupon = $afrCouponHandler->createDbCouponFromApiCouponUuid($user, $userOpts, $couponsCampaign, $coupon_billing_uuid, $coupon_provider_uuid);
					break;
				default :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			config::getLogger()->addInfo("user coupon creating done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating an user coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user coupon creating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating an user coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user coupon creating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_coupon);
	}
	
}

?>