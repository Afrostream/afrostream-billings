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

}

?>