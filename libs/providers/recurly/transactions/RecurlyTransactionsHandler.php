<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class RecurlyTransactionsHandler {
	
	public function __construct() {
	}
	
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
			Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
			Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
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
							$this->createOrUpdateChargeFromProvider($user, $userOpts, $recurlyAccount, $recurlyTransaction->original_transaction->get(), $updateType);
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
			$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
		}
		if($recurlyTransaction->action == 'purchase') {
			$this->updateRefundsFromProvider($user, $userOpts, $recurlyTransaction, $billingsTransaction, $updateType);
		}
		config::getLogger()->addInfo("creating/updating transactions from recurly transaction id=".$recurlyTransaction->uuid." done successfully");
		return($billingsTransaction);
	}
	
	private function updateRefundsFromProvider(User $user, UserOpts $userOpts, Recurly_Transaction $recurlyTransaction, BillingsTransaction $billingsTransaction, $updateType) {
		$recurlyRefundTransactions = Recurly_TransactionList::getForAccount($user->getUserProviderUuid(), ['type' => 'refund']);
		foreach($recurlyRefundTransactions as $recurlyRefundTransaction) {
			if($recurlyTransaction->uuid == $recurlyRefundTransaction->original_transaction->get()->uuid) {
				$this->createOrUpdateRefundFromProvider($user, $userOpts, $recurlyRefundTransaction, $billingsTransaction, $updateType);
			}
		}
	}
	
	private function createOrUpdateRefundFromProvider(User $user, UserOpts $userOpts, Recurly_Transaction $recurlyRefundTransaction, BillingsTransaction $billingsTransaction, $updateType) {
		config::getLogger()->addInfo("creating/updating refund transaction from recurly refund transaction id=".$recurlyRefundTransaction->uuid."...");
		$billingsRefundTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($user->getProviderId(), $recurlyRefundTransaction->uuid);
		if($billingsRefundTransaction == NULL) {
			//CREATE
			$billingsRefundTransaction = new BillingsTransaction();
			$billingsRefundTransaction->setProviderId($user->getProviderId());
			$billingsRefundTransaction->setUserId($user->getId());
			$billingsRefundTransaction->setSubId($billingsTransaction->getSubId());
			$billingsRefundTransaction->setCouponId($billingsTransaction->getCouponId());
			$billingsRefundTransaction->setInvoiceId($billingsTransaction->getInvoiceId());
			$billingsRefundTransaction->setTransactionBillingUuid(guid());
			$billingsRefundTransaction->setTransactionProviderUuid($recurlyRefundTransaction->uuid);
			$billingsRefundTransaction->setTransactionCreationDate($recurlyRefundTransaction->created_at);
			$billingsRefundTransaction->setAmountInCents($recurlyRefundTransaction->amount_in_cents);
			$billingsRefundTransaction->setCurrency($recurlyRefundTransaction->currency);
			$billingsRefundTransaction->setCountry($billingsTransaction->getCountry());//Country = Country of the Charge
			$billingsRefundTransaction->setTransactionStatus(self::getMappedTransactionStatus($recurlyRefundTransaction));
			$billingsRefundTransaction->setTransactionType(self::getMappedTransactionType($recurlyRefundTransaction));
			if(isset($recurlyRefundTransaction->invoice)) {
				$billingsRefundTransaction->setInvoiceProviderUuid($recurlyRefundTransaction->invoice->get()->uuid);
			} else {
				$billingsRefundTransaction->setInvoiceProviderUuid(NULL);
			}
			$billingsRefundTransaction->setMessage("provider_status=".$recurlyRefundTransaction->status);
			$billingsRefundTransaction->setUpdateType($updateType);
			$billingsRefundTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsRefundTransaction);
		} else {
			//UPDATE
			$billingsRefundTransaction->setProviderId($user->getProviderId());
			$billingsRefundTransaction->setUserId($user->getId());
			$billingsRefundTransaction->setSubId($billingsTransaction->getSubId());
			$billingsRefundTransaction->setCouponId($billingsTransaction->getCouponId());
			$billingsRefundTransaction->setInvoiceId($billingsTransaction->getInvoiceId());
			//NO !!! : $billingsTransaction->setTransactionBillingUuid(guid());
			$billingsRefundTransaction->setTransactionProviderUuid($recurlyRefundTransaction->uuid);
			$billingsRefundTransaction->setTransactionCreationDate($recurlyRefundTransaction->created_at);
			$billingsRefundTransaction->setAmountInCents($recurlyRefundTransaction->amount_in_cents);
			$billingsRefundTransaction->setCurrency($recurlyRefundTransaction->currency);
			$billingsRefundTransaction->setCountry($billingsTransaction->getCountry());//Country = Country of the Charge
			$billingsRefundTransaction->setTransactionStatus(self::getMappedTransactionStatus($recurlyRefundTransaction));
			$billingsRefundTransaction->setTransactionType(self::getMappedTransactionType($recurlyRefundTransaction));
			if(isset($recurlyRefundTransaction->invoice)) {
				$billingsRefundTransaction->setInvoiceProviderUuid($recurlyRefundTransaction->invoice->get()->uuid);
			} else {
				$billingsRefundTransaction->setInvoiceProviderUuid(NULL);
			}
			$billingsRefundTransaction->setMessage("provider_status=".$recurlyRefundTransaction->status);
			$billingsRefundTransaction->setUpdateType($updateType);
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
	
}

?>