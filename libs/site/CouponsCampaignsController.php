<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../couponsCampaigns/CouponsCampaignsHandler.php';
require_once __DIR__ .'/BillingsController.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class CouponsCampaignsController extends BillingsController {
	
	public function getMulti(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$provider_name = NULL;
			if(isset($data['providerName'])) {
				$provider_name = $data['providerName'];
			}
			$couponsCampaignType = NULL;
			if(isset($data['couponsCampaignType'])) {
				$couponsCampaignType = $data['couponsCampaignType'];
			}
			$couponsCampaignsHandler = new CouponsCampaignsHandler();
			$couponsCampaigns = $couponsCampaignsHandler->doGetCouponsCampaigns($provider_name, $couponsCampaignType);
			return($this->returnObjectAsJson($response, 'couponsCampaigns', $couponsCampaigns));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting CouponsCampaigns, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting CouponsCampaigns, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function getOne(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$couponsCampaignBillingUuid = NULL;
			if(!isset($args['couponsCampaignBillingUuid'])) {
				//exception
				$msg = "field 'couponsCampaignBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$couponsCampaignBillingUuid = $args['couponsCampaignBillingUuid'];
			$couponsCampaignsHandler = new CouponsCampaignsHandler();
			$couponsCampaign = $couponsCampaignsHandler->doGetCouponsCampaign($couponsCampaignBillingUuid);
			
			if($couponsCampaign == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'couponsCampaign', $couponsCampaign));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting a CouponsCampaign, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a CouponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}

?>