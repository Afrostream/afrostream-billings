<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../utils/utils.php';
require_once __DIR__ . '/../partners/global/PartnerHandlersBuilder.php';

class OrdersHandler {
	
	public function __construct() {
	}
	
	public function doGetOnePartnerOrder(GetPartnerOrderRequest $getPartnerOrderRequest) {
		$billingPartnerOrder = NULL;
		try {
			config::getLogger()->addInfo("getting a partnerOrder...");
			$billingPartnerOrder = BillingPartnerOrderDAO::getBillingPartnerOrderByPartnerOrderUuid($getPartnerOrderRequest->getPartnerOrderBillingUuid());
			config::getLogger()->addInfo("getting a partnerOrder done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("getting a partnerOrder failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("getting a partnerOrder failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($billingPartnerOrder);
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
	
	public function doAddInternalCouponsCampaignToPartnerOrder(AddInternalCouponsCampaignToPartnerOrderRequest $addInternalCouponsCampaignToPartnerOrderRequest) {
		$billingPartnerOrder = NULL;
		try {
			config::getLogger()->addInfo("adding an internalCouponsCampaign to a partnerOrder...");
			$billingPartnerOrder = BillingPartnerOrderDAO::getBillingPartnerOrderByPartnerOrderUuid($addInternalCouponsCampaignToPartnerOrderRequest->getPartnerOrderBillingUuid());
			if($billingPartnerOrder == NULL) {
				$msg = "unknown partnerOrderBillingUuid : ".$addInternalCouponsCampaignToPartnerOrderRequest->getPartnerOrderBillingUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($addInternalCouponsCampaignToPartnerOrderRequest->getInternalCouponsCampaignBillingUuid());
			if($internalCouponsCampaign == NULL) {
				$msg = "unknown internalCouponsCampaignBillingUuid : ".$addInternalCouponsCampaignToPartnerOrderRequest->getInternalCouponsCampaignBillingUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);				
			}
			$partner = BillingPartnerDAO::getPartnerById($billingPartnerOrder->getPartnerId());
			if($partner == NULL) {
				$msg = "unknown partner with id : ".$billingPartnerOrder->getPartnerId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($billingPartnerOrder->getProcessingStatus() != 'waiting') {
				$msg = "partnerOrder processingStatus : ".$billingPartnerOrder->getProcessingStatus().", only 'waiting' status supported for this action";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);				
			}
			$partnerOrdersHandlerInstance = PartnerHandlersBuilder::getPartnerOrdersHandlerInstance($partner);
			$billingPartnerOrder = $partnerOrdersHandlerInstance->doAddInternalCouponsCampaignToPartnerOrder($billingPartnerOrder, 
					$internalCouponsCampaign, 
					$addInternalCouponsCampaignToPartnerOrderRequest);
			config::getLogger()->addInfo("adding an internalCouponsCampaign to a partnerOrder done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while adding an internalCouponsCampaign to a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an internalCouponsCampaign to a partnerOrder failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while adding an internalCouponsCampaign to a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an internalCouponsCampaign to a partnerOrder failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($billingPartnerOrder);
	}
	
	public function doBookPartnerOrder(BookPartnerOrderRequest $bookPartnerOrderRequest) {
		$billingPartnerOrder = NULL;
		try {
			config::getLogger()->addInfo("booking a partnerOrder...");
			$billingPartnerOrder = BillingPartnerOrderDAO::getBillingPartnerOrderByPartnerOrderUuid($bookPartnerOrderRequest->getPartnerOrderBillingUuid());
			if($billingPartnerOrder == NULL) {
				$msg = "unknown partnerOrderBillingUuid : ".$bookPartnerOrderRequest->getPartnerOrderBillingUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$partner = BillingPartnerDAO::getPartnerById($billingPartnerOrder->getPartnerId());
			if($partner == NULL) {
				$msg = "unknown partner with id : ".$billingPartnerOrder->getPartnerId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($billingPartnerOrder->getProcessingStatus() != 'waiting') {
				$msg = "partnerOrder processingStatus : ".$billingPartnerOrder->getProcessingStatus().", only 'waiting' status supported for this action";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$partnerOrdersHandlerInstance = PartnerHandlersBuilder::getPartnerOrdersHandlerInstance($partner);
			$billingPartnerOrder = $partnerOrdersHandlerInstance->doBookPartnerOrder($billingPartnerOrder, 
					$bookPartnerOrderRequest);
			config::getLogger()->addInfo("booking a partnerOrder done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while booking a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("booking a partnerOrder failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while booking a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("booking a partnerOrder failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($billingPartnerOrder);
	}
	
	public function doProcessPartnerOrder(ProcessPartnerOrderRequest $processPartnerOrderRequest) {
		$billingPartnerOrder = NULL;
		try {
			config::getLogger()->addInfo("processing a partnerOrder...");
			$billingPartnerOrder = BillingPartnerOrderDAO::getBillingPartnerOrderByPartnerOrderUuid($processPartnerOrderRequest->getPartnerOrderBillingUuid());
			if($billingPartnerOrder == NULL) {
				$msg = "unknown partnerOrderBillingUuid : ".$processPartnerOrderRequest->getPartnerOrderBillingUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$partner = BillingPartnerDAO::getPartnerById($billingPartnerOrder->getPartnerId());
			if($partner == NULL) {
				$msg = "unknown partner with id : ".$billingPartnerOrder->getPartnerId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($billingPartnerOrder->getProcessingStatus() != 'waiting') {
				$msg = "partnerOrder processingStatus : ".$billingPartnerOrder->getProcessingStatus().", only 'waiting' status supported for this action";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$partnerOrdersHandlerInstance = PartnerHandlersBuilder::getPartnerOrdersHandlerInstance($partner);
			$billingPartnerOrder = $partnerOrdersHandlerInstance->doProcessPartnerOrder($billingPartnerOrder,
					$processPartnerOrderRequest);
			config::getLogger()->addInfo("processing a partnerOrder done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while processing a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("processing a partnerOrder failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing a partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("processing a partnerOrder failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($billingPartnerOrder);
	}
	
}

?>