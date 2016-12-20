<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use MyCLabs\Enum\Enum;

class ExceptionType extends Enum {
	
 	const internal = 'internal';
	const provider = 'provider';
	
 }

class ExceptionError extends Enum {
	
	//
	const UNKNOWN_ERROR									=	0;
	//
	const COUPON_CODE_NOT_FOUND							=	50;
	//
	const SEPA_IBAN_INVALID								=	60;
	//CONTEXTS ERRORS
	const CONTEXT_NOT_FOUND								=	100;
	//CASHWAY ERRORS
	const CASHWAY_COUPON_ONE_BY_USER_FOR_EACH_CAMPAIGN	=	200;
	//ORANGE
	const ORANGE_SUBSCRIPTION_NOT_FOUND					=	300;
	const ORANGE_SUBSCRIPTION_BAD_STATUS				=	301;
	const ORANGE_CALL_API_INVALID_TOKEN					=	310;
	const ORANGE_CALL_API_MALFORMED_TOKEN				=	311;
	const ORANGE_CALL_API_EXPIRED_TOKEN					=	312;
	const ORANGE_CALL_API_INVALID_REQUEST				=	315;
	const ORANGE_CALL_API_UNKNOWN_ERROR					=	319;
	//Bouygues
	const BOUYGUES_CALL_API_UNKNOWN_ERROR				=	330;
	const BOUYGUES_CALL_API_SUBSCRIPTION_NOT_FOUND		=	331;
	const BOUYGUES_CALL_API_BAD_RESULT					=	335;
	const BOUYGUES_SUBSCRIPTION_BAD_STATUS				=	340;
	//Netsize
	const NETSIZE_INCOMPATIBLE							=	360;
	const NETSIZE_SUBSCRIPTION_BAD_STATUS				=	361;
	//AFR
	const AFR_COUPON_SPS_SELF_FORBIDDEN					=	400;
	const AFR_COUPON_SPS_RECIPIENT_ALREADY_SPONSORED	=	401;
	const AFR_COUPON_SPS_RECIPIENT_ACTIVE_FORBIDDEN		=	402;
	const AFR_SUB_SPS_RECIPIENT_DIFFER					=	410;
	const AFR_SUB_SPS_RECIPIENT_ALREADY_SPONSORED		=	411;
	//SUBS
	const SUBS_AUTO_ALREADY_EXISTS						=	420;
	const SUBS_FUTURE_ALREADY_EXISTS					=	421;
	const SUBS_ALREADY_EXISTS							=	422;
	//COUPONS
	const COUPON_REDEEMED								=	450;
	const COUPON_EXPIRED								=	451;
	const COUPON_PENDING								=	452;
	const COUPON_NOT_READY								=	453;
	const COUPON_PROVIDER_INCOMPATIBLE					=	454;
	const COUPON_INTERNALPLAN_INCOMPATIBLE				=	455;
	const COUPON_ALREADY_LINKED							=	456;
}

class BillingsException extends Exception {
	
	private $exceptionType = ExceptionType::internal;
		
	public function __construct(ExceptionType $exceptionType, $message = null, $code = ExceptionError::UNKNOWN_ERROR, $previous = null) {
		parent::__construct($message, $code, $previous);
		$this->setExceptionType($exceptionType);
	}
	
	public function getExceptionType() {
		return($this->exceptionType);
	}
	
	public function setExceptionType(ExceptionType $exceptionType) {
		$this->exceptionType = $exceptionType;
	}
}

?>