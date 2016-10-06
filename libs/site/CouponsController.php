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
			$userBillingUuid = NULL;
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
			if(isset($data['userBillingUuid'])) {
				$userBillingUuid = $data['userBillingUuid'];
			}
			$providerName = $data['providerName'];
			$couponCode = $data['couponCode'];
			//
			$couponsHandler = new CouponsHandler();
			$coupon = $couponsHandler->doGetCoupon($providerName, $couponCode, $userBillingUuid);
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

	public function getList(Request $request, Response $response, array $args)
	{
		try {
			$data = $request->getQueryParams();
	
			$userBillingUuid = null;
	
			if (empty($data['userBillingUuid'])) {
				$msg = "'userBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			
			$userBillingUuid = $data['userBillingUuid'];
			
			$couponsCampaignBillingUuid = empty($data['couponsCampaignBillingUuid']) ? null : $data['couponsCampaignBillingUuid'];
	
			$couponsCampaignType = empty($data['couponsCampaignType']) ? null : $data['couponsCampaignType'];
			
			$recipientIsFilled = empty($data['recipientIsFilled']) ? true : ($data['recipientIsFilled'] == 'true' ? true : false);
			
			$couponsHandler = new CouponsHandler();
			$listCoupons = $couponsHandler->doGetList($userBillingUuid, $couponsCampaignType, $couponsCampaignBillingUuid, $recipientIsFilled);
	
			return $this->returnObjectAsJson($response, 'coupons', $listCoupons);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting coupons, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function create(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			$userBillingUuid = NULL;
			$couponsCampaignBillingUuid = NULL;
			if(!isset($data['userBillingUuid'])) {
				//exception
				$msg = "field 'userBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!isset($data['couponsCampaignBillingUuid'])) {
				//exception
				$msg = "field 'couponsCampaignBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}

			$couponOpts = array();
			if (isset($data['couponOpts'])) {
				if(!is_array($data['couponOpts'])) {
					//exception
					$msg = "field 'couponOpts' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}

				$couponOpts = $data['couponOpts'];
			}

			$userBillingUuid = $data['userBillingUuid'];
			$couponsCampaignBillingUuid = $data['couponsCampaignBillingUuid'];
			//
			$couponsHandler = new CouponsHandler();
			$coupon = $couponsHandler->doCreateCoupon($userBillingUuid, $couponsCampaignBillingUuid, $couponOpts);
			return($this->returnObjectAsJson($response, 'coupon', $coupon));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while creating a coupon, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}

?>