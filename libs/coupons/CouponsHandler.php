<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';

class CouponsHandler {
	
	public function __construct() {
	}
	
	public function doGetCoupon($providerName, $couponCode) {
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
			$db_coupon = CouponDAO::getCoupon($provider->getId(), $couponCode);
			//
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
}
	
?>