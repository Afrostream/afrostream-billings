<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';

class BraintreeCouponsCampaignsHandler {
	
	public function __construct()
	{
	}
	
	public function createProviderCouponsCampaign(BillingInternalCouponsCampaign $billingInternalCouponsCampaign) {
		$couponsCampaignProviderBillingUuid = NULL;
		try {
			$couponsCampaignProviderBillingUuid = 'AfrBillingApiDiscount';
			//
			Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
			Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
			Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
			Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
			//Check Only
			$discount = $this->getDiscountByCouponCode(Braintree\Discount::all(), $couponsCampaignProviderBillingUuid);
			if($discount == NULL) {
				$msg = "mandatory discount with ID=AfrBillingApiDiscount NOT FOUND";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			config::getLogger()->addInfo("braintree couponsCampaign done successfully, braintree_coupon_campaign_uuid=".$couponsCampaignProviderBillingUuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a braintree couponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree couponsCampaign creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a braintree couponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("braintree couponsCampaign creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($couponsCampaignProviderBillingUuid);
	}
	
	private function getDiscountByCouponCode(array $discounts, $couponCode) {
		foreach ($discounts as $discount) {
			if($discount->id == $couponCode) {
				return($discount);
			}
		}
		return(NULL);
	}
	
}

?>