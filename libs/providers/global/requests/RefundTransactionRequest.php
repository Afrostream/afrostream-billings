<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class RefundTransactionRequest extends ActionRequest {
	
	protected $transactionBillingUuid = NULL;
	protected $amountInCents = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setTransactionBillingUuid($transactionBillingUuid) {
		$this->transactionBillingUuid = $transactionBillingUuid;
	}
	
	public function getTransactionBillingUuid() {
		return($this->transactionBillingUuid);
	}
	
	public function setAmountInCents($amountInCents) {
		$this->amountInCents = $amountInCents;
	}

	public function getAmountInCents() {
		return($this->amountInCents);
	}
	
}

?>