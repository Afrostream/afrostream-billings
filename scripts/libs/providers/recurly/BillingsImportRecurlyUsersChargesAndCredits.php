<?php

require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';

class BillingsImportRecurlyUsersChargesAndCredits {
	
	private $providerid = NULL;
	
	public function __construct() {
		$this->providerid = ProviderDAO::getProviderByName('recurly')->getId();
	}
	
	public function doImportUsersChargesAndCredits() {
		try {
			ScriptsConfig::getLogger()->addInfo("importing charges and credits from recurly...");
			//
			Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
			Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
			//
			$recurlyAccounts = Recurly_AccountList::get();
				
			foreach ($recurlyAccounts as $recurlyAccount) {
				try {
					$this->doImportUserChargesAndCredits($recurlyAccount);
				} catch (Exception $e) {
					ScriptsConfig::getLogger()->addError("unexpected exception while importing charges and credits from recurly with account_code=".$recurlyAccount->account_code.", message=".$e->getMessage());
				}
			}
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("unexpected exception while importing charges and credits from recurly, message=".$e->getMessage());
		}
		ScriptsConfig::getLogger()->addInfo("importing charges and credits from recurly done");
	}
	
	
	public function doImportUserChargesAndCredits(Recurly_Account $recurlyAccount) {
		ScriptsConfig::getLogger()->addInfo("importing charges and credits from recurly account with account_code=".$recurlyAccount->account_code."...");
		$user = UserDAO::getUserByUserProviderUuid($this->providerid, $recurlyAccount->account_code);
		if($user == NULL) {
			throw new Exception("user with account_code=".$recurlyAccount->account_code." does not exist in billings database");
		}
		$recurlyTransactions = Recurly_TransactionList::getForAccount($recurlyAccount->account_code);
		foreach ($recurlyTransactions as $recurlyTransaction) {
			$msg =
					"transaction : uuid=".$recurlyTransaction->uuid.
					", action=".$recurlyTransaction->action.
					", status=".$recurlyTransaction->status.
					", amount_in_cents=".$recurlyTransaction->amount_in_cents.
					", currency=".$recurlyTransaction->currency.
					", tax_in_cents=".$recurlyTransaction->tax_in_cents.
					", source=".$recurlyTransaction->source;
			if(isset($recurlyTransaction->invoice)) {
					$msg.= ", invoiceUid=".$recurlyTransaction->invoice->get()->uuid;
			}
			if($recurlyTransaction->source == 'subscription') {
				$msg.= ", subscriptionUid=".$recurlyTransaction->subscription->get()->uuid; 
			}
			$msg.= ", country=".$recurlyAccount->billing_info->get()->country;
			ScriptsConfig::getLogger()->addInfo($msg);
		}
		ScriptsConfig::getLogger()->addInfo("importing charges and credits from recurly account with account_code=".$recurlyAccount->account_code." done successfully");
	}	
}

?>