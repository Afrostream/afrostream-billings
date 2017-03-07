<?php

use GoCardlessPro\Client;
use GoCardlessPro\Resources\Customer;
use GoCardlessPro\Core\Paginator;

require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';
require_once __DIR__ . '/../../../../libs/transactions/TransactionsHandler.php';

class BillingsImportGocardlessTransactions {
	
	private $provider = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
	}
	
	public function doImportTransactions(DateTime $from = NULL, DateTime $to = NULL) {
		try {
			ScriptsConfig::getLogger()->addInfo("importing transactions from gocardless...");
			//
			$client = new Client(array(
					'access_token' => $this->provider->getApiSecret(),
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
					$this->doImportUserTransactions($customer_entry, $from, $to);
				} catch (Exception $e) {
					ScriptsConfig::getLogger()->addError("unexpected exception while importing transactions from gocardless with account_code=".$customer_entry->id.", message=".$e->getMessage());
				}
			}
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("unexpected exception while importing transactions from gocardless, message=".$e->getMessage());
		}
		ScriptsConfig::getLogger()->addInfo("importing transactions from gocardless done");
	}
	
	
	public function doImportUserTransactions(Customer $gocardlessCustomer, DateTime $from = NULL, DateTime $to = NULL) {
		ScriptsConfig::getLogger()->addInfo("importing transactions from gocardless account with account_code=".$gocardlessCustomer->id."...");
		$user = UserDAO::getUserByUserProviderUuid($this->provider->getId(), $gocardlessCustomer->id);
		if($user == NULL) {
			throw new Exception("user with account_code=".$gocardlessCustomer->id." does not exist in billings database");
		}
		$transactionHandler = new TransactionsHandler();
		$transactionHandler->doUpdateTransactionsByUser($user, $from, $to, 'import');
		ScriptsConfig::getLogger()->addInfo("importing transactions from gocardless account with account_code=".$gocardlessCustomer->id." done successfully");
	}
	
}

?>