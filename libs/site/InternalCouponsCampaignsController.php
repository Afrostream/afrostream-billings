<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../internalCouponsCampaigns/InternalCouponsCampaignsHandler.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponsCampaignRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponsCampaignsRequest.php';
require_once __DIR__ . '/../providers/global/requests/AddProviderToInternalCouponsCampaignRequest.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class InternalCouponsCampaignsController extends BillingsController {
	
	public function getMulti(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$couponsCampaignType = NULL;
			if(isset($data['couponsCampaignType'])) {
				$couponsCampaignType = $data['couponsCampaignType'];
			}
			$internalCouponsCampaignsHandler = new InternalCouponsCampaignsHandler();
			$getInternalCouponsCampaignsRequest = new GetInternalCouponsCampaignsRequest();
			$getInternalCouponsCampaignsRequest->setCouponsCampaignType($couponsCampaignType);
			$getInternalCouponsCampaignsRequest->setOrigin('api');
			$internalCouponsCampaigns = $internalCouponsCampaignsHandler->doGetInternalCouponsCampaigns($getInternalCouponsCampaignsRequest);
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
			$getInternalCouponsCampaignRequest = new GetInternalCouponsCampaignRequest();
			$getInternalCouponsCampaignRequest->setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid);
			$getInternalCouponsCampaignRequest->setOrigin('api');
			$internalCouponsCampaign = $internalCouponsCampaignsHandler->doGetInternalCouponsCampaign($getInternalCouponsCampaignRequest);
			
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
	
	public function addToProvider(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['couponsCampaignInternalBillingUuid'])) {
				//exception
				$msg = "field 'couponsCampaignInternalBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$couponsCampaignInternalBillingUuid = $args['couponsCampaignInternalBillingUuid'];
			if(!isset($args['providerName'])) {
				//exception
				$msg = "field 'providerName' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerName = $args['providerName'];
			//
			$internalCouponsCampaignsHandler = new InternalCouponsCampaignsHandler();
			$addProviderToInternalCouponsCampaignRequest = new AddProviderToInternalCouponsCampaignRequest();
			$addProviderToInternalCouponsCampaignRequest->setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid);
			$addProviderToInternalCouponsCampaignRequest->setProviderName($providerName);
			$addProviderToInternalCouponsCampaignRequest->setOrigin('api');
			$couponsCampaign = $internalCouponsCampaignsHandler->doAddToProvider($addProviderToInternalCouponsCampaignRequest);
			return($this->returnObjectAsJson($response, 'couponsCampaign', $couponsCampaign));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while linking an internalCouponsCampaign to a provider, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while linking an internalCouponsCampaign to a provider, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function create(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			//
			
		} catch(BillingsException $e) {
			$msg = "an exception occurred while creating an InternalCouponsCampaign, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating an InternalCouponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}

?>