<?php

use GoCardlessPro\Client;
use GoCardlessPro\Resources\Customer;
use GoCardlessPro\Resources\Payment;
use GoCardlessPro\Resources\Refund;

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class GocardlessTransactionsHandler {
	
	public function __construct() {
	}
	
	public function doUpdateTransactionsByUser(User $user, UserOpts $userOpts, DateTime $from = NULL, DateTime $to = NULL) {
		try {
			config::getLogger()->addInfo("updating gocardless transactions...");
			//
			$client = new Client(array(
					'access_token' => getEnv('GOCARDLESS_API_KEY'),
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
				$this->createOrUpdateChargeFromProvider($user, $userOpts, $customer, $payment_entry);
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
	
	public function createOrUpdateChargeFromProvider(User $user, UserOpts $userOpts, Customer $gocardlessCustomer, Payment $gocardlessChargeTransaction) {
		config::getLogger()->addInfo("creating/updating charge transaction from gocardless charge transaction...");
		$billingsTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($user->getProviderId(), $gocardlessChargeTransaction->id);
		$country = $gocardlessCustomer->country_code;
		if($country == NULL) {
			$client = new Client(array(
					'access_token' => getEnv('GOCARDLESS_API_KEY'),
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
			$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
		}
		$this->updateRefundsFromProvider($user, $userOpts, $gocardlessChargeTransaction, $billingsTransaction);
		config::getLogger()->addInfo("creating/updating charge transaction from gocardless charge transaction done successfully");
		return($billingsTransaction);
	}
	
	private function updateRefundsFromProvider(User $user, UserOpts $userOpts, Payment $gocardlessChargeTransaction, BillingsTransaction $billingsTransaction) {
		$client = new Client(array(
				'access_token' => getEnv('GOCARDLESS_API_KEY'),
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
			$this->createOrUpdateRefundFromProvider($user, $userOpts, $refund_entry, $billingsTransaction);
		}
	}
	
	private function createOrUpdateRefundFromProvider(User $user, UserOpts $userOpts, Refund $gocardlessRefundTransaction, BillingsTransaction $billingsTransaction) {
		config::getLogger()->addInfo("creating/updating refund transaction from gocardless refund transaction...");
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
			$billingsRefundTransaction->setMessage("provider_status=".$gocardlessRefundTransaction->status);
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
			$billingsRefundTransaction->setMessage("provider_status=".$gocardlessRefundTransaction->status);
			$billingsRefundTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsRefundTransaction);
		}
		config::getLogger()->addInfo("creating/updating refund transaction from gocardless refund transaction done successfully");
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
			case 'charge_back' :
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
	
}

?>