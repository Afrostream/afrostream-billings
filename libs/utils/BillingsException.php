<?php

 class ExceptionType extends SplEnum {
 	
 	const __default = self::internal;
 	
	const internal = 'internal';
	const provider = 'provider';
}

class BillingsException extends Exception {
	
	private $exceptionType = ExceptionType::internal;
		
	public function __construct(ExceptionType $exceptionType, $message = null, $code = null, $previous = null) {
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