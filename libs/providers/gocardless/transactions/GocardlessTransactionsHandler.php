<?php

use GoCardlessPro\Client;
use GoCardlessPro\Resources\Payment;
use GoCardlessPro\Resources\Refund;

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class GocardlessTransactionsHandler {
	
	public function __construct() {
	}
	
	public function doUpdateTransactionsByUser(User $user, UserOpts $userOpts) {
		try {
			config::getLogger()->addInfo("updating gocardless transactions...");
			//
			$client = new Client(array(
					'access_token' => getEnv('GOCARDLESS_API_KEY'),
					'environment' => getEnv('GOCARDLESS_API_ENV')
			));
			//
			$paginator = $client->payments()->all(
					['params' =>
							[
									'customer' => $user->getUserProviderUuid()
							]
					]);
			//
			foreach($paginator as $payment_entry) {
				$billingsTransaction = new BillingsTransaction();
				$billingsTransaction->setTransactionLinkId(NULL);
				$billingsTransaction->setProviderId($user->getProviderId());
				$billingsTransaction->setUserId($user->getId());
				$billingsTransaction->setSubId(NULL);//TODO
				$billingsTransaction->setCouponId(NULL);//TODO
				$billingsTransaction->setInvoiceId(NULL);//TODO
				$billingsTransaction->setTransactionBillingUuid(guid());
				$billingsTransaction->setTransactionProviderUuid($payment_entry->id);
				$billingsTransaction->setTransactionCreationDate(new DateTime($payment_entry->created_at));
				$billingsTransaction->setAmountInCents($payment_entry->amount);
				$billingsTransaction->setCurrency($payment_entry->currency);
				$api_mandate = $client->mandates()->get($payment_entry->links->mandate);
				$api_customer_bank_account = $client->customerBankAccounts()->get($api_mandate->links->customer_bank_account);
				$billingsTransaction->setCountry($api_customer_bank_account->country_code);
				$billingsTransaction->setTransactionStatus(self::getChargeMappedTransactionStatus($payment_entry));
				$billingsTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::purchase));
				$billingsTransaction->setInvoiceProviderUuid(NULL);//NO INVOICE...
				$billingsTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsTransaction);
				$this->doImportRefunds($billingsTransaction, $payment_entry);
			}
			//
			config::getLogger()->addInfo("updating gocardless transactions done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating gocardless transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating gocardless transactions failed : ".$msg);
			throw $e;
		} catch(Recurly_NotFoundError $e) {
			$msg = "a not found error exception occurred while updating gocardless transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating gocardless transactions failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while updating gocardless transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating gocardless transactions failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating gocardless transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
		config::getLogger()->addError("updating gocardless transactions failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
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
				//TODO : NC : For me OK + has refunds... to be confirmed 
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::success);
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
	
	private function doImportRefunds(BillingsTransaction $billingsTransaction, Payment $gocardlessChargeTransaction) {
		//
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
			$billingsRefundTransaction = new BillingsTransaction();
			$billingsRefundTransaction->setTransactionLinkId($billingsTransaction->getId());
			$billingsRefundTransaction->setProviderId($billingsTransaction->getProviderId());
			$billingsRefundTransaction->setUserId($billingsTransaction->getUserId());
			$billingsRefundTransaction->setSubId($billingsTransaction->getSubId());
			$billingsRefundTransaction->setCouponId($billingsTransaction->getCouponId());
			$billingsRefundTransaction->setInvoiceId($billingsTransaction->getInvoiceId());
			$billingsRefundTransaction->setTransactionBillingUuid(guid());
			$billingsRefundTransaction->setTransactionProviderUuid($refund_entry->id);
			$billingsRefundTransaction->setTransactionCreationDate(new DateTime($refund_entry->created_at));
			$billingsRefundTransaction->setAmountInCents($refund_entry->amount);
			$billingsRefundTransaction->setCurrency($refund_entry->currency);
			$billingsRefundTransaction->setCountry($billingsTransaction->getCountry());//Country = Country of the Charge
			$billingsRefundTransaction->setTransactionStatus(self::getRefundMappedTransactionStatus($refund_entry));
			$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
			$billingsRefundTransaction->setInvoiceProviderUuid(NULL);//NO INVOICE...
			$billingsRefundTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsRefundTransaction);			
		}
	}
	
}

?>