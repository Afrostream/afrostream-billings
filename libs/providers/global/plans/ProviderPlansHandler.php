<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class ProviderPlansHandler {
	
	protected $provider = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
	}
	
	public function createProviderPlan(InternalPlan $internalPlan) {
		$msg = "unsupported feature - create plan - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
		
}

?>