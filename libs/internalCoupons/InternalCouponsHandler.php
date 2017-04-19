<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponRequest.php';

class InternalCouponsHandler {
	
	public function __construct() {
	}
	
	public function doGetInternalCoupon(GetInternalCouponRequest $getInternalCouponRequest) {
		$code = $getInternalCouponRequest->getCouponCode();
		$internal_coupon = NULL;
		try {
			config::getLogger()->addInfo("internalCoupon getting, code=".$code."....");
			//
			$internal_coupon = BillingInternalCouponDAO::getBillingInternalCouponByCode($code, $getInternalCouponRequest->getPlatform()->getId());
			//
			config::getLogger()->addInfo("internalCoupon getting code=".$code." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting an internalCoupon for code=".$code.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCoupon getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an internalCoupon for code=".code.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCoupon getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($internal_coupon);
	}
	
}

?>