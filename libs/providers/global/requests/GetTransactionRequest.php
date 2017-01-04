<?php

require_once __DIR__ . '/ActionRequest.php';

class GetTransactionRequest extends ActionRequest {
	
	private $transactionBillingUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setTransactionBillingUuid($transactionBillingUuid) {
		$this->transactionBillingUuid = $transactionBillingUuid;
	}
	
	public function getTransactionBillingUuid() {
		return($this->transactionBillingUuid);
	}
	
}

?>