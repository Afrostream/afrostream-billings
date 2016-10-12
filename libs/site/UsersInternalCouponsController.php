<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../usersInternalCoupons/UsersInternalCouponsHandler.php';
require_once __DIR__ .'/BillingsController.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class UsersInternalCouponsController extends BillingsController {

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
			//for backward compatibility - to be removed later -
			$couponsCampaignInternalBillingUuid = empty($data['couponsCampaignBillingUuid']) ? null : $data['couponsCampaignBillingUuid'];
			if(is_null($couponsCampaignInternalBillingUuid)) {
				$couponsCampaignInternalBillingUuid = empty($data['couponsCampaignInternalBillingUuid']) ? null : $data['couponsCampaignInternalBillingUuid'];
			}
			
			$couponsCampaignType = empty($data['couponsCampaignType']) ? null : $data['couponsCampaignType'];
			
			$usersInternalCouponsHandler = new UsersInternalCouponsHandler();
			$listCoupons = $usersInternalCouponsHandler->doGetList($userBillingUuid, $couponsCampaignType, $couponsCampaignInternalBillingUuid);
	
			return $this->returnObjectAsJson($response, 'coupons', $listCoupons);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting internal coupons, error_code=".$e->getCode().", error_message=".$e->getMessage();
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
			$usersInternalCouponsHandler = new UsersInternalCouponsHandler();
			$coupon = $usersInternalCouponsHandler->doCreateCoupon($userBillingUuid, $couponsCampaignBillingUuid, NULL /* no internalPlanUuid given for the moment */, $couponOpts);
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