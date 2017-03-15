<?php

require_once __DIR__ . '/../../client/BillingsApiClient.php';
require_once __DIR__ . '/../../client/BillingsApiUsers.php';
require_once __DIR__ . '/../../client/BillingsApiSubscriptions.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/utils/utils.php';

class BillingsImportUsers {
	
	private $provider = NULL;
	private $billingsApiClient = NULL;
	
	public function __construct() {
		$this->billingsApiClient = new BillingsApiClient();
	}
	
	public function doImportRecurlyUser(AfrUser $afrUser) {
		try {
			ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId());
			//check if user exists
			$apiGetUserRequest = new ApiGetUserRequest();
			$apiGetUserRequest->setProviderName("recurly");
			$apiGetUserRequest->setUserReferenceUuid($afrUser->getId());
			ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", checking if user does exist...");
			$apiUser = $this->billingsApiClient->getBillingsApiUsers()->getUser($apiGetUserRequest);
			ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", checking if user does exist done : exists ? ".($apiUser != NULL ? 'true' : 'false'));
			if($apiUser == NULL) {
				ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", creating user...");
				$apiCreateUserRequest = new ApiCreateUserRequest();
				$apiCreateUserRequest->setProviderName("recurly");
				$apiCreateUserRequest->setUserReferenceUuid($afrUser->getId());
				$apiCreateUserRequest->setUserProviderUuid($afrUser->getAccountCode());
				$apiCreateUserRequest->setUserOpts("email", $afrUser->getEmail());
				$apiCreateUserRequest->setUserOpts("firstName", "firstNameValue");
				$apiCreateUserRequest->setUserOpts("lastName", "lastNameValue");
				$apiUser = $this->billingsApiClient->getBillingsApiUsers()->createUser($apiCreateUserRequest);
				//user does now exist
				//check
				$apiUser = $this->billingsApiClient->getBillingsApiUsers()->getUser($apiGetUserRequest);
				if($apiUser == NULL) {
					throw new Exception("User should now exist !");
				}
				ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", creating user done, userBillingUuid=".$apiUser['userBillingUuid']);
			} else {
				ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", user does already exist, userBillingUuid=".$apiUser['userBillingUuid']);
			}
			//get current subscriptions
			ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", getting existing subscriptions...");
			$apiGetSubscriptionsRequest = new ApiGetSubscriptionsRequest();
			$apiGetSubscriptionsRequest->setUserBillingUuid($apiUser['userBillingUuid']);
			$apiSubscriptions = $this->billingsApiClient->getBillingsApiSubscriptions()->getMulti($apiGetSubscriptionsRequest);
			$countSubscriptionsBeforeUpdate = 0;
			if($apiSubscriptions == NULL) {
				ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", existing subscriptions found : 0");
			} else {
				$countSubscriptionsBeforeUpdate = count($apiSubscriptions);
				ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", existing subscriptions found : ".$countSubscriptionsBeforeUpdate);
			}
			ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", getting existing subscriptions done successfully");
			//update subscriptions
			ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", updating subscriptions...");
			$apiUpdateSubscriptionsRequest = new ApiUpdateSubscriptionsRequest();
			$apiUpdateSubscriptionsRequest->setUserBillingUuid($apiUser['userBillingUuid']);
			$apiSubscriptions = $this->billingsApiClient->getBillingsApiSubscriptions()->update($apiUpdateSubscriptionsRequest);
			$countSubscriptionsAfterUpdate = 0;
			if($apiSubscriptions == NULL) {
				ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", updated subscriptions found : 0");
			} else {
				$countSubscriptionsAfterUpdate = count($apiSubscriptions);
				ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", updated subscriptions found : ".$countSubscriptionsAfterUpdate);
			}
			if($countSubscriptionsBeforeUpdate != $countSubscriptionsAfterUpdate) {
				ScriptsConfig::getLogger()->addWarning("importing a recurly user...id=".$afrUser->getId().", number of subscriptions were updated, before update=".$countSubscriptionsBeforeUpdate.", after update=".$countSubscriptionsAfterUpdate);
			} else {
				ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", number of subscriptions is the same (".$countSubscriptionsAfterUpdate.")");
			}
			ScriptsConfig::getLogger()->addInfo("importing a recurly user...id=".$afrUser->getId().", updating subscriptions done successfully");
			//done
			ScriptsConfig::getLogger()->addInfo("importing a recurly user done successfully, id=".$afrUser->getId());
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("importing a recurly user failed, id=".$afrUser->getId()." error_code=".$e->getCode().", error_message=".$e->getMessage());
			throw $e;
		}
	}
}

?>