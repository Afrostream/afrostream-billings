<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../internalCoupons/InternalCouponsHandler.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponRequest.php';

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
	
}

?>