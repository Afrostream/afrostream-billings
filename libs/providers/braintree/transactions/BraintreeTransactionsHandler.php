<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/transactions/ProviderTransactionsHandler.php';

class BraintreeTransactionsHandler extends ProviderTransactionsHandler {
	
	public function doUpdateTransactionsByUser(User $user, UserOpts $userOpts, DateTime $from = NULL, DateTime $to = NULL, $updateType) {
		try {
			config::getLogger()->addInfo("updating braintree transactions...");
			//
			Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
			Braintree_Configuration::merchantId($this->provider->getMerchantId());
			Braintree_Configuration::publicKey($this->provider->getApiKey());
			Braintree_Configuration::privateKey($this->provider->getApiSecret());
			//
			$search_query = [Braintree\TransactionSearch::customerId()->is($user->getUserProviderUuid())];
			//NC : WARNING : greaterThanOrEqualTo / lessThanOrEqualTo does not seem to work...
			if(isset($from) || isset($to)) {
				$search_query[] = Braintree\TransactionSearch::createdAt()->between($from, $to);
			}
			$braintreeTransactions = Braintree\Transaction::search($search_query);
			//
			foreach ($braintreeTransactions as $braintreeCurrentTransaction) {
				try {
					config::getLogger()->addInfo("updating braintree transaction id=".$braintreeCurrentTransaction->id."...");
					switch($braintreeCurrentTransaction->type) {
						case Braintree\Transaction::SALE :
							$this->createOrUpdateChargeFromProvider($user, $userOpts, $braintreeCurrentTransaction, $updateType);
							break;
						case Braintree\Transaction::CREDIT :
							if(isset($braintreeCurrentTransaction->refundedTransactionId)) {
								$braintreeChargeTransaction = Braintree\Transaction::find($braintreeCurrentTransaction->refundedTransactionId);
								$this->createOrUpdateChargeFromProvider($user, $userOpts, $braintreeChargeTransaction, $updateType);
							} else {
								config::getLogger()->addWarning("Braintree credit Transaction with transaction_provider_uuid=".$braintreeCurrentTransaction->id." should be linked to a charge Transaction");
							}
							break;
						default :
							throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown braintree transaction type : ".$braintreeCurrentTransaction->type);
							break;
					}
					config::getLogger()->addInfo("updating braintree transaction id=".$braintreeCurrentTransaction->id." done successfully");
				} catch (Exception $e) {
					$msg = "an unknown exception occurred while updating braintree transaction id=".$braintreeCurrentTransaction->id.", error_code=".$e->getCode().", error_message=".$e->getMessage();
					config::getLogger()->addError($msg);
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
	
	public function createOrUpdateChargeFromProvider(User $user, UserOpts $userOpts, Braintree\Transaction $braintreeChargeTransaction, $updateType) {
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
			$billingsTransaction->setUpdateType($updateType);
			$billingsTransaction->setPlatformId($this->provider->getPlatformId());
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
			$billingsTransaction->setUpdateType($updateType);
			//NO !!! : $billingsTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
		}
		$this->updateRefundsFromProvider($user, $userOpts, $braintreeChargeTransaction, $billingsTransaction, $updateType);
		config::getLogger()->addInfo("creating/updating transactions from braintree transaction id=".$braintreeChargeTransaction->id." done successfully");
		return($billingsTransaction);
	}
	
	private function updateRefundsFromProvider(User $user, UserOpts $userOpts, Braintree\Transaction $braintreeChargeTransaction, BillingsTransaction $billingsTransaction, $updateType) {
		//
		Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
		Braintree_Configuration::merchantId($this->provider->getMerchantId());
		Braintree_Configuration::publicKey($this->provider->getApiKey());
		Braintree_Configuration::privateKey($this->provider->getApiSecret());
		//
		$braintreeRefundTransactions = array();
		if(count($braintreeChargeTransaction->refundIds) > 0) {
			$braintreeRefundTransactions = Braintree\Transaction::search([
					Braintree\TransactionSearch::ids()->in($braintreeChargeTransaction->refundIds)
			]);
		}
		
		foreach ($braintreeRefundTransactions as $braintreeRefundTransaction) {
			$this->createOrUpdateRefundFromProvider($user, $userOpts, $braintreeRefundTransaction, $billingsTransaction, $updateType);
		}
	}
	
	public function createOrUpdateRefundFromProvider(User $user, UserOpts $userOpts, Braintree\Transaction $braintreeRefundTransaction, BillingsTransaction $billingsTransaction, $updateType) {
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
			$billingsRefundTransaction->setUpdateType($updateType);
			$billingsRefundTransaction->setPlatformId($this->provider->getPlatformId());
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
			$billingsRefundTransaction->setUpdateType($updateType);
			//NO !!! : $billingsRefundTransaction->setPlatformId($this->provider->getPlatformId());
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
	
	public function doRefundTransaction(BillingsTransaction $transaction, RefundTransactionRequest $refundTransactionRequest) {
		try {
			config::getLogger()->addInfo("refunding a ".$this->provider->getName()." transaction with transactionBillingUuid=".$transaction->getTransactionBillingUuid()."...");
			//
			Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
			Braintree_Configuration::merchantId($this->provider->getMerchantId());
			Braintree_Configuration::publicKey($this->provider->getApiKey());
			Braintree_Configuration::privateKey($this->provider->getApiSecret());
			//
			$api_payment = Braintree\Transaction::find($transaction->getTransactionProviderUuid());
			switch ($api_payment->status) {
				case Braintree\Transaction::AUTHORIZED :
				case Braintree\Transaction::SUBMITTED_FOR_SETTLEMENT :
					$result = Braintree\Transaction::void($api_payment->id);
					if (!$result->success) {
						$msg = 'a braintree api error occurred : ';
						$errorString = $result->message;
						foreach($result->errors->deepAll() as $error) {
							$errorString.= '; Code=' . $error->code . ", msg=" . $error->message;
						}
						throw new Exception($msg.$errorString);
					}
					break;
				case Braintree\Transaction::SETTLING :
				case Braintree\Transaction::SETTLED :
					$result = Braintree\Transaction::refund($api_payment->id, $refundTransactionRequest->getAmountInCents());
					if (!$result->success) {
						$msg = 'a braintree api error occurred : ';
						$errorString = $result->message;
						foreach($result->errors->deepAll() as $error) {
							$errorString.= '; Code=' . $error->code . ", msg=" . $error->message;
						}
						throw new Exception($msg.$errorString);
					}
					break;
				case Braintree\Transaction::AUTHORIZATION_EXPIRED :
				case Braintree\Transaction::PROCESSOR_DECLINED :
				case Braintree\Transaction::GATEWAY_REJECTED :
				case Braintree\Transaction::FAILED :
				case Braintree\Transaction::VOIDED :
				case Braintree\Transaction::SETTLEMENT_DECLINED :
				case Braintree\Transaction::SETTLEMENT_PENDING :
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "status : ".$api_payment->status." does not allow refund");
					break;
				case Braintree\Transaction::UNRECOGNIZED :
				case Braintree\Transaction::AUTHORIZING :
				case Braintree\Transaction::SETTLEMENT_CONFIRMED :
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "unexpected braintree transaction status : ".$api_payment->status);
					break;
				default :
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown braintree transaction status : ".$api_payment->status);
					break;
			}
			//reload payment, in order to be up to date
			$api_payment = Braintree\Transaction::find($transaction->getTransactionProviderUuid());
			//
			$user = UserDAO::getUserById($transaction->getUserId());
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			$transaction = $this->createOrUpdateChargeFromProvider($user, $userOpts, $api_payment, $refundTransactionRequest->getOrigin());
			//
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