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
		if(($transactionsFileRes = fopen($importTransactionsRequest->getUploadedFile(), 'r')) === false) {
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
	
	/** salesreports **/
	
	//$fields[0] = Order Number
	//$fields[1] = Order Charged Date
	//$fields[2] = Order Charged Timestamp
	//$fields[3] = Financial Status
	//$fields[4] = Device Model
	//$fields[5] = Product Title
	//$fields[6] = Product ID
	//$fields[7] = Product Type
	//$fields[8] = SKU ID
	//$fields[9] = Currency of Sale
	//$fields[10] = Item Price
	//$fields[11] = Taxes Collected
	//$fields[12] = Charged Amount
	//$fields[13] = City of Buyer
	//$fields[14] = State of Buyer
	//$fields[15] = Postal Code of Buyer
	//$fields[16] = Country of Buyer
	
	protected function doImportTransactionLine(array $fields) {
		try {
			if(count($fields) < 17) {
				$msg = "line cannot be processed, it contains only ".count($fields)." fields, 17 minimum are expected";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$financialStatus = $fields[3];
			switch($financialStatus) {
				case 'Charged' :
					$this->doImportChargeTransactionLine($fields);
					break;
				case 'Refund' :
					$this->doImportRefundTransactionLine($fields);
					break;
				default :
					config::getLogger()->addInfo("financialStatus=".$financialStatus." ignored");
					break;
			}
		} catch(Exception $e) {
			$msg = "an error occurred while processing a transaction line, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("processing a transaction line failed : ".$msg);
			//throw $e;
		}
	}
	
	protected function doImportChargeTransactionLine(array $fields) {
		config::getLogger()->addInfo("importing charge transaction line...");
		$financialStatus = $fields[3];
		if($financialStatus != 'Charged') {
			$msg = "financialStatus expected is Charge, but ".$financialStatus;
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$transactionProviderUuid = $fields[0];
		$transactionChargeProviderUuid = $transactionProviderUuid.".".BillingsTransactionType::purchase;
		$initialOrderId = $this->parseInitialOrderId($transactionProviderUuid);
		$dbSubscription = BillingsSubscriptionDAO::getBillingsSubscriptionByOptKeyValue($this->provider->getId(), 'orderId', $initialOrderId);
		if($dbSubscription == NULL) {
			//EXCEPTION
			throw new Exception("no subscription linked to this charge transaction with providerUuid=".$transactionChargeProviderUuid);
		}
		$billingsTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($this->provider->getId(), $transactionChargeProviderUuid);
		if($billingsTransaction == NULL) {
			//CREATE
			$billingsTransaction = new BillingsTransaction();
			$billingsTransaction->setProviderId($this->provider->getId());
			$billingsTransaction->setUserId($dbSubscription->getUserId());
			$billingsTransaction->setSubId($dbSubscription->getId());
			$billingsTransaction->setCouponId(NULL);
			$billingsTransaction->setInvoiceId(NULL);
			$billingsTransaction->setTransactionBillingUuid(guid());
			$billingsTransaction->setTransactionProviderUuid($transactionChargeProviderUuid);
			$billingsTransaction->setTransactionCreationDate((new DateTime())->setTimestamp($fields[2]));
			$billingsTransaction->setAmountInCents(intval(floatval($fields[12]) * 100));
			$billingsTransaction->setCurrency($fields[9]);
			$billingsTransaction->setCountry($fields[16]);
			$billingsTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::success));
			$billingsTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::purchase));
			$billingsTransaction->setInvoiceProviderUuid(NULL);
			$billingsTransaction->setMessage('');
			$billingsTransaction->setUpdateType('import');
			$billingsTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsTransaction->setPaymentMethodType(new BillingPaymentMethodType(BillingPaymentMethodType::googleplay));
			$billingsTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsTransaction);
		} else {
			//UPDATE
			$billingsTransaction->setProviderId($this->provider->getId());
			$billingsTransaction->setUserId($dbSubscription->getUserId());
			$billingsTransaction->setSubId($dbSubscription->getId());
			$billingsTransaction->setCouponId(NULL);
			$billingsTransaction->setInvoiceId(NULL);
			//NO !!! : $billingsTransaction->setTransactionBillingUuid(guid());
			$billingsTransaction->setTransactionProviderUuid($transactionChargeProviderUuid);
			$billingsTransaction->setTransactionCreationDate((new DateTime())->setTimestamp($fields[2]));
			$billingsTransaction->setAmountInCents(intval(floatval($fields[12]) * 100));
			$billingsTransaction->setCurrency($fields[9]);
			$billingsTransaction->setCountry($fields[16]);
			$billingsTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::success));
			$billingsTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::purchase));
			$billingsTransaction->setInvoiceProviderUuid(NULL);
			$billingsTransaction->setMessage('');
			$billingsTransaction->setUpdateType('import');
			//NO !!! : $billingsTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsTransaction->setPaymentMethodType(new BillingPaymentMethodType(BillingPaymentMethodType::googleplay));
			$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
		}
		config::getLogger()->addInfo("importing charge transaction line done successfully");
		return($billingsTransaction);
	}
	
	protected function doImportRefundTransactionLine(array $fields) {
		config::getLogger()->addInfo("importing refund transaction line...");
		$billingsRefundTransaction = NULL;
		$financialStatus = $fields[3];
		if($financialStatus != 'Refund') {
			$msg = "financialStatus expected is Refund, but ".$financialStatus;
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$transactionProviderUuid = $fields[0];
		$transactionRefundProviderUuid = $transactionProviderUuid.".".BillingsTransactionType::refund;
		$transactionChargeProviderUuid = $transactionProviderUuid.".".BillingsTransactionType::purchase;
		$billingsChargeTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($this->provider->getId(), $transactionChargeProviderUuid);
		if($billingsChargeTransaction == NULL) {
			//EXCEPTION (CHARGE MUST EXIST BEFORE)
			throw new Exception("no charge linked to this refund transaction with providerUuid=".$transactionRefundProviderUuid);
		}
		$billingsRefundTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($this->provider->getId(), $transactionRefundProviderUuid);
		if($billingsRefundTransaction == NULL) {
			//CREATE
			$billingsRefundTransaction = new BillingsTransaction();
			$billingsRefundTransaction->setTransactionLinkId($billingsChargeTransaction->getId());
			$billingsRefundTransaction->setProviderId($this->provider->getId());
			$billingsRefundTransaction->setUserId($billingsChargeTransaction->getUserId());
			$billingsRefundTransaction->setSubId($billingsChargeTransaction->getSubId());
			$billingsRefundTransaction->setCouponId(NULL);
			$billingsRefundTransaction->setInvoiceId(NULL);
			$billingsRefundTransaction->setTransactionBillingUuid(guid());
			$billingsRefundTransaction->setTransactionProviderUuid($transactionRefundProviderUuid);
			$billingsRefundTransaction->setTransactionCreationDate((new DateTime())->setTimestamp($fields[2]));
			$billingsRefundTransaction->setAmountInCents(abs(intval(floatval($fields[12]) * 100)));
			$billingsRefundTransaction->setCurrency($fields[9]);
			$billingsRefundTransaction->setCountry($fields[16]);
			$billingsRefundTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::success));
			$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
			$billingsRefundTransaction->setInvoiceProviderUuid(NULL);
			$billingsRefundTransaction->setMessage('');
			$billingsRefundTransaction->setUpdateType('import');
			$billingsRefundTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsRefundTransaction->setPaymentMethodType(new BillingPaymentMethodType(BillingPaymentMethodType::googleplay));
			$billingsRefundTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsRefundTransaction);
		} else {
			//UPDATE
			$billingsRefundTransaction->setTransactionLinkId($billingsChargeTransaction->getId());
			$billingsRefundTransaction->setProviderId($this->provider->getId());
			$billingsRefundTransaction->setUserId($billingsChargeTransaction->getUserId());
			$billingsRefundTransaction->setSubId($billingsChargeTransaction->getSubId());
			$billingsRefundTransaction->setCouponId(NULL);
			$billingsRefundTransaction->setInvoiceId(NULL);
			//NO !!! : $billingsTransaction->setTransactionBillingUuid(guid());
			$billingsRefundTransaction->setTransactionProviderUuid($transactionRefundProviderUuid);
			$billingsRefundTransaction->setTransactionCreationDate((new DateTime())->setTimestamp($fields[2]));
			$billingsRefundTransaction->setAmountInCents(abs(intval(floatval($fields[12]) * 100)));
			$billingsRefundTransaction->setCurrency($fields[9]);
			$billingsRefundTransaction->setCountry($fields[16]);
			$billingsRefundTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::success));
			$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
			$billingsRefundTransaction->setInvoiceProviderUuid(NULL);
			$billingsRefundTransaction->setMessage('');
			$billingsRefundTransaction->setUpdateType('import');
			//NO !!! : $billingsRefundTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsRefundTransaction->setPaymentMethodType(new BillingPaymentMethodType(BillingPaymentMethodType::googleplay));
			$billingsRefundTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsRefundTransaction);
		}
		config::getLogger()->addInfo("importing refund transaction line done successfully");
		return($billingsRefundTransaction);
	}
		
	/** *** earnings (abandoned) ***
	
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
		
	protected function doImportTransactionLine(array $fields) {
		try {
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
		} catch(Exception $e) {
			$msg = "an error occurred while processing a transaction line, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("processing a transaction line failed : ".$msg);
			//throw $e;
		}
	}
	
	protected function doImportChargeTransactionLine(array $fields) {
		config::getLogger()->addInfo("importing charge transaction line...");
		$billingsTransaction = NULL;
		$transactionType = $fields[4];
		if($transactionType != 'Charge') {
			$msg = "transactionType expected is Charge, but ".$transactionType;
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$transactionProviderUuid = $fields[0];
		$transactionChargeProviderUuid = $transactionProviderUuid.".".$transactionType;
		$initialOrderId = $this->parseInitialOrderId($transactionProviderUuid);
		$dbSubscription = BillingsSubscriptionDAO::getBillingsSubscriptionByOptKeyValue($this->provider->getId(), 'orderId', $initialOrderId);
		if($dbSubscription == NULL) {
			//EXCEPTION
			throw new Exception("no subscription linked to this charge transaction with providerUuid=".$transactionChargeProviderUuid);
		}
		$billingsTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($this->provider->getId(), $transactionChargeProviderUuid);
		if($billingsTransaction == NULL) {
			//CREATE
			$billingsTransaction = new BillingsTransaction();
			$billingsTransaction->setProviderId($this->provider->getId());
			$billingsTransaction->setUserId($dbSubscription->getUserId());
			$billingsTransaction->setSubId($dbSubscription->getId());
			$billingsTransaction->setCouponId(NULL);
			$billingsTransaction->setInvoiceId(NULL);
			$billingsTransaction->setTransactionBillingUuid(guid());
			$billingsTransaction->setTransactionProviderUuid($transactionChargeProviderUuid);
			$billingsTransaction->setTransactionCreationDate($this->parseDateTime($fields[1], $fields[2]));
			$billingsTransaction->setAmountInCents(intval(floatval($fields[18]) * 100));
			$billingsTransaction->setCurrency($fields[17]);
			$billingsTransaction->setCountry($fields[11]);
			$billingsTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::success));
			$billingsTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::purchase));
			$billingsTransaction->setInvoiceProviderUuid(NULL);
			$billingsTransaction->setMessage('');
			$billingsTransaction->setUpdateType('import');
			$billingsTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsTransaction->setPaymentMethodType(new BillingPaymentMethodType(BillingPaymentMethodType::googleplay));
			$billingsTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsTransaction);
		} else {
			//UPDATE
			$billingsTransaction->setProviderId($this->provider->getId());
			$billingsTransaction->setUserId($dbSubscription->getUserId());
			$billingsTransaction->setSubId($dbSubscription->getId());
			$billingsTransaction->setCouponId(NULL);
			$billingsTransaction->setInvoiceId(NULL);
			//NO !!! : $billingsTransaction->setTransactionBillingUuid(guid());
			$billingsTransaction->setTransactionProviderUuid($transactionChargeProviderUuid);
			$billingsTransaction->setTransactionCreationDate($this->parseDateTime($fields[1], $fields[2]));
			$billingsTransaction->setAmountInCents(intval(floatval($fields[18]) * 100));
			$billingsTransaction->setCurrency($fields[17]);
			$billingsTransaction->setCountry($fields[11]);
			$billingsTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::success));
			$billingsTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::purchase));
			$billingsTransaction->setInvoiceProviderUuid(NULL);
			$billingsTransaction->setMessage('');
			$billingsTransaction->setUpdateType('import');
			//NO !!! : $billingsTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsTransaction->setPaymentMethodType(new BillingPaymentMethodType(BillingPaymentMethodType::googleplay));
			$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
		}
		config::getLogger()->addInfo("importing charge transaction line done successfully");
		return($billingsTransaction);
	}
	
	protected function doImportRefundTransactionLine(array $fields) {
		config::getLogger()->addInfo("importing refund transaction line...");
		$billingsRefundTransaction = NULL;
		$transactionType = $fields[4];
		if($transactionType != 'Charge refund') {
			$msg = "transactionType expected is Charge refund, but ".$transactionType;
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$transactionProviderUuid = $fields[0];
		$transactionRefundProviderUuid = $transactionProviderUuid.".".$transactionType;
		$transactionChargeProviderUuid = $transactionProviderUuid."."."Charge";
		$billingsChargeTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($this->provider->getId(), $transactionChargeProviderUuid);
		if($billingsChargeTransaction == NULL) {
			//EXCEPTION (CHARGE MUST EXIST BEFORE)
			throw new Exception("no charge linked to this refund transaction with providerUuid=".$transactionRefundProviderUuid);
		}
		$billingsRefundTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($this->provider->getId(), $transactionRefundProviderUuid);
		if($billingsRefundTransaction == NULL) {
			//CREATE
			$billingsRefundTransaction = new BillingsTransaction();
			$billingsRefundTransaction->setTransactionLinkId($billingsChargeTransaction->getId());
			$billingsRefundTransaction->setProviderId($this->provider->getId());
			$billingsRefundTransaction->setUserId($billingsChargeTransaction->getUserId());
			$billingsRefundTransaction->setSubId($billingsChargeTransaction->getSubId());
			$billingsRefundTransaction->setCouponId(NULL);
			$billingsRefundTransaction->setInvoiceId(NULL);
			$billingsRefundTransaction->setTransactionBillingUuid(guid());
			$billingsRefundTransaction->setTransactionProviderUuid($transactionRefundProviderUuid);
			$billingsRefundTransaction->setTransactionCreationDate($this->parseDateTime($fields[1], $fields[2]));
			$billingsRefundTransaction->setAmountInCents(abs(intval(floatval($fields[18]) * 100)));
			$billingsRefundTransaction->setCurrency($fields[17]);
			$billingsRefundTransaction->setCountry($fields[11]);
			$billingsRefundTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::success));
			$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
			$billingsRefundTransaction->setInvoiceProviderUuid(NULL);
			$billingsRefundTransaction->setMessage('');
			$billingsRefundTransaction->setUpdateType('import');
			$billingsRefundTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsRefundTransaction->setPaymentMethodType(new BillingPaymentMethodType(BillingPaymentMethodType::googleplay));
			$billingsRefundTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsRefundTransaction);
		} else {
			//UPDATE
			$billingsRefundTransaction->setTransactionLinkId($billingsChargeTransaction->getId());
			$billingsRefundTransaction->setProviderId($this->provider->getId());
			$billingsRefundTransaction->setUserId($billingsChargeTransaction->getUserId());
			$billingsRefundTransaction->setSubId($billingsChargeTransaction->getSubId());
			$billingsRefundTransaction->setCouponId(NULL);
			$billingsRefundTransaction->setInvoiceId(NULL);
			//NO !!! : $billingsTransaction->setTransactionBillingUuid(guid());
			$billingsRefundTransaction->setTransactionProviderUuid($transactionRefundProviderUuid);
			$billingsRefundTransaction->setTransactionCreationDate($this->parseDateTime($fields[1], $fields[2]));
			$billingsRefundTransaction->setAmountInCents(abs(intval(floatval($fields[18]) * 100)));
			$billingsRefundTransaction->setCurrency($fields[17]);
			$billingsRefundTransaction->setCountry($fields[11]);
			$billingsRefundTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::success));
			$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
			$billingsRefundTransaction->setInvoiceProviderUuid(NULL);
			$billingsRefundTransaction->setMessage('');
			$billingsRefundTransaction->setUpdateType('import');
			//NO !!! : $billingsRefundTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsRefundTransaction->setPaymentMethodType(new BillingPaymentMethodType(BillingPaymentMethodType::googleplay));
			$billingsRefundTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsRefundTransaction);
		}
		config::getLogger()->addInfo("importing refund transaction line done successfully");
		return($billingsRefundTransaction);
	}
	
	//Sample Apr 1, 2017 1:12:32 AM PDT
	protected function parseDateTime($date, $time) {
	 	$datetime = DateTime::createFromFormat('M j, Y g:i:s A T', $date.' '.$time);
		if($datetime === false) {
			throw new Exception("date cannot be parsed : ".$date." ".$time);
		}
		return($datetime);
	}
	*/
	
	protected function parseInitialOrderId($transactionProviderUuid) {
		return(substr($transactionProviderUuid, 0, 24));
	}
	
}

?>