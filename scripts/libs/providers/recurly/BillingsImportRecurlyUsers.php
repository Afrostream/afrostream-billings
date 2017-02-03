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
				$msg = "unknown provider named : ".$provider_name;
				ScriptsConfig::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
			Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
			//
			$recurlyAccounts = Recurly_AccountList::getActive();//Recurly seems to give unactive accounts...
			foreach ($recurlyAccounts as $recurlyAccount) {
				//CHECK IF THE ACCOUNT IS ACTIVE
				if($this->recurlyAccountIsActive($recurlyAccount)) {
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
			}
			ScriptsConfig::getLogger()->addInfo("checking Recurly users done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("an error occurred, error_code=".$e->getCode().", error_message=".$e->getMessage());
			throw $e;
		}
	}
	
	protected function recurlyAccountIsActive(Recurly_account $recurlyAccount) {
		$subscriptions = Recurly_SubscriptionList::getForAccount($recurlyAccount->account_code);
		foreach ($subscriptions as $subscription) {
			if($subscription->state == 'active') {
				return(true);
			}
		}
		return(false);
	}
	
}

?>