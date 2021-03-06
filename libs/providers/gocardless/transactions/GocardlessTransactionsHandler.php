<?php

use GoCardlessPro\Client;
use GoCardlessPro\Resources\Customer;
use GoCardlessPro\Resources\Payment;
use GoCardlessPro\Resources\Refund;

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/transactions/ProviderTransactionsHandler.php';

class GocardlessTransactionsHandler extends ProviderTransactionsHandler {
	
	public function doUpdateTransactionsByUser(User $user, UserOpts $userOpts, DateTime $from = NULL, DateTime $to = NULL, $updateType) {
		try {
			config::getLogger()->addInfo("updating gocardless transactions...");
			//
			$client = new Client(array(
					'access_token' => $this->provider->getApiSecret(),
					'environment' => getEnv('GOCARDLESS_API_ENV')
			));
			//
			$customer = $client->customers()->get($user->getUserProviderUuid());
			//
			$params = array();
			$params['customer'] = $customer->id;
			if(isset($from)) {
				$params['created_at[gte]'] = $from->format('Y-m-d\TH:i:s\Z');
			}
			if(isset($to)) {
				$params['created_at[lte]'] = $to->format('Y-m-d\TH:i:s\Z');
			}
			//CHARGES
			$payments_paginator = $client->payments()->all(['params' => $params]);
			//
			foreach($payments_paginator as $payment_entry) {
				try {
					config::getLogger()->addInfo("updating gocardless transaction id=".$payment_entry->id."...");
					$this->createOrUpdateChargeFromProvider($user, $userOpts, $customer, $payment_entry, $updateType);
					config::getLogger()->addInfo("updating gocardless transaction id=".$payment_entry->id." done successfully");
				} catch(Exception $e) {
					$msg = "an unknown exception occurred while updating gocardless transaction id=".$payment_entry->id.", error_code=".$e->getCode().", error_message=".$e->getMessage();
					config::getLogger()->addError($msg);
				}
			}
			//
			config::getLogger()->addInfo("updating gocardless transactions done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating gocardless transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating gocardless transactions failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating gocardless transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating gocardless transactions failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
	}
	
	public function createOrUpdateChargeFromProvider(User $user, UserOpts $userOpts, Customer $gocardlessCustomer, Payment $gocardlessChargeTransaction, $updateType) {
		config::getLogger()->addInfo("creating/updating charge transaction from gocardless charge transaction id=".$gocardlessChargeTransaction->id."...");
		$billingsTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($user->getProviderId(), $gocardlessChargeTransaction->id);
		$country = $gocardlessCustomer->country_code;
		if($country == NULL) {
			$client = new Client(array(
					'access_token' => $this->provider->getApiSecret(),
					'environment' => getEnv('GOCARDLESS_API_ENV')
			));
			$api_mandate = $client->mandates()->get($gocardlessChargeTransaction->links->mandate);
			$api_customer_bank_account = $client->customerBankAccounts()->get($api_mandate->links->customer_bank_account);
			$country = $api_customer_bank_account->country_code;
		}
		$subId = NULL;
		if(isset($gocardlessChargeTransaction->links->subscription)) {
			$subscription_provider_uuid = $gocardlessChargeTransaction->links->subscription;
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
			$billingsTransaction->setTransactionLinkId(NULL);
			$billingsTransaction->setProviderId($user->getProviderId());
			$billingsTransaction->setUserId($user->getId());
			$billingsTransaction->setSubId($subId);
			$billingsTransaction->setCouponId(NULL);
			$billingsTransaction->setInvoiceId(NULL);//NO INVOICE...
			$billingsTransaction->setTransactionBillingUuid(guid());
			$billingsTransaction->setTransactionProviderUuid($gocardlessChargeTransaction->id);
			$billingsTransaction->setTransactionCreationDate(new DateTime($gocardlessChargeTransaction->created_at));
			$billingsTransaction->setAmountInCents($gocardlessChargeTransaction->amount);
			$billingsTransaction->setCurrency($gocardlessChargeTransaction->currency);
			$billingsTransaction->setCountry($country);
			$billingsTransaction->setTransactionStatus(self::getChargeMappedTransactionStatus($gocardlessChargeTransaction));
			$billingsTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::purchase));
			$billingsTransaction->setInvoiceProviderUuid(NULL);//NO INVOICE...
			$billingsTransaction->setMessage("provider_status=".$gocardlessChargeTransaction->status);
			$billingsTransaction->setUpdateType($updateType);
			$billingsTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsTransaction->setPaymentMethodType(self::getChargeMappedTransactionPaymentMethodType($gocardlessChargeTransaction));
			$billingsTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsTransaction);
		} else {
			//UPDATE
			$billingsTransaction->setTransactionLinkId(NULL);
			$billingsTransaction->setProviderId($user->getProviderId());
			$billingsTransaction->setUserId($user->getId());
			$billingsTransaction->setSubId($subId);
			$billingsTransaction->setCouponId(NULL);
			$billingsTransaction->setInvoiceId(NULL);//NO INVOICE...
			//NO !!! : $billingsTransaction->setTransactionBillingUuid(guid());
			$billingsTransaction->setTransactionProviderUuid($gocardlessChargeTransaction->id);
			$billingsTransaction->setTransactionCreationDate(new DateTime($gocardlessChargeTransaction->created_at));
			$billingsTransaction->setAmountInCents($gocardlessChargeTransaction->amount);
			$billingsTransaction->setCurrency($gocardlessChargeTransaction->currency);
			$billingsTransaction->setCountry($country);
			$billingsTransaction->setTransactionStatus(self::getChargeMappedTransactionStatus($gocardlessChargeTransaction));
			$billingsTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::purchase));
			$billingsTransaction->setInvoiceProviderUuid(NULL);//NO INVOICE...
			$billingsTransaction->setMessage("provider_status=".$gocardlessChargeTransaction->status);
			$billingsTransaction->setUpdateType($updateType);
			//NO !!! : $billingsTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsTransaction->setPaymentMethodType(self::getChargeMappedTransactionPaymentMethodType($gocardlessChargeTransaction));
			$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
		}
		$this->updateRefundsFromProvider($user, $userOpts, $gocardlessChargeTransaction, $billingsTransaction, $updateType);
		config::getLogger()->addInfo("creating/updating charge transaction from gocardless charge transaction id=".$gocardlessChargeTransaction->id." done successfully");
		return($billingsTransaction);
	}
	
	private function updateRefundsFromProvider(User $user, UserOpts $userOpts, Payment $gocardlessChargeTransaction, BillingsTransaction $billingsTransaction, $updateType) {
		$client = new Client(array(
				'access_token' => $this->provider->getApiSecret(),
				'environment' => getEnv('GOCARDLESS_API_ENV')
		));
		//
		$paginator = $client->refunds()->all(
				['params' =>
						[
								'payment' => $gocardlessChargeTransaction->id
						]
				]);
		//
		foreach($paginator as $refund_entry) {
			$this->createOrUpdateRefundFromProvider($user, $userOpts, $refund_entry, $billingsTransaction, $updateType);
		}
	}
	
	private function createOrUpdateRefundFromProvider(User $user, UserOpts $userOpts, Refund $gocardlessRefundTransaction, BillingsTransaction $billingsTransaction, $updateType) {
		config::getLogger()->addInfo("creating/updating refund transaction from gocardless refund transaction id=".$gocardlessRefundTransaction->id."...");
		$billingsRefundTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($user->getProviderId(), $gocardlessRefundTransaction->id);
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
			$billingsRefundTransaction->setTransactionProviderUuid($gocardlessRefundTransaction->id);
			$billingsRefundTransaction->setTransactionCreationDate(new DateTime($gocardlessRefundTransaction->created_at));
			$billingsRefundTransaction->setAmountInCents($gocardlessRefundTransaction->amount);
			$billingsRefundTransaction->setCurrency($gocardlessRefundTransaction->currency);
			$billingsRefundTransaction->setCountry($billingsTransaction->getCountry());//Country = Country of the Charge
			$billingsRefundTransaction->setTransactionStatus(self::getRefundMappedTransactionStatus($gocardlessRefundTransaction));
			$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
			$billingsRefundTransaction->setInvoiceProviderUuid(NULL);//NO INVOICE...
			$billingsRefundTransaction->setMessage("provider_status=none");
			$billingsRefundTransaction->setUpdateType($updateType);
			$billingsRefundTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsRefundTransaction->setPaymentMethodType(self::getRefundMappedTransactionPaymentMethodType($gocardlessRefundTransaction));
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
			$billingsRefundTransaction->setTransactionProviderUuid($gocardlessRefundTransaction->id);
			$billingsRefundTransaction->setTransactionCreationDate(new DateTime($gocardlessRefundTransaction->created_at));
			$billingsRefundTransaction->setAmountInCents($gocardlessRefundTransaction->amount);
			$billingsRefundTransaction->setCurrency($gocardlessRefundTransaction->currency);
			$billingsRefundTransaction->setCountry($billingsTransaction->getCountry());//Country = Country of the Charge
			$billingsRefundTransaction->setTransactionStatus(self::getRefundMappedTransactionStatus($gocardlessRefundTransaction));
			$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
			$billingsRefundTransaction->setInvoiceProviderUuid(NULL);//NO INVOICE...
			$billingsRefundTransaction->setMessage("provider_status=none");
			$billingsRefundTransaction->setUpdateType($updateType);
			//NO !!! : $billingsRefundTransaction->setPlatformId($this->provider->getPlatformId());
			$billingsRefundTransaction->setPaymentMethodType(self::getRefundMappedTransactionPaymentMethodType($gocardlessRefundTransaction));
			$billingsRefundTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsRefundTransaction);
		}
		config::getLogger()->addInfo("creating/updating refund transaction from gocardless refund transaction id=".$gocardlessRefundTransaction->id." done successfully");
		return($billingsRefundTransaction);
	}
	
	private static function getChargeMappedTransactionStatus(Payment $gocardlessChargeTransaction) {
		$billingTransactionStatus = NULL;
		switch($gocardlessChargeTransaction->status) {
			case 'pending_customer_approval' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case 'pending_submission' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case 'submitted' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case 'confirmed' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::success);
				break;
			case 'paid_out' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::success);
				break;
			case 'cancelled' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::canceled);
				break;
			case 'customer_approval_denied' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::declined);
				break;
			case 'failed' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::failed);
				break;
			case 'charged_back' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::declined);
				break;
			default :
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown gocardless payment transaction type : ".$gocardlessChargeTransaction->status);
				break;
		}
		return($billingTransactionStatus);
	}
	
	private static function getRefundMappedTransactionStatus(Refund $gocardlessRefundTransaction) {
		return(new BillingsTransactionStatus(BillingsTransactionStatus::success));
	}
	
	public function doRefundTransaction(BillingsTransaction $transaction, RefundTransactionRequest $refundTransactionRequest) {
		try {
			config::getLogger()->addInfo("refunding a ".$this->provider->getName()." transaction with transactionBillingUuid=".$transaction->getTransactionBillingUuid()."...");
			$client = new Client(array(
					'access_token' => $this->provider->getApiSecret(),
					'environment' => getEnv('GOCARDLESS_API_ENV')
			));
			$api_payment = $client->payments()->get($transaction->getTransactionProviderUuid());
			switch ($api_payment->status) {
				case 'pending_customer_approval' :
				case 'pending_submission' :
					$api_payment = $client->payments()->cancel($transaction->getTransactionProviderUuid());
					break;
				case 'confirmed' :
				case 'paid_out' :
					$api_refund = $client->refunds()->create([
						"params" => [	"amount" => $refundTransactionRequest->getAmountInCents() == NULL ? $api_payment->amount : $refundTransactionRequest->getAmountInCents(),
										"total_amount_confirmation" => $refundTransactionRequest->getAmountInCents() == NULL ? $api_payment->amount : $refundTransactionRequest->getAmountInCents(),
										"links" => ["payment" => $api_payment->id]]
								]);
					//reload payment, in order to be up to date
					$api_payment = $client->payments()->get($transaction->getTransactionProviderUuid());
					break;
				case 'submitted' :
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "current status : ".$api_payment->status." does not allow refund, please retry later");
					break;
				case 'cancelled' :
				case 'customer_approval_denied' :
				case 'failed' :
				case 'charged_back' :
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "final status : ".$api_payment->status." does not allow refund");
					break;
				default :
					throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown gocardless payment transaction type : ".$api_payment->status);
					break;
			}
			$user = UserDAO::getUserById($transaction->getUserId());
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			$gocardlessCustomer = $client->customers()->get($user->getUserProviderUuid());
			$transaction = $this->createOrUpdateChargeFromProvider($user, $userOpts, $gocardlessCustomer, $api_payment, $refundTransactionRequest->getOrigin());
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
	
	private function getChargeMappedTransactionPaymentMethodType(Payment $gocardlessChargeTransaction) {
		return(new BillingPaymentMethodType(BillingPaymentMethodType::sepa));
	}
	
	private function getRefundMappedTransactionPaymentMethodType(Refund $gocardlessRefundTransaction) {
		return(new BillingPaymentMethodType(BillingPaymentMethodType::sepa));
	}
	
}

?>