<?php

use GoCardlessPro\Client;
use GoCardlessPro\Resources\Customer;
use GoCardlessPro\Core\Paginator;

require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';
require_once __DIR__ . '/../../../../libs/transactions/TransactionsHandler.php';

class BillingsImportGocardlessTransactions {
	
	private $providerid = NULL;
	
	public function __construct() {
		$this->providerid = ProviderDAO::getProviderByName('gocardless')->getId();
	}
	
	public function doImportTransactions() {
		try {
			ScriptsConfig::getLogger()->addInfo("importing transactions from gocardless...");
			//
			$client = new Client(array(
					'access_token' => getEnv('GOCARDLESS_API_KEY'),
					'environment' => getEnv('GOCARDLESS_API_ENV')
			));
			
			$paginator = $client->customers()->all(
					['params' =>
							[
							]
					]);
			//
			foreach ($paginator as $customer_entry) {
				try {
					$this->doImportUserTransactions($customer_entry);
				} catch (Exception $e) {
					ScriptsConfig::getLogger()->addError("unexpected exception while importing transactions from gocardless with account_code=".$customer_entry->id.", message=".$e->getMessage());
				}
			}
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("unexpected exception while importing transactions from gocardless, message=".$e->getMessage());
		}
		ScriptsConfig::getLogger()->addInfo("importing transactions from gocardless done");
	}
	
	
	public function doImportUserTransactions(Customer $gocardlessCustomer) {
		ScriptsConfig::getLogger()->addInfo("importing transactions from gocardless account with account_code=".$gocardlessCustomer->id."...");
		$user = UserDAO::getUserByUserProviderUuid($this->providerid, $gocardlessCustomer->id);
		if($user == NULL) {
			throw new Exception("user with account_code=".$gocardlessCustomer->id." does not exist in billings database");
		}
		$transactionHandler = new TransactionsHandler();
		$transactionHandler->doUpdateTransactionsByUser($user);
		ScriptsConfig::getLogger()->addInfo("importing transactions from gocardless account with account_code=".$gocardlessCustomer->id." done successfully");
	}	
}

?>