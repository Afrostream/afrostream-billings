<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class RecurlyTransactionsHandler {
	
	public function __construct() {
	}
	
	public function doUpdateTransactionsByUser(User $user, UserOpts $userOpts) {
		try {
			config::getLogger()->addInfo("updating recurly transactions...");
			//
			Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
			Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
			//
			$recurlyAccount = Recurly_Account::get($user->getUserProviderUuid());
			//
			$recurlyTransactions = Recurly_TransactionList::getForAccount($user->getUserProviderUuid());
			//
			foreach ($recurlyTransactions as $recurlyTransaction) {
				$billingsTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($user->getProviderId(), $recurlyTransaction->uuid);
				$this->createOrUpdateFromProvider($user, $userOpts, $recurlyAccount, $recurlyTransaction, $billingsTransaction);
				/*
				$billingsTransaction = new BillingsTransaction();
				$billingsTransaction->setProviderId($user->getProviderId());
				$billingsTransaction->setUserId($user->getId());
				$billingsTransaction->setSubId(NULL);//TODO
				$billingsTransaction->setCouponId(NULL);//TODO
				$billingsTransaction->setInvoiceId(NULL);//TODO
				$billingsTransaction->setTransactionBillingUuid(guid());
				$billingsTransaction->setTransactionProviderUuid($recurlyTransaction->uuid);
				$billingsTransaction->setTransactionCreationDate($recurlyTransaction->created_at);
				$billingsTransaction->setAmountInCents($recurlyTransaction->amount_in_cents);
				$billingsTransaction->setCurrency($recurlyTransaction->currency);
				$billingsTransaction->setCountry($recurlyAccount->billing_info->get()->country);
				$billingsTransaction->setTransactionStatus(self::getMappedTransactionStatus($recurlyTransaction->status));
				$billingsTransaction->setTransactionType(self::getMappedTransactionType($recurlyTransaction->action));
				if(isset($recurlyTransaction->invoice)) {
					$billingsTransaction->setInvoiceProviderUuid($recurlyTransaction->invoice->get()->uuid);
				}
				$billingsTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsTransaction);
				*/
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
	
	private function createOrUpdateFromProvider(User $user, UserOpts $userOpts, Recurly_Account $recurlyAccount, Recurly_Transaction $recurlyTransaction, BillingsTransaction $billingsTransaction = NULL) {
		$country = NULL;
		if(isset($recurlyAccount->billing_info)) {
			$country = $recurlyAccount->billing_info->get()->country;
		}
		if($billingsTransaction == NULL) {
			//CREATE
			$billingsTransaction = new BillingsTransaction();
			$billingsTransaction->setProviderId($user->getProviderId());
			$billingsTransaction->setUserId($user->getId());
			$billingsTransaction->setSubId(NULL);//TODO
			$billingsTransaction->setCouponId(NULL);//TODO
			$billingsTransaction->setInvoiceId(NULL);//TODO
			$billingsTransaction->setTransactionBillingUuid(guid());
			$billingsTransaction->setTransactionProviderUuid($recurlyTransaction->uuid);
			$billingsTransaction->setTransactionCreationDate($recurlyTransaction->created_at);
			$billingsTransaction->setAmountInCents($recurlyTransaction->amount_in_cents);
			$billingsTransaction->setCurrency($recurlyTransaction->currency);
			$billingsTransaction->setCountry($country);
			$billingsTransaction->setTransactionStatus(self::getMappedTransactionStatus($recurlyTransaction->status));
			$billingsTransaction->setTransactionType(self::getMappedTransactionType($recurlyTransaction->action));
			if(isset($recurlyTransaction->invoice)) {
				$billingsTransaction->setInvoiceProviderUuid($recurlyTransaction->invoice->get()->uuid);
			} else {
				$billingsTransaction->setInvoiceProviderUuid(NULL);
			}
			$billingsTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsTransaction);
		} else {
			//UPDATE
			$billingsTransaction->setProviderId($user->getProviderId());
			$billingsTransaction->setUserId($user->getId());
			$billingsTransaction->setSubId(NULL);//TODO
			$billingsTransaction->setCouponId(NULL);//TODO
			$billingsTransaction->setInvoiceId(NULL);//TODO
			$billingsTransaction->setTransactionBillingUuid(guid());
			$billingsTransaction->setTransactionProviderUuid($recurlyTransaction->uuid);
			$billingsTransaction->setTransactionCreationDate($recurlyTransaction->created_at);
			$billingsTransaction->setAmountInCents($recurlyTransaction->amount_in_cents);
			$billingsTransaction->setCurrency($recurlyTransaction->currency);
			$billingsTransaction->setCountry($country);
			$billingsTransaction->setTransactionStatus(self::getMappedTransactionStatus($recurlyTransaction->status));
			$billingsTransaction->setTransactionType(self::getMappedTransactionType($recurlyTransaction->action));
			if(isset($recurlyTransaction->invoice)) {
				$billingsTransaction->setInvoiceProviderUuid($recurlyTransaction->invoice->get()->uuid);
			} else {
				$billingsTransaction->setInvoiceProviderUuid(NULL);
			}
			$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
		}
		return($billingsTransaction);
	}
	
	/*public function updateFromProvider(User $user, UserOpts $userOpts, Recurly_Account $recurlyAccount, Recurly_Transaction $recurlyTransaction) {
			
	}*/
	
	private static function getMappedTransactionStatus($recurlyTransactionStatus) {
		$billingTransactionStatus = NULL;
		switch ($recurlyTransactionStatus) {
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
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown recurly transaction status : ".$recurlyTransactionStatus);
				break;
		}
		return($billingTransactionStatus);
	}
	
	private static function getMappedTransactionType($recurlyTransactionType) {
		$billingTransactionType = NULL;
		switch ($recurlyTransactionType) {
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
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown recurly transaction type : ".$recurlyTransactionType);
				break;				
		}
		return($billingTransactionType);
	}
	
}

?>