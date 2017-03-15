<?php

require_once __DIR__ . '/../../db/dbGlobal.php';
require_once __DIR__ . '/../logista/orders/LogistaOrdersHandler.php';
		
class PartnerHandlersBuilder {
	
	public static function getPartnerOrdersHandlerInstance(BillingPartner $partner) {
		$partnerOrdersHandlerInstance = NULL;
		switch($partner->getName()) {
			case 'logista' :
				$partnerOrdersHandlerInstance = new LogistaOrdersHandler($partner);
				break;
			default:
				$msg = "unsupported feature for partner named : ".$partner->getName();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
				break;
		}
		return($partnerOrdersHandlerInstance);
	}
	
}

?>