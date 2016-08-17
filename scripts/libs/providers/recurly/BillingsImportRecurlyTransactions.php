<?php

require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';
require_once __DIR__ . '/../../../../libs/transactions/TransactionsHandler.php';

class BillingsImportRecurlyTransactions {
	
	private $providerid = NULL;
	
	public function __construct() {
		$this->providerid = ProviderDAO::getProviderByName('recurly')->getId();
	}
	
	public function doImportTransactions() {
		try {
			ScriptsConfig::getLogger()->addInfo("importing transactions from recurly...");
			//
			Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
			Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
			//
			$recurlyAccounts = Recurly_AccountList::getActive();
				
			foreach ($recurlyAccounts as $recurlyAccount) {
				try {
					$this->doImportUserTransactions($recurlyAccount);
					usleep(getEnv('RECURLY_IMPORT_TRANSACTIONS_SLEEPING_TIME_IN_MILLIS') * 1000);
				} catch (Exception $e) {
					ScriptsConfig::getLogger()->addError("unexpected exception while importing transactions from recurly with account_code=".$recurlyAccount->account_code.", message=".$e->getMessage());
				}
			}
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("unexpected exception while importing transactions from recurly, message=".$e->getMessage());
		}
		ScriptsConfig::getLogger()->addInfo("importing transactions from recurly done");
	}
	
	
	public function doImportUserTransactions(Recurly_Account $recurlyAccount) {
		ScriptsConfig::getLogger()->addInfo("importing transactions from recurly account with account_code=".$recurlyAccount->account_code."...");
		$user = UserDAO::getUserByUserProviderUuid($this->providerid, $recurlyAccount->account_code);
		if($user == NULL) {
			throw new Exception("user with account_code=".$recurlyAccount->account_code." does not exist in billings database");
		}
		$transactionHandler = new TransactionsHandler();
		$transactionHandler->doUpdateTransactionsByUser($user);
		ScriptsConfig::getLogger()->addInfo("importing transactions from recurly account with account_code=".$recurlyAccount->account_code." done successfully");
	}	
}

?>