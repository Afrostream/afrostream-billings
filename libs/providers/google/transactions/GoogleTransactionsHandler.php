<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/transactions/ProviderTransactionsHandler.php';

class GoogleTransactionsHandler extends ProviderTransactionsHandler {
	
	private $csvDelimiter = ',';
	private $csvEnclosure = '"';
	
	public function doImportTransactions(ImportTransactionsRequest $importTransactionsRequest) {
		config::getLogger()->addInfo("importing transactions...");
		$transactionsFileRes = NULL;
		if(($transactionsFileRes = fopen($importTransactionsRequest->getUploadedFile()->file, 'r')) === false) {
			throw new Exception("file cannot be opened for reading");
		}
		$lineNumber = 0;
		while(($fields = fgetcsv($transactionsFileRes, NULL, $this->csvDelimiter)) !== false) {
			//ignore first line (header)
			if($lineNumber > 0) {
				$this->doImportTransactionLine($fields);
			}
			//done
			$lineNumber++;
		}
		fclose($transactionsFileRes);
		$transactionsFileRes = NULL;
		config::getLogger()->addInfo("importing transactions done successfully");
	}
	
	/*
	//$fields[0] = Description
	//$fields[1] = Transaction Date
	//$fields[2] = Transaction Time
	//$fields[3] = Tax Type
	//$fields[4] = Transaction Type
	//$fields[5] = Refund Type
	//$fields[6] = Product Title
	//$fields[7] = Product id
	//$fields[8] = Product Type
	//$fields[9] = Sku Id
	//$fields[10] = Hardware
	//$fields[11] = Buyer Country
	//$fields[12] = Buyer State
	//$fields[13] = Buyer Postal Code
	//$fields[14] = Buyer Currency
	//$fields[15] = Amount (Buyer Currency)
	//$fields[16] = Currency Conversion Rate
	//$fields[17] = Merchant Currency
	//$fields[18] = Amount (Merchant Currency)
	*/
	
	protected function doImportTransactionLine(array $fields) {
		if(count($fields) < 19) {
			$msg = "line cannot be processed, it contains only ".count($fields)." fields, 19 minimum are expected";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$transactionType = $fields[4];
		switch($transactionType) {
			case 'Charge' :
				$this->doImportChargeTransactionLine($fields);
				break;
			case 'Charge refund' :
				$this->doImportRefundTransactionLine($fields);
				break;
			default :
				config::getLogger()->addInfo("transactionType=".$transactionType." ignored");
				break;
		}
	}
	
	protected function doImportChargeTransactionLine(array $fields) {
		$transactionType = $fields[4];
		if($transactionType != 'Charge') {
			$msg = "transactionType expected is Charge, but ".$transactionType;
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$transactionProviderUuid = $fields[0];
		$dbTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($this->provider->getId(), $transactionProviderUuid);
		if($dbTransaction == NULL) {
			$initialOrderId = $this->parseInitialOrderId($transactionProviderUuid);
			$dbSubscription = BillingsSubscriptionDAO::getBillingsSubscriptionByOptKeyValue($this->provider->getId(), 'orderId', $initialOrderId);
			if($dbSubscription == NULL) {
				//EXCEPTION
			} else {
				//CREATE
			}
		} else {
			//UPDATE
		}
	}
	
	protected function doImportRefundTransactionLine(array $fields) {
		$transactionType = $fields[4];
		if($transactionType != 'Charge refund') {
			$msg = "transactionType expected is Charge refund, but ".$transactionType;
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$transactionProviderUuid = $fields[0];
		$dbTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($this->provider->getId(), $transactionProviderUuid);
		if($dbTransaction == NULL) {
			//EXCEPTION (CHARGE MUST EXIST BEFORE)
		} else {
			//UPDATE
		}
	}
	
	//Sample Apr 1, 2017 1:12:32 AM PDT
	protected function parseDateTime($date, $time) {
	 	$datetime = DateTime::createFromFormat('M j, Y g:i:s A T', $date.' '.$time);
		if($datetime === false) {
			throw new Exception("date cannot be parsed : ".$date." ".$time);
		}
		return($datetime);
	}
	
	protected function parseInitialOrderId($transactionProviderUuid) {
		return(substr($transactionOrderId, 0, 24));
	}
	
}

?>