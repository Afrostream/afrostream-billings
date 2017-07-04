<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponRequest.php';
require_once __DIR__ . '/../providers/global/requests/ExpireInternalCouponRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponsRequest.php';

class InternalCouponsHandler {
	
	const LIMIT_MAX = 10000;
	
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
	
	public function doGetList(GetInternalCouponsRequest $getInternalCouponsRequest) {
		config::getLogger()->addInfo("internalCoupons list getting....");
		$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($getInternalCouponsRequest->getInternalCouponsCampaignBillingUuid(), $getInternalCouponsRequest->getPlatform()->getId());
		if($internalCouponsCampaign == NULL) {
			$msg = "unknown internalCouponsCampaign with internalCouponsCampaignBillingUuid : ".$getInternalCouponsRequest->getInternalCouponsCampaignBillingUuid();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$list = BillingInternalCouponDAO::getBillingInternalCouponsByInternalCouponsCampaignsId($internalCouponsCampaign->getId(), 
				NULL,
				$getInternalCouponsRequest->getLimit() == NULL ? 0 : $getInternalCouponsRequest->getLimit(),
				$getInternalCouponsRequest->getOffset() == NULL ? 0 : $getInternalCouponsRequest->getOffset());
		config::getLogger()->addInfo("internalCoupons list getting done successfully");
		return $list;
	}
	
	public function doGetListInFile(GetInternalCouponsRequest $getInternalCouponsRequest) {
		config::getLogger()->addInfo("internalCoupons list in file getting....");
		$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($getInternalCouponsRequest->getInternalCouponsCampaignBillingUuid(), $getInternalCouponsRequest->getPlatform()->getId());
		if($internalCouponsCampaign == NULL) {
			$msg = "unknown internalCouponsCampaign with internalCouponsCampaignBillingUuid : ".$getInternalCouponsRequest->getInternalCouponsCampaignBillingUuid();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//open file
		$current_csv_file_res = NULL;
		if(($current_csv_file_res = fopen($getInternalCouponsRequest->getFilepath(), 'w')) === false) {
			throw new Exception('csv file cannot be opened');
		}
		//header
		fputcsv($current_csv_file_res, BillingInternalCoupon::exportFields());
		//
		$limit = min(self::LIMIT_MAX, $getInternalCouponsRequest->getLimit() == NULL ? self::LIMIT_MAX : $getInternalCouponsRequest->getLimit());
		$offset = $getInternalCouponsRequest->getOffset() == NULL ? 0 : $getInternalCouponsRequest->getOffset();
		$index = 1;
		
		do {
			$result = BillingInternalCouponDAO::getBillingInternalCouponsByInternalCouponsCampaignsId($internalCouponsCampaign->getId(), NULL, $limit, $offset);
			$offset = $offset + $limit;
			if(is_null($totalHits)) {
				$totalHits = min($result['total_hits'], $getInternalCouponsRequest->getLimit() == NULL ? $result['total_hits'] : $getInternalCouponsRequest->getLimit());
			}
			$idx+= count($result['coupons']);
			//
			foreach($result['coupons'] as $coupon) {
				/* from OBJECT : optimal but not complete */
				$fields = $coupon->exportValues();
				/* from JSON : not optimal */
				/* 	
				$fields = array();
				$coupon_as_array = json_decode(json_encode($coupon, JSON_UNESCAPED_UNICODE), true);
				foreach ($coupon_as_array as $val) {
					if(is_scalar($val)) {
						$fields[] = $val;
					}
				}
				*/
				fputcsv($current_csv_file_res, $fields);
				//
				if($index == $totalHits) { break; }
				$index++;
				//
			}
		} while ($idx < $totalHits && count($result['coupons']) > 0);
		//close file
		fclose($current_csv_file_res);
		$current_csv_file_res = NULL;
		$out = array();
		$out['filepath'] = $getInternalCouponsRequest->getFilepath();
		$out['filename'] = 'export_internalCoupons_'.$getInternalCouponsRequest->getInternalCouponsCampaignBillingUuid().'.csv';
		$out['Content-Type'] = 'text/csv';
		config::getLogger()->addInfo("internalCoupons list in file getting done successfully");
		return($out);
	}
	
}

?>