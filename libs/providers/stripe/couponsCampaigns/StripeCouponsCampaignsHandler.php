<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../global/couponsCampaigns/ProviderCouponsCampaignsHandler.php';

class StripeCouponsCampaignsHandler extends ProviderCouponsCampaignsHandler {
	
	public function __construct(Provider $provider)
	{
		parent::__construct($provider);
		\Stripe\Stripe::setApiKey($this->provider->getApiSecret());
	}
	
	public function createProviderCouponsCampaign(BillingInternalCouponsCampaign $billingInternalCouponsCampaign) {
		$couponsCampaignProviderBillingUuid = NULL;
		try {
			config::getLogger()->addInfo("stripe couponsCampaign creation...");
			//Verifications ...
			$supported_coupon_types = [
					new CouponCampaignType(CouponCampaignType::promo)
			];
			if(!in_array($billingInternalCouponsCampaign->getCouponType(), $supported_coupon_types)) {
				$msg = "unsupported couponsCampaignType : ".$billingInternalCouponsCampaign->getCouponType()->getValue()." by provider named : ".$this->provider->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//Verifications OK
			$couponData = array();
			//
			$couponData['id'] = guid();
			//
			switch($billingInternalCouponsCampaign->getDiscountType()) {
				case 'amount' :
					$couponData['amount_off'] = $billingInternalCouponsCampaign->getAmountInCents();
					$couponData['currency'] = $billingInternalCouponsCampaign->getCurrency();
					break;
				case 'percent':
					$couponData['percent_off'] = $billingInternalCouponsCampaign->getPercent();
					break;
				default :
					$msg = "unsupported discount_type=".$billingInternalCouponsCampaign->getDiscountType();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			switch($billingInternalCouponsCampaign->getDiscountDuration()) {
				case 'once' :
					$couponData['duration'] = 'once';
					break;
				case 'forever' :
					$couponData['duration'] = 'forever';
					break;
				case 'repeating' :
					$couponData['duration'] = 'repeating';
					$duration_in_months = NULL;
					switch($billingInternalCouponsCampaign->getDiscountDurationUnit()) {
						case 'month' :
							$duration_in_months = 1 * $billingInternalCouponsCampaign->getDiscountDurationLength();
							break;
						case 'year' :
							$duration_in_months = 12 * $billingInternalCouponsCampaign->getDiscountDurationLength();
							break;
						default :
							$msg = "unsupported discount_duration_unit=".$billingInternalCouponsCampaign->getDiscountDurationUnit();
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							break;
					}
					$couponData['duration_in_months'] = $duration_in_months;
					break;
				default :
					$msg = "unsupported discount_duration=".$billingInternalCouponsCampaign->getDiscountDuration();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			$stripe_coupon = \Stripe\Coupon::create($couponData);
			$couponsCampaignProviderBillingUuid = $stripe_coupon['id'];
			config::getLogger()->addInfo("stripe couponsCampaign done successfully, stripe_coupon_campaign_uuid=".$couponsCampaignProviderBillingUuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a stripe couponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("stripe couponsCampaign creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a stripe couponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("stripe couponsCampaign creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($couponsCampaignProviderBillingUuid);
	}
	
}

?>