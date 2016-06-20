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