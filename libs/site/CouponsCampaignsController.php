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
			$couponsCampaignsHandler = new CouponsCampaignsHandler();
			$couponsCampaigns = $couponsCampaignsHandler->doGetCouponsCampaigns($provider_name);
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
	
}

?>