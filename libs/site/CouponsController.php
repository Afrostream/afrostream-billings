<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../coupons/CouponsHandler.php';
require_once __DIR__ .'/BillingsController.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class CouponsController extends BillingsController {
	
	public function get(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$providerName = NULL;
			$couponCode = NULL;
			if(!isset($data['providerName'])) {
				//exception
				$msg = "field 'providerName' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!isset($data['couponCode'])) {
				//exception
				$msg = "field 'couponCode' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerName = $data['providerName'];
			$couponCode = $data['couponCode'];
			//
			$couponsHandler = new CouponsHandler();
			$coupon = $couponsHandler->doGetCoupon($providerName, $couponCode);
			if($coupon == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'coupon', $coupon));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting a coupon, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}

?>