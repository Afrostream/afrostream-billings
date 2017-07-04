<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../internalCoupons/InternalCouponsHandler.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponRequest.php';
require_once __DIR__ . '/../providers/global/requests/ExpireInternalCouponRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponsRequest.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class InternalCouponsController extends BillingsController {
	
	public function get(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$getInternalCouponRequest = new GetInternalCouponRequest();
			$getInternalCouponRequest->setOrigin('api');
			if(isset($args['internalCouponBillingUuid'])) {
				$getInternalCouponRequest->setInternalCouponBillingUuid($args['internalCouponBillingUuid']);
			}
			if(isset($data['couponCode'])) {
				$getInternalCouponRequest->setCouponCode($data['couponCode']);
			}
			$internalCouponsHandler = new InternalCouponsHandler();
			$coupon = $internalCouponsHandler->doGetInternalCoupon($getInternalCouponRequest);
			if($coupon == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'coupon', $coupon));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting an internal coupon, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an internal coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function expire(Request $request, Response $response, array $args) {
		try {
			$expireInternalCouponRequest = new ExpireInternalCouponRequest();
			$expireInternalCouponRequest->setOrigin('api');
			if(!isset($args['internalCouponBillingUuid'])) {
				//exception
				$msg = "field 'internalCouponBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$expireInternalCouponRequest->setInternalCouponBillingUuid($args['internalCouponBillingUuid']);
			$internalCouponsHandler = new InternalCouponsHandler();
			$coupon = $internalCouponsHandler->doExpireInternalCoupon($expireInternalCouponRequest);
			if($coupon == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'coupon', $coupon));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while expiring an InternalCoupon, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring an InternalCoupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function getList(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$getInternalCouponsRequest = new GetInternalCouponsRequest();
			$getInternalCouponsRequest->setOrigin('api');
			if(!isset($data['internalCouponsCampaignBillingUuid'])) {
				//exception
				$msg = "field 'internalCouponsCampaignBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(isset($data['offset'])) {
				$getInternalCouponsRequest->setOffset($data['offset']);
			}
			if(isset($data['limit'])) {
				$getInternalCouponsRequest->setLimit($data['limit']);
			}
			if(isset($data['isExport'])) {
				if($data['isExport'] == 'true') {
					$getInternalCouponsRequest->setIsExport(true);
					$getInternalCouponsRequest->setFilepath(tempnam('', 'tmp'));
				}
			}
			$getInternalCouponsRequest->setInternalCouponsCampaignBillingUuid($data['internalCouponsCampaignBillingUuid']);
			$internalCouponsHandler = new InternalCouponsHandler();
			if($getInternalCouponsRequest->getIsExport()) {
				$result = $internalCouponsHandler->doGetListInFile($getInternalCouponsRequest);
				return($this->returnFile($response, $result['filepath'], $result['filename'], $result['Content-Type']));
			} else {
				$listCoupons = $internalCouponsHandler->doGetList($getInternalCouponsRequest);
				return $this->returnObjectAsJson($response, NULL, $listCoupons);
			}
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting internalCoupons, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}

?>