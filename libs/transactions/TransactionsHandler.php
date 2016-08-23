<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../utils/utils.php';
require_once __DIR__ . '/../providers/recurly/transactions/RecurlyTransactionsHandler.php';
require_once __DIR__ . '/../providers/gocardless/transactions/GocardlessTransactionsHandler.php';
require_once __DIR__ . '/../providers/stripe/transactions/StripeTransactionsHandler.php';
require_once __DIR__ . '/../providers/braintree/transactions/BraintreeTransactionsHandler.php';

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
					$transactionsHandler = new RecurlyTransactionsHandler();
					$transactionsHandler->doUpdateTransactionsByUser($user, $userOpts, $from, $to, $updateType);
					break;
				case 'gocardless' :
					$transactionsHandler = new GocardlessTransactionsHandler();
					$transactionsHandler->doUpdateTransactionsByUser($user, $userOpts, $from, $to, $updateType);
					break;
				case 'stripe' :
					$transactionsHandler = new StripeTransactionsHandler();
					$transactionsHandler->doUpdateTransactionsByUser($user, $userOpts, $from, $to, $updateType);
					break;
				case 'braintree' :
					$transactionsHandler = new BraintreeTransactionsHandler();
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
			switch($provider_name) {
				case 'stripe' :
					$transactionsHandler = new StripeTransactionsHandler();
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
		
}

?>