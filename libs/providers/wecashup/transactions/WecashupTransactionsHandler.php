<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../client/WecashupClient.php';
require_once __DIR__ . '/../../global/transactions/ProviderTransactionsHandler.php';

class WecashupTransactionsHandler extends ProviderTransactionsHandler {
		
	public function createOrUpdateRefundFromProvider(User $user, UserOpts $userOpts, WecashupCustomerResponse $wecashupCustomerResponse = NULL, WecashupTransactionResponse $wecashupTransactionResponse, BillingsTransaction $billingsTransaction = NULL, $updateType) {
		config::getLogger()->addInfo("creating/updating refund transaction from wecashup refund transaction id=".$wecashupTransactionResponse->getTransactionUid()."...");
		$billingsRefundTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($user->getProviderId(), $wecashupTransactionResponse->getTransactionUid());
		$country = NULL;
		if(isset($billingsTransaction)) {
			$country = $billingsTransaction->getCountry();
		}
		$subId = NULL;
		if(isset($billingsTransaction)) {
			$subId = $billingsTransaction->getSubId();
		}
		$couponId = NULL;
		$invoiceId = NULL;
		$transactionLinkId = NULL;
		if(isset($billingsTransaction)) {
			$transactionLinkId = $billingsTransaction->getId();
		}
		$amount_from_transaction = $wecashupTransactionResponse->getTransactionSenderTotalAmount();
		$amount_in_cents_from_transaction = intval($amount_from_transaction * 100);
		if($billingsRefundTransaction == NULL) {
			//CREATE
			$billingsRefundTransaction = new BillingsTransaction();
			$billingsRefundTransaction->setTransactionLinkId($transactionLinkId);
			$billingsRefundTransaction->setProviderId($user->getProviderId());
			$billingsRefundTransaction->setUserId($user->getId());
			$billingsRefundTransaction->setSubId($subId);
			$billingsRefundTransaction->setCouponId($couponId);
			$billingsRefundTransaction->setInvoiceId($invoiceId);
			$billingsRefundTransaction->setTransactionBillingUuid(guid());
			$billingsRefundTransaction->setTransactionProviderUuid($wecashupTransactionResponse->getTransactionUid());
			$billingsRefundTransaction->setTransactionCreationDate($wecashupTransactionResponse->getTransactionDate());
			$billingsRefundTransaction->setAmountInCents($amount_in_cents_from_transaction);
			$billingsRefundTransaction->setCurrency($wecashupTransactionResponse->getTransactionSenderCurrency());
			$billingsRefundTransaction->setCountry($country);
			$billingsRefundTransaction->setTransactionStatus(self::getMappedTransactionStatus($wecashupTransactionResponse));
			$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
			$billingsRefundTransaction->setInvoiceProviderUuid(NULL);
			$billingsRefundTransaction->setMessage("provider_status=".$wecashupTransactionResponse->getTransactionStatus());
			$billingsRefundTransaction->setUpdateType($updateType);
			$billingsRefundTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsRefundTransaction);
		} else {
			//UPDATE
			$billingsRefundTransaction->setTransactionLinkId($transactionLinkId);
			$billingsRefundTransaction->setProviderId($user->getProviderId());
			$billingsRefundTransaction->setUserId($user->getId());
			$billingsRefundTransaction->setSubId($subId);
			$billingsRefundTransaction->setCouponId($couponId);
			$billingsRefundTransaction->setInvoiceId($invoiceId);
			//NO !!! : $billingsTransaction->setTransactionBillingUuid(guid());
			$billingsRefundTransaction->setTransactionProviderUuid($wecashupTransactionResponse->getTransactionUid());
			$billingsRefundTransaction->setTransactionCreationDate($wecashupTransactionResponse->getTransactionDate());
			$billingsRefundTransaction->setAmountInCents($amount_in_cents_from_transaction);
			$billingsRefundTransaction->setCurrency($wecashupTransactionResponse->getTransactionSenderCurrency());
			$billingsRefundTransaction->setCountry($country);
			$billingsRefundTransaction->setTransactionStatus(self::getMappedTransactionStatus($wecashupTransactionResponse));
			$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
			$billingsRefundTransaction->setInvoiceProviderUuid(NULL);
			$billingsRefundTransaction->setMessage("provider_status=".$wecashupTransactionResponse->getTransactionStatus());
			$billingsRefundTransaction->setUpdateType($updateType);
			$billingsRefundTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsRefundTransaction);
		}
		config::getLogger()->addInfo("creating/updating refund transaction from wecashup refund transaction id=".$wecashupTransactionResponse->getTransactionUid()." done successfully");
		return($billingsRefundTransaction);
	}

	private static function getMappedTransactionStatus(WecashupTransactionResponse $wecashupTransactionResponse) {
		$billingTransactionStatus = NULL;
		switch ($wecashupTransactionResponse->getTransactionStatus()) {
			case 'TOVALIDATE' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case 'PENDING' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case 'PAID' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::success);
				break;
			case 'FAILED' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::failed);
				break;
			default :
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown wecashup transaction status : ".$wecashupTransactionResponse->getTransactionStatus());
				break;
		}
		return($billingTransactionStatus);
	}
	
	public function doRefundTransaction(BillingsTransaction $transaction, RefundTransactionRequest $refundTransactionRequest) {
		try {
			config::getLogger()->addInfo("refunding a ".$this->provider->getName()." transaction with transactionBillingUuid=".$transaction->getTransactionBillingUuid()."...");
			$user = UserDAO::getUserById($transaction->getUserId());
			if($user == NULL) {
				$msg = "unknown user with id : ".$transaction->getUserId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			//
			$wecashupClient = new WecashupClient();
			$wecashupRefundTransactionRequest = new WecashupRefundTransactionRequest();
			$wecashupRefundTransactionRequest->setTransactionUid($transaction->getTransactionProviderUuid());
			$wecashupRefundTransactionResponse = $wecashupClient->refundTransaction($wecashupRefundTransactionRequest);
			//
			$wecashupTransactionRequest = new WecashupTransactionRequest();
			$wecashupTransactionRequest->setTransactionUid($wecashupRefundTransactionResponse->getTransactionUid());
			$refundTransaction = $wecashupClient->getTransaction($wecashupTransactionRequest);
			//
			$this->createOrUpdateRefundFromProvider($user, $userOpts, NULL, $refundTransaction, $transaction, $refundTransactionRequest->getOrigin());	
			//
			$transaction = BillingsTransactionDAO::getBillingsTransactionById($transaction->getId());
			config::getLogger()->addInfo("refunding a ".$this->provider->getName()." transaction with transactionBillingUuid=".$transaction->getTransactionBillingUuid()." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while refunding a ".$this->provider->getName()." transaction with transactionBillingUuid=".$transaction->getTransactionBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("refunding a ".$this->provider->getName()." transaction failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while refunding a ".$this->provider->getName()." transaction with transactionBillingUuid=".$transaction->getTransactionBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("refunding a ".$this->provider->getName()." transaction failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($transaction);
	}

}

?>