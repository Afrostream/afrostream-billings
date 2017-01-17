<?php

require_once __DIR__ . '/../../global/orders/PartnerOrdersHandler.php';

class LogistaOrdersHandler extends PartnerOrdersHandler {
	
	public function doCreatePartnerOrder(CreatePartnerOrderRequest $createPartnerOrderRequest) {
		if(!in_array($createPartnerOrderRequest->getPartnerOrderType(), ['coupons'])) {
			throw new BillingsException(new ExceptionType(ExceptionType::internal), "type : ".$createPartnerOrderRequest->getPartnerOrderType()." is not supported");
		}
		return(parent::doCreatePartnerOrder($createPartnerOrderRequest));
	}
	
	public function doAddInternalCouponsCampaignToPartnerOrder(BillingPartnerOrder $billingPartnerOrder,
			BillingInternalCouponsCampaign $billingInternalCouponsCampaign,
			AddInternalCouponsCampaignToPartnerOrderRequest $addInternalCouponsCampaignToPartnerOrderRequest) {
		$billingPartnerOrderInternalCouponsCampaignLinks = BillingPartnerOrderInternalCouponsCampaignLinkDAO::getBillingPartnerOrderInternalCouponsCampaignLinksByPartnerOrderId($billingPartnerOrder->getId());
		if(count($billingPartnerOrderInternalCouponsCampaignLinks) > 0) {
			throw new BillingsException(new ExceptionType(ExceptionType::internal), "an internalCouponsCampaign is already linked to the partnerOrder");
		}
		return(parent::doAddInternalCouponsCampaignToPartnerOrder($billingPartnerOrder, $billingInternalCouponsCampaign, $addInternalCouponsCampaignToPartnerOrderRequest));
	}
	
	public function doBookPartnerOrder(BillingPartnerOrder $billingPartnerOrder,
			BookPartnerOrderRequest $bookPartnerOrderRequest) {
		return(parent::doBookPartnerOrder($billingPartnerOrder, $bookPartnerOrderRequest));
	}
	
	public function doProcessPartnerOrder(BillingPartnerOrder $billingPartnerOrder,
			ProcessPartnerOrderRequest $processPartnerOrderRequest) {
		try {
		 	config::getLogger()->addInfo("processing a ".$this->partner->getName()." partnerOrder...");
		 	//TODO
		 	$billingPartnerOrder = BillingPartnerOrderDAO::getBillingPartnerOrderById($billingPartnerOrder->getId());
		 	config::getLogger()->addInfo("processing a ".$this->partner->getName()." partnerOrder done successfully");
		 } catch(BillingsException $e) {
			$msg = "a billings exception occurred while processing a ".$this->partner->getName()." partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("processing a ".$this->partner->getName()."partnerOrder failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing a ".$this->partner->getName()." partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("processing a ".$this->partner->getName()." partnerOrder failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($billingPartnerOrder);
	}
	
}

?>