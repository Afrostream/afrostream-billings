<?php

require_once __DIR__ . '/../../global/orders/PartnerOrdersHandler.php';

class LogistaOrdersHandler extends PartnerOrdersHandler {
		
	public function doCreatePartnerOrder(CreatePartnerOrderRequest $createPartnerOrderRequest) {
		if(!in_array($createPartnerOrderRequest->getPartnerOrderType(), ['coupons'])) {
			throw new BillingsException(new ExceptionType(ExceptionType::internal), "type : ".$createPartnerOrderRequest->getPartnerOrderType()." is not supported");
		}
		return(parent::doCreatePartnerOrder($createPartnerOrderRequest));
	}
}

?>