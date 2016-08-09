<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class BraintreeTransactionsHandler {
	
	public function __construct() {
	}
	
	public function doUpdateTransactionsByUser(User $user, UserOpts $userOpts, DateTime $from = NULL, DateTime $to = NULL) {
		try {
			config::getLogger()->addInfo("updating braintree transactions...");
			//
			Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
			Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
			Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
			Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
			//
			$search_query = [Braintree\TransactionSearch::customerId()->is($user->getUserProviderUuid())];
			if(isset($from)) {
				$search_query[] = Braintree\TransactionSearch::createdAt()->greaterThanOrEqualTo($from);
			}
			if(isset($to)) {
				$search_query[] = Braintree\TransactionSearch::createdAt()->lessThanOrEqualTo($to);
			}
			$braintreeTransactions = Braintree\Transaction::search($search_query);
			//
			foreach ($braintreeTransactions as $braintreeCurrentTransaction) {
				switch($braintreeCurrentTransaction->type) {
					case Braintree\Transaction::SALE :
						$this->createOrUpdateChargeFromProvider($user, $userOpts, $braintreeCurrentTransaction);
						break;
					case Braintree\Transaction::CREDIT :
						if(isset($braintreeCurrentTransaction->refundedTransactionId)) {
							$braintreeChargeTransaction = Braintree\Transaction::find($braintreeCurrentTransaction->refundedTransactionId);
							$this->createOrUpdateChargeFromProvider($user, $userOpts, $braintreeChargeTransaction);
						} else {
							config::getLogger()->addWarning("Braintree credit Transaction with transaction_provider_uuid=".$braintreeCurrentTransaction->id." should be linked to a charge Transaction");
						}
						break;
					default :
						throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown braintree transaction type : ".$braintreeCurrentTransaction->type);
						break;
				}
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
	
	private function createOrUpdateChargeFromProvider(User $user, UserOpts $userOpts, Braintree\Transaction $braintreeChargeTransaction) {
		config::getLogger()->addInfo("creating/updating transactions from braintree transaction id=".$braintreeChargeTransaction->id."...");
		$billingsTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($user->getProviderId(), $braintreeChargeTransaction->id);
		$subId = NULL;
		$country = NULL;
		if(isset($braintreeChargeTransaction->subscriptionId)) {
			$subscription_provider_uuid = $braintreeChargeTransaction->subscriptionId;
			$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($user->getProviderId(), $subscription_provider_uuid);
			if($subscription == NULL) {
				$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);	
			}
			$subId = $subscription->getId();
			$billingInfoId = $subscription->getBillingInfoId();
			if(isset($billingInfoId)) {
				$billingInfo = BillingInfoDAO::getBillingInfoByBillingInfoId($billingInfoId);
				if($billingInfo == NULL) {
					$msg = "billingInfo with id=".$billingInfoId." not found";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);					
				}
				$country = $billingInfo->getCountryCode();
			}
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
			$billingsTransaction->setCountry($country);
			$billingsTransaction->setTransactionStatus(self::getMappedTransactionStatus($braintreeChargeTransaction));
			$billingsTransaction->setTransactionType(self::getMappedTransactionType($braintreeChargeTransaction));
			$billingsTransaction->setInvoiceProviderUuid($braintreeChargeTransaction->orderId);
			$billingsTransaction->setMessage("provider_status=".$braintreeChargeTransaction->status);
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
			$billingsTransaction->setCountry($country);
			$billingsTransaction->setTransactionStatus(self::getMappedTransactionStatus($braintreeChargeTransaction));
			$billingsTransaction->setTransactionType(self::getMappedTransactionType($braintreeChargeTransaction));
			$billingsTransaction->setInvoiceProviderUuid($braintreeChargeTransaction->orderId);
			$billingsTransaction->setMessage("provider_status=".$braintreeChargeTransaction->status);
			$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
		}
		$this->updateRefundsFromProvider($user, $userOpts, $braintreeChargeTransaction, $billingsTransaction);
		config::getLogger()->addInfo("creating/updating transactions from braintree transaction id=".$braintreeChargeTransaction->id." done successfully");
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
		config::getLogger()->addInfo("creating/updating refund transaction from braintree refund transaction id=".$braintreeRefundTransaction->id."...");
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
			$billingsRefundTransaction->setTransactionStatus(self::getMappedTransactionStatus($braintreeRefundTransaction));
			$billingsRefundTransaction->setTransactionType(self::getMappedTransactionType($braintreeRefundTransaction));
			$billingsRefundTransaction->setInvoiceProviderUuid($braintreeRefundTransaction->orderId);
			$billingsRefundTransaction->setMessage("provider_status=".$braintreeRefundTransaction->status);
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
			$billingsRefundTransaction->setTransactionStatus(self::getMappedTransactionStatus($braintreeRefundTransaction));
			$billingsRefundTransaction->setTransactionType(self::getMappedTransactionType($braintreeRefundTransaction));
			$billingsRefundTransaction->setInvoiceProviderUuid($braintreeRefundTransaction->orderId);
			$billingsRefundTransaction->setMessage("provider_status=".$braintreeRefundTransaction->status);
			$billingsRefundTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsRefundTransaction);
		}
		config::getLogger()->addInfo("creating/updating refund transaction from braintree refund transaction id=".$braintreeRefundTransaction->id." done successfully");
		return($billingsRefundTransaction);
	}
	
	private static function getMappedTransactionStatus(Braintree\Transaction $braintreeTransaction) {
		$billingTransactionStatus = NULL;
		#See : https://developers.braintreepayments.com/reference/general/statuses#transaction
		switch ($braintreeTransaction->status) {
			case Braintree\Transaction::AUTHORIZED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case Braintree\Transaction::AUTHORIZATION_EXPIRED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::canceled);
				break;
			case Braintree\Transaction::PROCESSOR_DECLINED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::declined);
				break;
			case Braintree\Transaction::GATEWAY_REJECTED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::declined);
				break;
			case Braintree\Transaction::FAILED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::failed);
				break;
			case Braintree\Transaction::VOIDED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::canceled);
				break;
			case Braintree\Transaction::SUBMITTED_FOR_SETTLEMENT :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case Braintree\Transaction::SETTLING :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;				
			case Braintree\Transaction::SETTLED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::success);
				break;
			case Braintree\Transaction::SETTLEMENT_DECLINED :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::declined);
				break;
			case Braintree\Transaction::SETTLEMENT_PENDING :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case Braintree\Transaction::UNRECOGNIZED :
			case Braintree\Transaction::AUTHORIZING :
			case Braintree\Transaction::SETTLEMENT_CONFIRMED :
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unexpected braintree transaction status : ".$braintreeTransaction->status);
				break;	
			default :
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown braintree transaction status : ".$braintreeTransaction->status);
				break;
		}
		return($billingTransactionStatus);
	}
	
	private static function getMappedTransactionType(Braintree\Transaction $braintreeTransaction) {
		$billingTransactionType = NULL;
		switch ($braintreeTransaction->type) {
			case Braintree\Transaction::SALE :
				$billingTransactionType = new BillingsTransactionType(BillingsTransactionType::purchase);
				break;
			case Braintree\Transaction::CREDIT :
				$billingTransactionType = new BillingsTransactionType(BillingsTransactionType::refund);
				break;
			default :
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown braintree transaction type : ".$braintreeTransaction->type);
				break;				
		}
		return($billingTransactionType);
	}
	
}

?>