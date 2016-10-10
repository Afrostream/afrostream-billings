<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';

class InternalCouponsHandler {
	
	public function __construct() {
	}
	
	public function doGetCoupon($couponCode) {
		$db_coupon = NULL;
		try {
			config::getLogger()->addInfo("internal coupon getting, couponCode=".$couponCode."....");
			//
			$db_coupon = BillingInternalCouponDAO::getBillingInternalCouponByCode($couponCode);
			//
			config::getLogger()->addInfo("internal coupon getting couponCode=".$couponCode." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting an internal coupon for couponCode=".$couponCode.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internal coupon getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an internal coupon for couponCode=".$couponCode.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internal coupon getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_coupon);
	}
	
}

?>