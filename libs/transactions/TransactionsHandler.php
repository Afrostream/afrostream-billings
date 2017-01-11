<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../utils/utils.php';
require_once __DIR__ . '/../providers/recurly/transactions/RecurlyTransactionsHandler.php';
require_once __DIR__ . '/../providers/gocardless/transactions/GocardlessTransactionsHandler.php';
require_once __DIR__ . '/../providers/stripe/transactions/StripeTransactionsHandler.php';
require_once __DIR__ . '/../providers/braintree/transactions/BraintreeTransactionsHandler.php';
require_once __DIR__ . '/../providers/global/ProviderHandlersBuilder.php';
require_once __DIR__ . '/../providers/global/requests/RefundTransactionRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetTransactionRequest.php';

class TransactionsHandler {
	
	public function __construct() {
	}
	
	public function doUpdateTransactionsByUser(User $user, DateTime $from = NULL, DateTime $to = NULL, $updateType) {
		try {
			config::getLogger()->addInfo("transactions updating for userid=".$user->getId()."...");
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
				
			$provider = ProviderDAO::getProviderById($user->getProviderId());
				
			if($provider == NULL) {
				$msg = "unknown provider id : ".$user->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			switch($provider->getName()) {
				case 'recurly' :
					$transactionsHandler = new RecurlyTransactionsHandler($provider);
					$transactionsHandler->doUpdateTransactionsByUser($user, $userOpts, $from, $to, $updateType);
					break;
				case 'gocardless' :
					$transactionsHandler = new GocardlessTransactionsHandler($provider);
					$transactionsHandler->doUpdateTransactionsByUser($user, $userOpts, $from, $to, $updateType);
					break;
				case 'stripe' :
					$transactionsHandler = new StripeTransactionsHandler($provider);
					$transactionsHandler->doUpdateTransactionsByUser($user, $userOpts, $from, $to, $updateType);
					break;
				case 'braintree' :
					$transactionsHandler = new BraintreeTransactionsHandler($provider);
					$transactionsHandler->doUpdateTransactionsByUser($user, $userOpts, $from, $to, $updateType);
					break;
				default:
					//nothing to do (unknown)
					break;
			}
			config::getLogger()->addInfo("transactions updating for userid=".$user->getId()." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while transactions updating for userid=".$user->getId().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("transactions updating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while transactions updating for userid=".$user->getId().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("transactions updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	public function doUpdateTransactionByTransactionProviderUuid($provider_name, $transactionProviderUuid, $updateType) {
		try {
			config::getLogger()->addInfo("transaction updating for transactionProviderUuid=".$transactionProviderUuid."...");
			$provider = ProviderDAO::getProviderByName($provider_name);
			
			if($provider == NULL) {
				$msg = "unknown provider named : ".$provider_name;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			switch($provider->getName()) {
				case 'stripe' :
					$transactionsHandler = new StripeTransactionsHandler($provider);
					$transactionsHandler->doUpdateTransactionByTransactionProviderUuid($transactionProviderUuid, $updateType);
					break;
				default:
					//nothing to do (unknown)
					break;
			}
			config::getLogger()->addInfo("transaction updating for transactionProviderUuid=".$transactionProviderUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while transaction updating for transactionProviderUuid=".$transactionProviderUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("transaction updating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while transaction updating for transactionProviderUuid=".$transactionProviderUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("transaction updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	//TODO
	/*public function doCreateTransaction(BillingsTransaction $billingsTransaction,
			User $user, 
			BillingsSubscription $billingsSubscription = NULL,
			BillingUserInternalCoupon $billingUserInternalCoupon = NULL,
			BillingsTransaction $parentBillingTransaction = NULL)
	{
		$billingsTransaction->setProviderId($user->getProviderId());
		$billingsTransaction->setUserId($user->getId());
		$billingsTransaction->setSubId(isset($billingsSubscription) ? $billingsSubscription->getId() : NULL);
		$billingsTransaction->setCouponId(isset($billingUserInternalCoupon) ? $billingUserInternalCoupon->getId() : NULL);
		$billingsTransaction->setInvoiceId(NULL);
		$billingsTransaction->setTransactionBillingUuid(guid());
		$billingsTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsTransaction);
		return($billingsTransaction);
	}*/
	
	public function doRefundTransaction(RefundTransactionRequest $refundTransactionRequest) {
		$transactionBillingUuid = $refundTransactionRequest->getTransactionBillingUuid();
		$db_transaction = NULL;
		try {
			config::getLogger()->addInfo("db_transaction refund for transactionBillingUuid=".$transactionBillingUuid."...");
			$db_transaction = BillingsTransactionDAO::getBillingsTransactionByTransactionBillingUuid($transactionBillingUuid);
			if($db_transaction == NULL) {
				$msg = "unknown transactionBillingUuid : ".$transactionBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($db_transaction->getTransactionType() != BillingsTransactionType::purchase) {
				$msg = "transaction with transactionBillingUuid : ".$transactionBillingUuid." is not a purchase, it cannot be refunded";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$provider = ProviderDAO::getProviderById($db_transaction->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider with id : ".$db_transaction->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			$providerTransactionsHandlerInstance = ProviderHandlersBuilder::getProviderTransactionsHandlerInstance($provider);
			$db_transaction = $providerTransactionsHandlerInstance->doRefundTransaction($db_transaction, $refundTransactionRequest);
			//
			config::getLogger()->addInfo("db_transaction refund for transactionBillingUuid=".$transactionBillingUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while db_transaction refunding for transactionBillingUuid=".$transactionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("db_transaction refunding failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while db_transaction refunding for transactionBillingUuid=".$transactionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("db_transaction refunding failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		} finally {
			//
		}
		return($db_transaction);
	}
	
	public function doGetTransaction(GetTransactionRequest $getTransactionRequest) {
		$transactionBillingUuid = $getTransactionRequest->getTransactionBillingUuid();
		$db_transaction = NULL;
		try {
			config::getLogger()->addInfo("db_transaction getting for transactionBillingUuid=".$transactionBillingUuid."...");
			$db_transaction = BillingsTransactionDAO::getBillingsTransactionByTransactionBillingUuid($transactionBillingUuid);
			config::getLogger()->addInfo("db_transaction getting for transactionBillingUuid=".$transactionBillingUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while db_transaction getting for transactionBillingUuid=".$transactionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("db_transaction getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while db_transaction getting for transactionBillingUuid=".$transactionBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("db_transaction getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		} finally {
			//
		}
		return($db_transaction);
	}
	
}

?>