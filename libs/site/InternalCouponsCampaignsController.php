<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../internalCouponsCampaigns/InternalCouponsCampaignsHandler.php';
require_once __DIR__ .'/BillingsController.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class InternalCouponsCampaignsController extends BillingsController {
	
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
			$internalCouponsCampaignsHandler = new InternalCouponsCampaignsHandler();
			$internalCouponsCampaigns = $internalCouponsCampaignsHandler->doGetInternalCouponsCampaigns($couponsCampaignType);
			return($this->returnObjectAsJson($response, 'couponsCampaigns', $internalCouponsCampaigns));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting internalCouponsCampaigns, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting internalCouponsCampaigns, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function getOne(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$couponsCampaignInternalBillingUuid = NULL;
			if(!isset($args['couponsCampaignInternalBillingUuid'])) {
				//exception
				$msg = "field 'couponsCampaignInternalBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$couponsCampaignInternalBillingUuid = $args['couponsCampaignInternalBillingUuid'];
			$internalCouponsCampaignsHandler = new InternalCouponsCampaignsHandler();
			$internalCouponsCampaign = $internalCouponsCampaignsHandler->doGetInternalCouponsCampaign($couponsCampaignInternalBillingUuid);
			
			if($internalCouponsCampaign == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'couponsCampaign', $internalCouponsCampaign));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting an internalCouponsCampaign, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an internalCouponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}

?>