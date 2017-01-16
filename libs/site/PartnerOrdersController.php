<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../orders/OrdersHandler.php';
require_once __DIR__ . '/../partners/global/requests/CreatePartnerOrderRequest.php';
require_once __DIR__ . '/../partners/global/requests/GetPartnerOrderRequest.php';
require_once __DIR__ . '/../partners/global/requests/AddInternalCouponsCampaignToPartnerOrderRequest.php';
require_once __DIR__ . '/../partners/global/requests/BookPartnerOrderRequest.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class PartnerOrdersController extends BillingsController {

	public function getOne(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['partnerOrderBillingUuid'])) {
				//exception
				$msg = "field 'partnerOrderBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$partnerOrderBillingUuid = $args["partnerOrderBillingUuid"];
			//
			$ordersHandler = new OrdersHandler();
			$getPartnerOrderRequest = new GetPartnerOrderRequest();
			$getPartnerOrderRequest->setOrigin('api');
			$getPartnerOrderRequest->setPartnerOrderBillingUuid($partnerOrderBillingUuid);
			$partnerOrder = $ordersHandler->doGetOnePartnerOrder($getPartnerOrderRequest);
			return($this->returnObjectAsJson($response, 'partnerOrder', $partnerOrder));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting a partnerOrder, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function create(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			if(!isset($data['partnerName'])) {
				//exception
				$msg = "field 'partnerName' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$partnerName = $data['partnerName'];
			if(!isset($data['partnerOrderName'])) {
				//exception
				$msg = "field 'partnerOrderName' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$partnerOrderName = $data['partnerOrderName'];
			if(!isset($data['partnerOrderType'])) {
				//exception
				$msg = "field 'partnerOrderType' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$partnerOrderType = $data['partnerOrderType'];
			//
			$ordersHandler = new OrdersHandler();
			$createPartnerOrderRequest = new CreatePartnerOrderRequest();
			$createPartnerOrderRequest->setOrigin('api');
			$createPartnerOrderRequest->setPartnerName($partnerName);
			$createPartnerOrderRequest->setPartnerOrderType($partnerOrderType);
			$partnerOrder = $ordersHandler->doCreatePartnerOrder($createPartnerOrderRequest);
			return($this->returnObjectAsJson($response, 'partnerOrder', $partnerOrder));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while creating a partnerOrder, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function addInternalCouponsCampaignToPartnerOrder(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['partnerOrderBillingUuid'])) {
				//exception
				$msg = "field 'partnerOrderBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$partnerOrderBillingUuid = $args["partnerOrderBillingUuid"];
			if(!isset($args['internalCouponsCampaignBillingUuid'])) {
				//exception
				$msg = "field 'internalCouponsCampaignBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalCouponsCampaignBillingUuid = $args["internalCouponsCampaignBillingUuid"];
			if(!isset($args['wishedCouponsCounter'])) {
				//exception
				$msg = "field 'wishedCouponsCounter' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$wishedCouponsCounter = $args["wishedCouponsCounter"];			
			//
			$ordersHandler = new OrdersHandler();
			$addInternalCouponsCampaignToPartnerOrderRequest = new AddInternalCouponsCampaignToPartnerOrderRequest();
			$addInternalCouponsCampaignToPartnerOrderRequest->setOrigin('api');
			$addInternalCouponsCampaignToPartnerOrderRequest->setPartnerOrderBillingUuid($partnerOrderBillingUuid);
			$addInternalCouponsCampaignToPartnerOrderRequest->setInternalCouponsCampaignBillingUuid($internalCouponsCampaignBillingUuid);
			$addInternalCouponsCampaignToPartnerOrderRequest->setWishedCouponsCounter($wishedCouponsCounter);
			$partnerOrder = $ordersHandler->doAddInternalCouponsCampaignToPartnerOrder($addInternalCouponsCampaignToPartnerOrderRequest);
			return($this->returnObjectAsJson($response, 'partnerOrder', $partnerOrder));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while adding an internalCouponsCampaign to a partnerOrder, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while adding an internalCouponsCampaign to a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function book(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['partnerOrderBillingUuid'])) {
				//exception
				$msg = "field 'partnerOrderBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$partnerOrderBillingUuid = $args["partnerOrderBillingUuid"];
			//
			$ordersHandler = new OrdersHandler();
			$bookPartnerOrderRequest = new BookPartnerOrderRequest();
			$bookPartnerOrderRequest->setOrigin('api');
			$bookPartnerOrderRequest->setPartnerOrderBillingUuid($partnerOrderBillingUuid);
			$partnerOrder = $ordersHandler->doBookPartnerOrder($bookPartnerOrderRequest);
			return($this->returnObjectAsJson($response, 'partnerOrder', $partnerOrder));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while booking a partnerOrder, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while booking a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}

?>