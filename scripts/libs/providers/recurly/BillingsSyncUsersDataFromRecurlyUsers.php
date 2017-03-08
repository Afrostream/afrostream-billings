<?php

require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';

class BillingsSyncUsersDataFromRecurlyUsers {
	
	private $provider = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
	}
	
	public function doSyncUsersData() {
		try {
			ScriptsConfig::getLogger()->addInfo("syncing users data from recurly...");
			//
			Recurly_Client::$subdomain = $this->provider->getMerchantId();
			Recurly_Client::$apiKey = $this->provider->getApiSecret();
			//
			$recurlyAccounts = Recurly_AccountList::getActive();
			
			foreach ($recurlyAccounts as $recurlyAccount) {
				try {
					$this->doSyncUserData($recurlyAccount);
				} catch (Exception $e) {
					ScriptsConfig::getLogger()->addError("unexpected exception while syncing user data from recurly with account_code=".$recurlyAccount->account_code.", message=".$e->getMessage());
				}
			}
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("unexpected exception while syncing users data from recurly, message=".$e->getMessage());
		}
		ScriptsConfig::getLogger()->addInfo("syncing users data from recurly done");
		
	}
	
	public function doSyncUserData(Recurly_Account $recurlyAccount) {
		ScriptsConfig::getLogger()->addInfo("syncing users data from recurly account with account_code=".$recurlyAccount->account_code."...");
		$user = UserDAO::getUserByUserProviderUuid($this->provider->getId(), $recurlyAccount->account_code);
		if($user == NULL) {
			throw new Exception("user with account_code=".$recurlyAccount->account_code." does not exist in billings database");
		}
		$afrUser = AfrUserDAO::getAfrUserById($user->getUserReferenceUuid());
		if($afrUser == NULL) {
			throw new Exception("user with userReferenceUuid=".$user->getUserReferenceUuid()." does not exist in afr database");
		}
		if(isset($recurlyAccount->first_name)
				&&
		strlen(trim($recurlyAccount->first_name)) > 0) {
			if($afrUser->getFirstName() == NULL || strlen(trim($afrUser->getFirstName())) == 0) {
				//empty
				ScriptsConfig::getLogger()->addInfo("AfrUserId=".$afrUser->getId()." changing firstName=".$recurlyAccount->first_name."...");
				$afrUser->setFirstName($recurlyAccount->first_name);
				$afrUser = AfrUserDAO::updateFirstName($afrUser);
				ScriptsConfig::getLogger()->addInfo("AfrUserId=".$afrUser->getId()." changing firstName=".$recurlyAccount->first_name." done successfully");
			}
		}
		if(isset($recurlyAccount->last_name)
				&&
		strlen(trim($recurlyAccount->last_name)) > 0) {
			if($afrUser->getLastName() == NULL || strlen(trim($afrUser->getLastName())) == 0) {
				//empty	
				ScriptsConfig::getLogger()->addInfo("AfrUserId=".$afrUser->getId()." changing lastName=".$recurlyAccount->last_name."...");
				$afrUser->setLastName($recurlyAccount->last_name);
				$afrUser = AfrUserDAO::updateLastName($afrUser);
				ScriptsConfig::getLogger()->addInfo("AfrUserId=".$afrUser->getId()." changing lastName=".$recurlyAccount->last_name." done successfully");
			}
		}
		ScriptsConfig::getLogger()->addInfo("syncing users data from recurly account with account_code=".$recurlyAccount->account_code." done successfully");
	}
	
}