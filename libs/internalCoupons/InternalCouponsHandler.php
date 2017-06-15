<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponRequest.php';
require_once __DIR__ . '/../providers/global/requests/ExpireInternalCouponRequest.php';

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
	
	public function doExpireInternalCoupon(ExpireInternalCouponRequest $expireInternalCouponRequest) {
		$internal_coupon = NULL;
		try {
			config::getLogger()->addInfo("internalCoupon expiring....");
			$internal_coupon = BillingInternalCouponDAO::getBillingInternalCouponByInternalCouponBillingUuid($expireInternalCouponRequest->getInternalCouponBillingUuid(), $expireInternalCouponRequest->getPlatform()->getId());
			if($internal_coupon == NULL) {
				//exception
				$msg = "unknown coupon with internalCouponBillingUuid=".$expireInternalCouponRequest->getInternalCouponBillingUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//checking...
			if($internal_coupon->getStatus() == 'redeemed') {
				$msg = "coupon status is redeemed, it cannot be expired";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($internal_coupon->getStatus() == 'expired') {
				$msg = "coupon has already been expired";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($internal_coupon->getStatus() == 'pending') {
				$msg = "coupon status is pending, it cannot be expired";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($internal_coupon->getStatus() != 'waiting') {
				$msg = "ccoupon status is ".$internal_coupon->getStatus().", it cannot be expired";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//checking done
			$now = new DateTime();
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				$internal_coupon->setStatus('expired');
				$internal_coupon = BillingInternalCouponDAO::updateStatus($internal_coupon);
				$internal_coupon->setExpiresDate($now);
				$internal_coupon = BillingInternalCouponDAO::updateExpiresDate($internal_coupon);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			config::getLogger()->addInfo("internalCoupon expiring done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while expiring an internalCoupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCoupon expiring failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring an internalCoupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCoupon expiring failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($internal_coupon);
	}
	
}

?>