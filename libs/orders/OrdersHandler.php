<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../utils/utils.php';
require_once __DIR__ . '/../partners/global/PartnerHandlersBuilder.php';

class OrdersHandler {
	
	public function __construct() {
	}
	
	public function doCreatePartnerOrder(CreatePartnerOrderRequest $createPartnerOrderRequest) {
		$billingPartnerOrder = NULL;
		try {
			config::getLogger()->addInfo("creating a partnerOrder...");
			$partner = BillingPartnerDAO::getPartnerByName($createPartnerOrderRequest->getPartnerName());
			if($partner == NULL) {
				$msg = "unknown partner with name : ".$createPartnerOrderRequest->getPartnerName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$partnerOrdersHandlerInstance = PartnerHandlersBuilder::getPartnerOrdersHandlerInstance($partner);
			$billingPartnerOrder = $partnerOrdersHandlerInstance->doCreatePartnerOrder($createPartnerOrderRequest);
			config::getLogger()->addInfo("creating a partnerOrder done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("creating a partnerOrder failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("creating a partnerOrder failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($billingPartnerOrder);
	}
}

?>