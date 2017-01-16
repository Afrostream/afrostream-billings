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
	
}

?>