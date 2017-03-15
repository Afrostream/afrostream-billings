<?php

use GoCardlessPro\Client;
use GoCardlessPro\Resources\Customer;
use GoCardlessPro\Core\Paginator;

require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';


class BillingsSyncUsersDataFromGocardlessUsers {
	
	private $provider = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
	}
	
	public function doSyncUsersData() {
		try {
			ScriptsConfig::getLogger()->addInfo("syncing users data from gocardless...");
			//
			$client = new Client(array(
					'access_token' => $this->provider->getApiSecret(),
					'environment' => getEnv('GOCARDLESS_API_ENV')
			));
			//
			$paginator = $client->customers()->all(
						['params' =>
							[
							]
						]);
			//
			foreach ($paginator as $customer_entry) {
				try {
					$this->doSyncUserData($customer_entry);
				} catch (Exception $e) {
					ScriptsConfig::getLogger()->addError("unexpected exception while syncing user data from gocardless with id=".$customer_entry->id.", message=".$e->getMessage());
				}
			}
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("unexpected exception while syncing users data from gocardless, message=".$e->getMessage());
		}
		ScriptsConfig::getLogger()->addInfo("syncing users data from gocardless done");
	}
	
	public function doSyncUserData(Customer $customer_entry) {
		ScriptsConfig::getLogger()->addInfo("syncing users data from gocardless account with id=".$customer_entry->id."...");
		$user = UserDAO::getUserByUserProviderUuid($this->provider->getId(), $customer_entry->id);
		if($user == NULL) {
			throw new Exception("user with id=".$customer_entry->id." does not exist in billings database");
		}
		$afrUser = AfrUserDAO::getAfrUserById($user->getUserReferenceUuid());
		if($afrUser == NULL) {
			throw new Exception("user with userReferenceUuid=".$user->getUserReferenceUuid()." does not exist in afr database");
		}
		if(strlen(trim($customer_entry->given_name)) > 0) {
			if($afrUser->getFirstName() == NULL || strlen(trim($afrUser->getFirstName())) == 0) {
				//empty
				ScriptsConfig::getLogger()->addInfo("AfrUserId=".$afrUser->getId()." changing firstName=".$customer_entry->given_name."...");
				$afrUser->setFirstName($customer_entry->given_name);
				$afrUser = AfrUserDAO::updateFirstName($afrUser);
				ScriptsConfig::getLogger()->addInfo("AfrUserId=".$afrUser->getId()." changing firstName=".$customer_entry->given_name." done successfully");
			}
		}
		if(strlen(trim($customer_entry->family_name)) > 0) {
			if($afrUser->getLastName() == NULL || strlen(trim($afrUser->getLastName())) == 0) {
				//empty	
				ScriptsConfig::getLogger()->addInfo("AfrUserId=".$afrUser->getId()." changing lastName=".$customer_entry->family_name."...");
				$afrUser->setLastName($customer_entry->family_name);
				$afrUser = AfrUserDAO::updateLastName($afrUser);
				ScriptsConfig::getLogger()->addInfo("AfrUserId=".$afrUser->getId()." changing lastName=".$customer_entry->family_name." done successfully");
			}
		}
		ScriptsConfig::getLogger()->addInfo("syncing users data from gocardless account with account_code=".$customer_entry->id." done successfully");
	}
	
}