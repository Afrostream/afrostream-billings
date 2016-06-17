<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../providers/afr/coupons/AfrCouponsHandler.php';
require_once __DIR__ . '/../providers/cashway/coupons/CashwayCouponsHandler.php';
require_once __DIR__ . '/../providers/stripe/coupons/StripeCouponsHandler.php';

class CouponsHandler {
	
	public function __construct() {
	}
	
	public function doGetCoupon($providerName, $couponCode, $userBillingUuid = NULL) {
		$db_coupon = NULL;
		try {
			config::getLogger()->addInfo("coupon getting, couponCode=".$couponCode."....");
			//
			$provider = ProviderDAO::getProviderByName($providerName);
				
			if($provider == NULL) {
				$msg = "unknown provider named : ".$providerName;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$user = NULL;
			$userOpts = NULL;
			if(isset($userBillingUuid)) {
				$user = UserDAO::getUserByUserBillingUuid($userBillingUuid);
				if($user == NULL) {
					$msg = "unknown user_billing_uuid : ".$userBillingUuid;
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($user->getProviderId() != $provider->getId()) {
					$msg = "providers do not match beetween the user with user_billing_uuid=".$userBillingUuid." and the providerName=".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);					
				}
				$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			}
			switch($provider->getName()) {
				case 'afr' :
					$afrCouponsHandler = new AfrCouponsHandler();
					$db_coupon = $afrCouponsHandler->doGetCoupon($user, $userOpts, $couponCode);
					break;
				case 'cashway' :
					$cashwayCouponsHandler = new CashwayCouponsHandler();
					$db_coupon = $cashwayCouponsHandler->doGetCoupon($user, $userOpts, $couponCode);
					break;
				default :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			config::getLogger()->addInfo("coupon getting, providerName=".$providerName.", couponCode=".$couponCode." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting a coupon for couponCode=".$couponCode.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("coupon getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a coupon for couponCode=".$couponCode.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("coupon getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_coupon);
	}
	
	public function doCreateCoupon($userBillingUuid, $couponsCampaignBillingUuid) {
		$db_coupon = NULL;
		try {
			config::getLogger()->addInfo("coupon creating....");
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
			$provider = $provider_from_user;
			//
			$coupon_billing_uuid = guid();
			switch($provider->getName()) {
				case 'cashway' :
					$cashwayCouponsHandler = new CashwayCouponsHandler();
					$coupon_provider_uuid = $cashwayCouponsHandler->doCreateCoupon($user, $userOpts, $couponsCampaign, $coupon_billing_uuid);
					break;
				case 'stripe' :
					$stripeCouponsHandler = new StripeCouponsHandler();
					$coupon_provider_uuid = $stripeCouponsHandler->doCreateCoupon($user, $userOpts, $couponsCampaign, $coupon_billing_uuid);
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
				case 'stripe' :
					$stripeCouponsHandler = new StripeCouponsHandler();
					$db_coupon = $stripeCouponsHandler->createDbCouponFromApiCouponUuid($user, $userOpts, $couponsCampaign, $coupon_billing_uuid, $coupon_provider_uuid);
					break;
				default :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			config::getLogger()->addInfo("coupon creating done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("coupon creating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("coupon creating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_coupon);
	}
	
}

?>