<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../usersInternalCoupons/UsersInternalCouponsHandler.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../providers/global/requests/GetUsersInternalCouponsRequest.php';
require_once __DIR__ . '/../providers/global/requests/CreateUsersInternalCouponRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetUsersInternalCouponRequest.php';
require_once __DIR__ . '/../providers/global/requests/ExpireUsersInternalCouponRequest.php';

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
			$internalCouponsCampaignBillingUuid = empty($data['couponsCampaignBillingUuid']) ? null : $data['couponsCampaignBillingUuid'];
			if(is_null($internalCouponsCampaignBillingUuid)) {
				$internalCouponsCampaignBillingUuid = empty($data['internalCouponsCampaignBillingUuid']) ? null : $data['internalCouponsCampaignBillingUuid'];
			}
			
			$couponsCampaignType = empty($data['couponsCampaignType']) ? null : $data['couponsCampaignType'];
			
			$recipientIsFilled = empty($data['recipientIsFilled']) ? true : ($data['recipientIsFilled'] == 'true' ? true : false);
			
			$usersInternalCouponsHandler = new UsersInternalCouponsHandler();
			$getUsersInternalCouponsRequest = new GetUsersInternalCouponsRequest();
			$getUsersInternalCouponsRequest->setUserBillingUuid($userBillingUuid);
			$getUsersInternalCouponsRequest->setCouponsCampaignType($couponsCampaignType);
			$getUsersInternalCouponsRequest->setInternalCouponsCampaignBillingUuid($internalCouponsCampaignBillingUuid);
			$getUsersInternalCouponsRequest->setRecipientIsFilled($recipientIsFilled);
			$getUsersInternalCouponsRequest->setOrigin('api');
			$listCoupons = $usersInternalCouponsHandler->doGetList($getUsersInternalCouponsRequest);
	
			return $this->returnObjectAsJson($response, 'coupons', $listCoupons);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting userInternalCoupons, error_code=".$e->getCode().", error_message=".$e->getMessage();
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
			$internalCouponsCampaignBillingUuid = NULL;
			if(!isset($data['userBillingUuid'])) {
				//exception
				$msg = "field 'userBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalCouponsCampaignBillingUuid = empty($data['couponsCampaignBillingUuid']) ? null : $data['couponsCampaignBillingUuid'];
			if(is_null($internalCouponsCampaignBillingUuid)) {
				$internalCouponsCampaignBillingUuid = empty($data['internalCouponsCampaignBillingUuid']) ? null : $data['internalCouponsCampaignBillingUuid'];
			}
			if(is_null($internalCouponsCampaignBillingUuid)) {
				//exception
				$msg = "field 'internalCouponsCampaignBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}

			$couponOptsArray = array();
			if (isset($data['couponOpts'])) {
				if(!is_array($data['couponOpts'])) {
					//exception
					$msg = "field 'couponOpts' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}

				$couponOptsArray = $data['couponOpts'];
			}

			$userBillingUuid = $data['userBillingUuid'];
			//
			$usersInternalCouponsHandler = new UsersInternalCouponsHandler();
			$createUsersInternalCouponRequest = new CreateUsersInternalCouponRequest();
			$createUsersInternalCouponRequest->setUserBillingUuid($userBillingUuid);
			$createUsersInternalCouponRequest->setInternalCouponsCampaignBillingUuid($internalCouponsCampaignBillingUuid);
			$createUsersInternalCouponRequest->setInternalPlanUuid(NULL);/* no internalPlanUuid given for the moment */
			$createUsersInternalCouponRequest->setCouponOptsArray($couponOptsArray);
			$createUsersInternalCouponRequest->setOrigin('api');
			$coupon = $usersInternalCouponsHandler->doCreateCoupon($createUsersInternalCouponRequest);
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
	
	public function get(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$getUsersInternalCouponRequest = new GetUsersInternalCouponRequest();
			$getUsersInternalCouponRequest->setOrigin('api');
			if(!isset($args['internalUserCouponBillingUuid'])) {
				//exception
				$msg = "field 'internalUserCouponBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$getUsersInternalCouponRequest->setInternalUserCouponBillingUuid($args['internalUserCouponBillingUuid']);
			$usersInternalCouponsHandler = new UsersInternalCouponsHandler();
			$coupon = $usersInternalCouponsHandler->doGetUserInternalCoupon($getUsersInternalCouponRequest);
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
	
	public function expire(Request $request, Response $response, array $args) {
		try {
			$expireUsersInternalCouponRequest = new ExpireUsersInternalCouponRequest();
			$expireUsersInternalCouponRequest->setOrigin('api');
			if(!isset($args['internalUserCouponBillingUuid'])) {
				//exception
				$msg = "field 'internalUserCouponBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$expireUsersInternalCouponRequest->setInternalUserCouponBillingUuid($args['internalUserCouponBillingUuid']);
			$usersInternalCouponsHandler = new UsersInternalCouponsHandler();
			$coupon = $usersInternalCouponsHandler->doExpireUserInternalCoupon($expireUsersInternalCouponRequest);
			if($coupon == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'coupon', $coupon));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while expiring a coupon, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while expiring a coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}

?>