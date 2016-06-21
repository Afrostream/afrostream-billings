<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';

class AfrCouponsHandler {
	
	public function __construct() {
	}
		
	public function doGetCoupon(User $user = NULL, UserOpts $userOpts = NULL, $couponCode) {
		$db_coupon = NULL;
		try {
			if(isset($user)) {
				$msg = "unsupported feature for provider named : afr, user has NOT to be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);				
			}
			//provider
			$provider = ProviderDAO::getProviderByName('afr');
			if($provider == NULL) {
				$msg = "provider named 'afr' not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_coupon = CouponDAO::getCoupon($provider->getId(), $couponCode);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting a afr coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr coupon getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a afr coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr coupon getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($db_coupon);
	}

	public function doCreateCoupon(User $user, UserOpts $userOpts, CouponsCampaign $couponsCampaign, $couponBillingUuid)
	{
		$coupon = new Coupon();
		$coupon->setCouponBillingUuid($couponBillingUuid);
		$coupon->setCouponsCampaignId($couponsCampaign->getId());
		$coupon->setProviderId($couponsCampaign->getProviderId());
		$coupon->setProviderPlanId($couponsCampaign->getProviderPlanId());
		$coupon->setCode($couponsCampaign->getPrefix()."-".$this->getRandomString($couponsCampaign->getGeneratedCodeLength()));

		CouponDAO::addCoupon($coupon);

		return $coupon->getCode();
	}

	public function createDbCouponFromApiCouponUuid(User $user,  UserOpts $userOpts, CouponsCampaign $couponsCampaign, $coupon_billing_uuid, $coupon_provider_uuid) {
		return CouponDAO::getCouponByCouponBillingUuid($coupon_billing_uuid);
	}

	protected function getRandomString($length) {
		$strAlphaNumericString = '23456789bcdfghjkmnpqrstvwxz';
		$strlength             = strlen($strAlphaNumericString) -1 ;
		$strReturnString       = '';

		for ($intCounter = 0; $intCounter < $length; $intCounter++) {
			$strReturnString .= $strAlphaNumericString[rand(0, $strlength)];
		}

		return $strReturnString;
	}
}

?>