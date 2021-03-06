<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/transactions/ProviderTransactionsHandler.php';

class RecurlyTransactionsHandler extends ProviderTransactionsHandler {
	
	public function doUpdateTransactionsByUser(User $user, UserOpts $userOpts, DateTime $from = NULL, DateTime $to = NULL, $updateType) {
		try {
			config::getLogger()->addInfo("updating recurly transactions...");
			if(isset($from)) {
				$msg = "recurly does not support date ranges for transactions, 'from' field must be NULL";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(isset($to)) {
				$msg = "recurly does not support date ranges for transactions, 'to' field must be NULL";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);				
			}
			//
			Recurly_Client::$subdomain = $this->provider->getMerchantId();
			Recurly_Client::$apiKey = $this->provider->getApiSecret();
			//
			$recurlyAccount = Recurly_Account::get($user->getUserProviderUuid());
			//
			$recurlyTransactions = Recurly_TransactionList::getForAccount($user->getUserProviderUuid());
			//
			foreach ($recurlyTransactions as $recurlyTransaction) {
				try {
					config::getLogger()->addInfo("updating recurly transaction id=".$recurlyTransaction->uuid."...");
					switch($recurlyTransaction->action) {
						case 'purchase' :
							$this->createOrUpdateChargeFromProvider($user, $userOpts, $recurlyAccount, $recurlyTransaction, $updateType);
							break;
						case 'verify' :
							$this->createOrUpdateChargeFromProvider($user, $userOpts, $recurlyAccount, $recurlyTransaction, $updateType);
							break;
						case 'refund' :
							if(isset($recurlyTransaction->original_transaction)) {
								$this->createOrUpdateChargeFromProvider($user, $userOpts, $recurlyAccount, $recurlyTransaction->original_transaction->get(), $updateType);
							} else {
								$this->createOrUpdateRefundFromProvider($user, $userOpts, $recurlyAccount, $recurlyTransaction, NULL, $updateType);
							}
							break;
						default :
							throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown recurly transaction type : ".$recurlyTransaction->action);
							break;
					}
					config::getLogger()->addInfo("updating recurly transaction id=".$recurlyTransaction->uuid." done successfully");
				} catch(Exception $e) {
					$msg = "an unknown exception occurred while updating recurly transaction id=".$recurlyTransaction->uuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
					config::getLogger()->addError($msg);
				}
			}
			//
			config::getLogger()->addInfo("updating recurly transactions done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating recurly transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating recurly transactions failed : ".$msg);
			throw $e;
		} catch(Recurly_NotFoundError $e) {
			$msg = "a not found error exception occurred while updating recurly transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating recurly transactions failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while updating recurly transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating recurly transactions failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating recurly transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating recurly transactions failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
	}
	
	public function createOrUpdateChargeFromProvider(User $user, UserOpts $userOpts, Recurly_Account $recurlyAccount, Recurly_Transaction $recurlyTransaction, $updateType) {
		config::getLogger()->addInfo("creating/updating transactions from recurly transaction id=".$recurlyTransaction->uuid."...");
		$billingsTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($user->getProviderId(), $recurlyTransaction->uuid);
		$country = NULL;
		if(isset($recurlyAccount->billing_info)) {
			$country = $recurlyAccount->billing_info->get()->country;
		}
		if($country == NULL) {
			if(isset($billingsTransaction)) {
				$country = $billingsTransaction->getCountry();
			}
		}
		$subId = NULL;
		if($recurlyTransaction->source == 'subscription') {
			$subscription_provider_uuid = $recurlyTransaction->subscription->get()->uuid;
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
			$billingsTransaction->setTransactionProviderUuid($recurlyTransaction->uuid);
			$billingsTransaction->setTransactionCreationDate($recurlyTransaction->created_at);
			$billingsTransaction->setAmountInCents($recurlyTransaction->amount_in_cents);
			$billingsTransaction->setCurrency($recurlyTransaction->currency);
			$billingsTransaction->setCountry($country);
			$billingsTransaction->setTransactionStatus(self::getMappedTransactionStatus($recurlyTransaction));
			$billingsTransaction->setTransactionType(self::getMappedTransactionType($recurlyTransaction));
			if(isset($recurlyTransaction->invoice)) {
				$billingsTransaction->setInvoiceProviderUuid($recurlyTransaction->invoice->get()->uuid);
			} else {
				$billingsTransaction->setInvoiceProviderUuid(NULL);
			}
			$billingsTransaction->setMessage("provider_status=".$recurlyTransaction->status);
			$billingsTransaction->setUpdateType($updateType);
			$billingsTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsTransaction->setPaymentMethodType(self::getMappedTransactionPaymentMethodType($recurlyTransaction));
			$billingsTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsTransaction);
		} else {
			//UPDATE
			$billingsTransaction->setProviderId($user->getProviderId());
			$billingsTransaction->setUserId($user->getId());
			$billingsTransaction->setSubId($subId);
			$billingsTransaction->setCouponId($couponId);
			$billingsTransaction->setInvoiceId($invoiceId);
			//NO !!! : $billingsTransaction->setTransactionBillingUuid(guid());
			$billingsTransaction->setTransactionProviderUuid($recurlyTransaction->uuid);
			$billingsTransaction->setTransactionCreationDate($recurlyTransaction->created_at);
			$billingsTransaction->setAmountInCents($recurlyTransaction->amount_in_cents);
			$billingsTransaction->setCurrency($recurlyTransaction->currency);
			$billingsTransaction->setCountry($country);
			$billingsTransaction->setTransactionStatus(self::getMappedTransactionStatus($recurlyTransaction));
			$billingsTransaction->setTransactionType(self::getMappedTransactionType($recurlyTransaction));
			if(isset($recurlyTransaction->invoice)) {
				$billingsTransaction->setInvoiceProviderUuid($recurlyTransaction->invoice->get()->uuid);
			} else {
				$billingsTransaction->setInvoiceProviderUuid(NULL);
			}
			$billingsTransaction->setMessage("provider_status=".$recurlyTransaction->status);
			$billingsTransaction->setUpdateType($updateType);
			//NO !!! : $billingsTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsTransaction->setPaymentMethodType(self::getMappedTransactionPaymentMethodType($recurlyTransaction));
			$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
		}
		if($recurlyTransaction->action == 'purchase') {
			$this->updateRefundsFromProvider($user, $userOpts, $recurlyAccount, $recurlyTransaction, $billingsTransaction, $updateType);
		}
		config::getLogger()->addInfo("creating/updating transactions from recurly transaction id=".$recurlyTransaction->uuid." done successfully");
		return($billingsTransaction);
	}
	
