<?php

require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';
require_once __DIR__ . '/../../../../libs/transactions/TransactionsHandler.php';

class BillingsImportBraintreeTransactions {
	
	private $providerid = NULL;
	
	public function __construct() {
		$this->providerid = ProviderDAO::getProviderByName('braintree')->getId();
	}
	
	public function doImportTransactions(DateTime $from = NULL, DateTime $to = NULL) {
		try {
			ScriptsConfig::getLogger()->addInfo("importing transactions from braintree...");
			//
			Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
			Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
			Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
			Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
			//
			$braintreeCustomers = Braintree\Customer::all(); 
				
			foreach ($braintreeCustomers as $braintreeCustomer) {
				try {
					$this->doImportUserTransactions($braintreeCustomer, $from, $to);
				} catch (Exception $e) {
					ScriptsConfig::getLogger()->addError("unexpected exception while importing transactions from braintree with account_code=".$braintreeCustomer->id.", message=".$e->getMessage());
				}
			}
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("unexpected exception while importing transactions from braintree, message=".$e->getMessage());
		}
		ScriptsConfig::getLogger()->addInfo("importing transactions from braintree done");
	}
	
	
	public function doImportUserTransactions(Braintree\Customer $braintreeCustomer, DateTime $from = NULL, DateTime $to = NULL) {
		ScriptsConfig::getLogger()->addInfo("importing transactions from braintree account with account_code=".$braintreeCustomer->id."...");
		$user = UserDAO::getUserByUserProviderUuid($this->providerid, $braintreeCustomer->id);
		if($user == NULL) {
			throw new Exception("user with account_code=".$braintreeCustomer->id." does not exist in billings database");
		}
		$transactionHandler = new TransactionsHandler();
		$transactionHandler->doUpdateTransactionsByUser($user, $from, $to, 'import');
		ScriptsConfig::getLogger()->addInfo("importing transactions from braintree account with account_code=".$braintreeCustomer->id." done successfully");
	}
	
}

?>