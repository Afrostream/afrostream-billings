<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class StripeCouponsHandler
{
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