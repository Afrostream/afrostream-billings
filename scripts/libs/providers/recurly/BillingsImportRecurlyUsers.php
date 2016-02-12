<?php

require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';

class BillingsImportRecurlyUsers {
	
	function doImportRecurlyUsers() {
		try {
			ScriptsConfig::getLogger()->addInfo("checking Recurly users...");
			$provider = ProviderDAO::getProviderByName('recurly');
			if($provider == NULL) {
				//Exception
			}
			//
			Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
			Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
			//
			$recurlyAccounts = Recurly_AccountList::getActive();
			foreach ($recurlyAccounts as $recurlyAccount) {
				//CHECK IN AFRO DB
				$afrUser = AfrUserDAO::getAfrUserByAccountCode($recurlyAccount->account_code);
				if($afrUser == NULL) {
					//NOT FOUND
					ScriptsConfig::getLogger()->addError("NOT FOUND IN AFRO DB : Recurly Account with account_code=".$recurlyAccount->account_code);
				} else {
					//CHECK IN BILLINGS DB
					$dbUser = UserDAO::getUserByUserProviderUuid($provider->getId(), $recurlyAccount->account_code);
					if($dbUser == NULL) {
						//NOT FOUND
						ScriptsConfig::getLogger()->addError("NOT FOUND IN BILLINGS DB : Recurly Account with account_code=".$recurlyAccount->account_code);
					}
				}
			}
			ScriptsConfig::getLogger()->addInfo("checking Recurly users done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("an error occurred, error_code=".$e->getCode().", error_message=".$e->getMessage());
			throw $e;
		}
	}
}

?>