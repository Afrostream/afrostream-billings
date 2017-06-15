<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponRequest.php';

class InternalCouponsHandler {
	
	public function __construct() {
	}
	
	public function doGetInternalCoupon(GetInternalCouponRequest $getInternalCouponRequest) {
		$internal_coupon = NULL;
		try {
			config::getLogger()->addInfo("internalCoupon getting....");
			//
			if($getInternalCouponRequest->getInternalCouponBillingUuid() != NULL) {
				$internal_coupon = BillingInternalCouponDAO::getBillingInternalCouponByInternalCouponBillingUuid($getInternalCouponRequest->getInternalCouponBillingUuid(), $getInternalCouponRequest->getPlatform()->getId());
			} else if($getInternalCouponRequest->getCouponCode() != NULL) {
				$internal_coupon = BillingInternalCouponDAO::getBillingInternalCouponByCode($getInternalCouponRequest->getCouponCode(), $getInternalCouponRequest->getPlatform()->getId());
			} else {
				//Exception
				$msg = "internalCouponBillingUuid OR couponCode must be given";
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			config::getLogger()->addInfo("internalCoupon getting done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting an internalCoupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCoupon getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an internalCoupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCoupon getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($internal_coupon);
	}
	
}

?>