	private function updateRefundsFromProvider(User $user, UserOpts $userOpts, Recurly_Account $recurlyAccount, Recurly_Transaction $recurlyTransaction, BillingsTransaction $billingsTransaction, $updateType) {
		$recurlyRefundTransactions = Recurly_TransactionList::getForAccount($user->getUserProviderUuid(), ['type' => 'refund']);
		foreach($recurlyRefundTransactions as $recurlyRefundTransaction) {
			if($recurlyTransaction->uuid == $recurlyRefundTransaction->original_transaction->get()->uuid) {
				$this->createOrUpdateRefundFromProvider($user, $userOpts, $recurlyAccount, $recurlyRefundTransaction, $billingsTransaction, $updateType);
			}
		}
	}
	
	public function createOrUpdateRefundFromProvider(User $user, UserOpts $userOpts, Recurly_Account $recurlyAccount, Recurly_Transaction $recurlyRefundTransaction, BillingsTransaction $billingsTransaction = NULL, $updateType) {
		config::getLogger()->addInfo("creating/updating refund transaction from recurly refund transaction id=".$recurlyRefundTransaction->uuid."...");
		$billingsRefundTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($user->getProviderId(), $recurlyRefundTransaction->uuid);
		$country = NULL;
		if(isset($recurlyAccount->billing_info)) {
			$country = $recurlyAccount->billing_info->get()->country;
		}
		if($country == NULL) {
			if(isset($billingsTransaction)) {
				$country = $billingsTransaction->getCountry();
			}
		}
		$subId = NULL;
		if($recurlyRefundTransaction->source == 'subscription') {
			$subscription_provider_uuid = $recurlyRefundTransaction->subscription->get()->uuid;
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
		$transactionLinkId = NULL;
		if(isset($billingsTransaction)) {
			$transactionLinkId = $billingsTransaction->getId();
		}
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
			$billingsRefundTransaction->setTransactionProviderUuid($recurlyRefundTransaction->uuid);
			$billingsRefundTransaction->setTransactionCreationDate($recurlyRefundTransaction->created_at);
			$billingsRefundTransaction->setAmountInCents($recurlyRefundTransaction->amount_in_cents);
			$billingsRefundTransaction->setCurrency($recurlyRefundTransaction->currency);
			$billingsRefundTransaction->setCountry($country);
			$billingsRefundTransaction->setTransactionStatus(self::getMappedTransactionStatus($recurlyRefundTransaction));
			$billingsRefundTransaction->setTransactionType(self::getMappedTransactionType($recurlyRefundTransaction));
			if(isset($recurlyRefundTransaction->invoice)) {
				$billingsRefundTransaction->setInvoiceProviderUuid($recurlyRefundTransaction->invoice->get()->uuid);
			} else {
				$billingsRefundTransaction->setInvoiceProviderUuid(NULL);
			}
			$billingsRefundTransaction->setMessage("provider_status=".$recurlyRefundTransaction->status);
			$billingsRefundTransaction->setUpdateType($updateType);
			$billingsRefundTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsRefundTransaction->setPaymentMethodType(self::getMappedTransactionPaymentMethodType($recurlyRefundTransaction));
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
			$billingsRefundTransaction->setTransactionProviderUuid($recurlyRefundTransaction->uuid);
			$billingsRefundTransaction->setTransactionCreationDate($recurlyRefundTransaction->created_at);
			$billingsRefundTransaction->setAmountInCents($recurlyRefundTransaction->amount_in_cents);
			$billingsRefundTransaction->setCurrency($recurlyRefundTransaction->currency);
			$billingsRefundTransaction->setCountry($country);
			$billingsRefundTransaction->setTransactionStatus(self::getMappedTransactionStatus($recurlyRefundTransaction));
			$billingsRefundTransaction->setTransactionType(self::getMappedTransactionType($recurlyRefundTransaction));
			if(isset($recurlyRefundTransaction->invoice)) {
				$billingsRefundTransaction->setInvoiceProviderUuid($recurlyRefundTransaction->invoice->get()->uuid);
			} else {
				$billingsRefundTransaction->setInvoiceProviderUuid(NULL);
			}
			$billingsRefundTransaction->setMessage("provider_status=".$recurlyRefundTransaction->status);
			$billingsRefundTransaction->setUpdateType($updateType);
			//NO !!! : $billingsRefundTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsRefundTransaction->setPaymentMethodType(self::getMappedTransactionPaymentMethodType($recurlyRefundTransaction));
			$billingsRefundTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsRefundTransaction);
		}
		config::getLogger()->addInfo("creating/updating refund transaction from recurly refund transaction id=".$recurlyRefundTransaction->uuid." done successfully");
		return($billingsRefundTransaction);
	}
	
	private static function getMappedTransactionStatus(Recurly_Transaction $recurlyTransaction) {
		$billingTransactionStatus = NULL;
		switch ($recurlyTransaction->status) {
			case 'success' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::success);
				break;
			case 'declined' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::declined);
				break;
			case 'void' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::void);
				break;
			default :
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown recurly transaction status : ".$recurlyTransaction->status);
				break;
		}
		return($billingTransactionStatus);
	}
	
	private static function getMappedTransactionType(Recurly_Transaction $recurlyTransaction) {
		$billingTransactionType = NULL;
		switch ($recurlyTransaction->action) {
			case 'purchase' :
				$billingTransactionType = new BillingsTransactionType(BillingsTransactionType::purchase);
				break;
			case 'refund' :
				$billingTransactionType = new BillingsTransactionType(BillingsTransactionType::refund);
				break;
			case 'verify' :
				$billingTransactionType = new BillingsTransactionType(BillingsTransactionType::verify);
				break;
			default :
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown recurly transaction type : ".$recurlyTransaction->action);
				break;				
		}
		return($billingTransactionType);
	}
	
	public function doRefundTransaction(BillingsTransaction $transaction, RefundTransactionRequest $refundTransactionRequest) {
		try {
			config::getLogger()->addInfo("refunding a ".$this->provider->getName()." transaction with transactionBillingUuid=".$transaction->getTransactionBillingUuid()."...");
			//
			Recurly_Client::$subdomain = $this->provider->getMerchantId();
			Recurly_Client::$apiKey = $this->provider->getApiSecret();
			//
			$api_payment = Recurly_Transaction::get($transaction->getTransactionProviderUuid());
			$api_invoice = $api_payment->invoice->get();
			//@see : https://dev.recurly.com/docs/line-item-refunds
			$line_items = $api_invoice->line_items;
			$adjustments = array_map(
					function($line_item) { return $line_item->toRefundAttributes(); }, 
					$api_invoice->line_items
			);
			$refund_invoice = $api_invoice->refund($adjustments, 'transaction');
			//reload payment, in order to be up to date
			$api_payment = Recurly_Transaction::get($transaction->getTransactionProviderUuid());
			//
			$user = UserDAO::getUserById($transaction->getUserId());
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			$recurlyAccount = Recurly_Account::get($user->getUserProviderUuid());
			$transaction = $this->createOrUpdateChargeFromProvider($user, $userOpts, $recurlyAccount, $api_payment, $refundTransactionRequest->getOrigin());
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
	
	private function getMappedTransactionPaymentMethodType(Recurly_Transaction $recurlyTransaction) {
		$paymentMethodType = NULL;
		switch ($recurlyTransaction->payment_method) {
			case 'credit_card' :
				$paymentMethodType = new BillingPaymentMethodType(BillingPaymentMethodType::card);
				break;
			case 'paypal' :
				$paymentMethodType = new BillingPaymentMethodType(BillingPaymentMethodType::paypal);
				break;
			case 'check' :
				$paymentMethodType = new BillingPaymentMethodType(BillingPaymentMethodType::check);
				break;
			case 'wire_transfer' :
				$paymentMethodType = new BillingPaymentMethodType(BillingPaymentMethodType::wire_transfer);
				break;
			case 'money_order' :
				$paymentMethodType = new BillingPaymentMethodType(BillingPaymentMethodType::money_order);
				break;
			default :
				$paymentMethodType = new BillingPaymentMethodType(BillingPaymentMethodType::other);
				break;
		}
		return($paymentMethodType);
	}
	
}

?>