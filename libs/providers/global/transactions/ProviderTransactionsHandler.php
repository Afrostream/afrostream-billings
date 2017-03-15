<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../requests/RefundTransactionRequest.php';
require_once __DIR__ . '/../requests/UpdateTransactionRequest.php';

class ProviderTransactionsHandler {
	
	protected $provider = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
	}
	
	public function doRefundTransaction(BillingsTransaction $transaction, RefundTransactionRequest $refundTransactionRequest) {
		$msg = "unsupported feature - refund transaction - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	public function doUpdateTransactionsByUser(User $user, UserOpts $userOpts, DateTime $from = NULL, DateTime $to = NULL, $updateType) {
		$msg = "unsupported feature - update user transactions - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	public function doUpdateTransactionByTransactionProviderUuid(UpdateTransactionRequest $updateTransactionRequest) {
		$msg = "unsupported feature - update transaction - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
}

?>