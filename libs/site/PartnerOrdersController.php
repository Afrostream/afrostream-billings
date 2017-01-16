<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../orders/OrdersHandler.php';
require_once __DIR__ . '/../partners/global/requests/CreatePartnerOrderRequest.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class PartnerOrdersController extends BillingsController {

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
	
}

?>