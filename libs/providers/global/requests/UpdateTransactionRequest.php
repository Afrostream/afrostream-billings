<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class UpdateTransactionRequest extends ActionRequest {
	
	protected $providerName = NULL;
	protected $transactionProviderUuid = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setProviderName($providerName) {
		$this->providerName = $providerName;
	}
	
	public function getProviderName() {
		return($this->providerName);
	}
	
	public function setTransactionProviderUuid($transactionProviderUuid) {
		$this->transactionProviderUuid = $transactionProviderUuid;
	}
	
	public function getTransactionProviderUuid() {
		return($this->transactionProviderUuid);
	}
	
}

?>