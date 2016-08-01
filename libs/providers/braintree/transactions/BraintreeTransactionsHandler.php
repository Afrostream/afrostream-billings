<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class BraintreeTransactionsHandler {
	
	public function __construct() {
	}
	
	public function doUpdateTransactionsByUser(User $user, UserOpts $userOpts) {
		try {
			config::getLogger()->addInfo("updating braintree transactions...");
			//
			Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
			Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
			Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
			Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
			//
			$braintreeChargeTransactions = Braintree\Transaction::search([
					Braintree\TransactionSearch::customerId()->is($user->getUserProviderUuid()),
					Braintree\TransactionSearch::type()->is(Braintree\Transaction::SALE)
			]);
			//
			foreach ($braintreeChargeTransactions as $braintreeChargeTransaction) {
				$billingsTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($user->getProviderId(), $braintreeChargeTransaction->id);
				$this->createOrUpdateChargeFromProvider($user, $userOpts, $braintreeChargeTransaction, $billingsTransaction);
			}
			//
			config::getLogger()->addInfo("updating braintree transactions done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating braintree transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating braintree transactions failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating braintree transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating braintree transactions failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
	}
	
	private function createOrUpdateChargeFromProvider(User $user, UserOpts $userOpts, Braintree\Transaction $braintreeChargeTransaction, BillingsTransaction $billingsTransaction = NULL) {
		config::getLogger()->addInfo("creating/updating transactions from braintree transactions...");
		$subId = NULL;
		if(isset($braintreeChargeTransaction->subscriptionId)) {
			$subscription_provider_uuid = $braintreeChargeTransaction->subscriptionId;
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($user->getProviderId(), $subscription_provider_uuid);
			if($subscription == NULL) {
				$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);	
			}
			$subId = $subscription->getId();
		}
		$couponId = NULL;
		$invoiceId = NULL;
		if($billingsTransaction == NULL) {
			//CREATE
			$billingsTransaction = new BillingsTransaction();
			$billingsTransaction->setProviderId($user->getProviderId());
			$billingsTransaction->setUserId($user->getId());
			$billingsTransaction->setSubId($subId);
			$billingsTransaction->setCouponId($couponId);
			$billingsTransaction->setInvoiceId($invoiceId);
			$billingsTransaction->setTransactionBillingUuid(guid());
			$billingsTransaction->setTransactionProviderUuid($braintreeChargeTransaction->id);
			$billingsTransaction->setTransactionCreationDate($braintreeChargeTransaction->createdAt);
			$billingsTransaction->setAmountInCents($braintreeChargeTransaction->amount * 100);
			$billingsTransaction->setCurrency($braintreeChargeTransaction->currencyIsoCode);
			$billingsTransaction->setCountry(NULL);//TODO
			$billingsTransaction->setTransactionStatus(self::getMappedTransactionStatus($braintreeChargeTransaction->status));
			$billingsTransaction->setTransactionType(self::getMappedTransactionType($braintreeChargeTransaction->type));
			$billingsTransaction->setInvoiceProviderUuid($braintreeChargeTransaction->orderId);
			$billingsTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsTransaction);
		} else {
			//UPDATE
			$billingsTransaction->setProviderId($user->getProviderId());
			$billingsTransaction->setUserId($user->getId());
			$billingsTransaction->setSubId($subId);
			$billingsTransaction->setCouponId($couponId);
			$billingsTransaction->setInvoiceId($invoiceId);
			//NO !!! : $billingsTransaction->setTransactionBillingUuid(guid());
			$billingsTransaction->setTransactionProviderUuid($braintreeChargeTransaction->id);
			$billingsTransaction->setTransactionCreationDate($braintreeChargeTransaction->createdAt);
			$billingsTransaction->setAmountInCents($braintreeChargeTransaction->amount * 100);
			$billingsTransaction->setCurrency($braintreeChargeTransaction->currencyIsoCode);
			$billingsTransaction->setCountry(NULL);//TODO
			$billingsTransaction->setTransactionStatus(self::getMappedTransactionStatus($braintreeChargeTransaction->status));
			$billingsTransaction->setTransactionType(self::getMappedTransactionType($braintreeChargeTransaction->type));
			$billingsTransaction->setInvoiceProviderUuid($braintreeChargeTransaction->orderId);
			$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
		}
		$this->updateRefundsFromProvider($user, $userOpts, $braintreeChargeTransaction, $billingsTransaction);
		config::getLogger()->addInfo("creating/updating transactions from braintree transactions done successfully");
		return($billingsTransaction);
	}
	
	private function updateRefundsFromProvider(User $user, UserOpts $userOpts, Braintree\Transaction $braintreeChargeTransaction, BillingsTransaction $billingsTransaction) {
		//
		Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
		Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
		Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
		Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
		//
		$braintreeRefundTransactions = array();
		if(count($braintreeChargeTransaction->refundIds) > 0) {
			$braintreeRefundTransactions = Braintree\Transaction::search([
					Braintree\TransactionSearch::ids()->in($braintreeChargeTransaction->refundIds)
			]);
		}
		
		foreach ($braintreeRefundTransactions as $braintreeRefundTransaction) {
			$this->createOrUpdateRefundFromProvider($user, $userOpts, $braintreeRefundTransaction, $billingsTransaction);
		}
	}
	
	private function createOrUpdateRefundFromProvider(User $user, UserOpts $userOpts, Braintree\Transaction $braintreeRefundTransaction, BillingsTransaction $billingsTransaction) {
		config::getLogger()->addInfo("creating/updating refund transaction from braintree refund transaction...");
		$billingsRefundTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($user->getProviderId(), $braintreeRefundTransaction->id);
		if($billingsRefundTransaction == NULL) {
			//CREATE
			$billingsRefundTransaction = new BillingsTransaction();
			$billingsRefundTransaction->setTransactionLinkId($billingsTransaction->getId());
			$billingsRefundTransaction->setProviderId($billingsTransaction->getProviderId());
			$billingsRefundTransaction->setUserId($billingsTransaction->getUserId());
			$billingsRefundTransaction->setSubId($billingsTransaction->getSubId());
			$billingsRefundTransaction->setCouponId($billingsTransaction->getCouponId());
			$billingsRefundTransaction->setInvoiceId($billingsTransaction->getInvoiceId());
			$billingsRefundTransaction->setTransactionBillingUuid(guid());
			$billingsRefundTransaction->setTransactionProviderUuid($braintreeRefundTransaction->id);
			$billingsRefundTransaction->setTransactionCreationDate($braintreeRefundTransaction->createdAt);
			$billingsRefundTransaction->setAmountInCents($braintreeRefundTransaction->amount * 100);
			$billingsRefundTransaction->setCurrency($braintreeRefundTransaction->currencyIsoCode);
			$billingsRefundTransaction->setCountry($billingsTransaction->getCountry());//Country = Country of the Charge
			$billingsRefundTransaction->setTransactionStatus(self::getMappedTransactionStatus($braintreeRefundTransaction->status));
			$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
			$billingsRefundTransaction->setInvoiceProviderUuid($braintreeRefundTransaction->orderId);
			$billingsRefundTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsRefundTransaction);
		} else {
			//UPDATE
			$billingsRefundTransaction->setTransactionLinkId($billingsTransaction->getId());
			$billingsRefundTransaction->setProviderId($billingsTransaction->getProviderId());
			$billingsRefundTransaction->setUserId($billingsTransaction->getUserId());
			$billingsRefundTransaction->setSubId($billingsTransaction->getSubId());
			$billingsRefundTransaction->setCouponId($billingsTransaction->getCouponId());
			$billingsRefundTransaction->setInvoiceId($billingsTransaction->getInvoiceId());
			//NO !!! : $billingsRefundTransaction->setTransactionBillingUuid(guid());
			$billingsRefundTransaction->setTransactionProviderUuid($braintreeRefundTransaction->id);
			$billingsRefundTransaction->setTransactionCreationDate($braintreeRefundTransaction->createdAt);
			$billingsRefundTransaction->setAmountInCents($braintreeRefundTransaction->amount * 100);
			$billingsRefundTransaction->setCurrency($braintreeRefundTransaction->currencyIsoCode);
			$billingsRefundTransaction->setCountry($billingsTransaction->getCountry());//Country = Country of the Charge
			$billingsRefundTransaction->setTransactionStatus(self::getMappedTransactionStatus($braintreeRefundTransaction->status));
			$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
			$billingsRefundTransaction->setInvoiceProviderUuid($braintreeRefundTransaction->orderId);
			$billingsRefundTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsRefundTransaction);
		}
		config::getLogger()->addInfo("creating/updating refund transaction from braintree refund transaction done successfully");
		return($billingsRefundTransaction);
	}
	
	private static function getMappedTransactionStatus($braintreeTransactionStatus) {
		$billingTransactionStatus = NULL;
		switch ($braintreeTransactionStatus) {
			case Braintree\Transaction::AUTHORIZATION_EXPIRED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::canceled);
				break;
			case Braintree\Transaction::AUTHORIZING :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case Braintree\Transaction::AUTHORIZED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case Braintree\Transaction::GATEWAY_REJECTED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::declined);
				break;
			case Braintree\Transaction::FAILED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::failed);
				break;
			case Braintree\Transaction::PROCESSOR_DECLINED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::declined);
				break;
			case Braintree\Transaction::SETTLED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::success);
				break;
			case Braintree\Transaction::SETTLING :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case Braintree\Transaction::SUBMITTED_FOR_SETTLEMENT :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case Braintree\Transaction::VOIDED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::canceled);
				break;
			case Braintree\Transaction::UNRECOGNIZED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::failed);
				break;
			case Braintree\Transaction::SETTLEMENT_DECLINED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::declined);
				break;
			case Braintree\Transaction::SETTLEMENT_PENDING :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case Braintree\Transaction::SETTLEMENT_CONFIRMED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::success);
				break;
			default :
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown braintree transaction status : ".$braintreeTransactionStatus);
				break;
		}
		return($billingTransactionStatus);
	}
	
	private static function getMappedTransactionType($braintreeTransactionType) {
		$billingTransactionType = NULL;
		switch ($braintreeTransactionType) {
			case Braintree\Transaction::SALE :
				$billingTransactionType = new BillingsTransactionType(BillingsTransactionType::purchase);
				break;
			case Braintree\Transaction::CREDIT :
				$billingTransactionType = new BillingsTransactionType(BillingsTransactionType::refund);
				break;
			default :
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown braintree transaction type : ".$braintreeTransactionType);
				break;				
		}
		return($billingTransactionType);
	}
	
}

?>