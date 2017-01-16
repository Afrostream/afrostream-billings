<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class RefundTransactionRequest extends ActionRequest {
	
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