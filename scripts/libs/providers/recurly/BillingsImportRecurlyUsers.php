<?php

require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';

class BillingsImportRecurlyUsers {
	
	private $provider = NULL;
	
	public function __construct() {
		$this->provider = ProviderDAO::getProviderByName('recurly');
	}
	
	function doImportRecurlyUsers() {
		try {
			ScriptsConfig::getLogger()->addInfo("checking Recurly users...");
			//
			Recurly_Client::$subdomain = $this->provider->getMerchantId();
			Recurly_Client::$apiKey = $this->provider->getApiSecret();
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
						$dbUser = UserDAO::getUserByUserProviderUuid($this->provider->getId(), $recurlyAccount->account_code);
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