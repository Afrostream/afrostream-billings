<?php

require_once __DIR__ . '/../../client/BillingsApiClient.php';
require_once __DIR__ . '/../../client/BillingsApiUsers.php';
require_once __DIR__ . '/../../client/BillingsApiSubscriptions.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../libs/utils/utils.php';

class BillingsImportUsers {
	
	private $billingsApiClient = NULL;
	
	public function __construct() {
		$this->billingsApiClient = new BillingsApiClient();
	}
	
	public function doImportCeleryUser(AfrUser $afrUser) {
		try {
			ScriptsConfig::getLogger()->addInfo("importing a celery user...id=".$afrUser->getId());
			//check if user exists
			$apiGetUserRequest = new ApiGetUserRequest();
			$apiGetUserRequest->setProviderName("celery");
			$apiGetUserRequest->setUserReferenceUuid($afrUser->getId());
			ScriptsConfig::getLogger()->addInfo("importing a celery user...id=".$afrUser->getId().", checking if user does exist...");
			$apiUser = $this->billingsApiClient->getBillingsApiUsers()->getUser($apiGetUserRequest);
			ScriptsConfig::getLogger()->addInfo("importing a celery user...id=".$afrUser->getId().", checking if user does exist done : exists ? ".($apiUser != NULL ? 'true' : 'false'));
			if($apiUser == NULL) {
				ScriptsConfig::getLogger()->addInfo("importing a celery user...id=".$afrUser->getId().", creating user...");
				$apiCreateUserRequest = new ApiCreateUserRequest();
				$apiCreateUserRequest->setProviderName("celery");
				$apiCreateUserRequest->setUserReferenceUuid($afrUser->getId());
				$accountCode = $afrUser->getAccountCode();
				if(!isset($accountCode)) {
					$accountCode = "F_".guid();
				}
				$apiCreateUserRequest->setUserProviderUuid($accountCode);
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
				ScriptsConfig::getLogger()->addInfo("importing a celery user...id=".$afrUser->getId().", creating user done, userBillingUuid=".$apiUser['userBillingUuid']);
			} else {
				ScriptsConfig::getLogger()->addInfo("importing a celery user...id=".$afrUser->getId().", user does already exist, userBillingUuid=".$apiUser['userBillingUuid']);
			}
			ScriptsConfig::getLogger()->addInfo("importing a celery user...id=".$afrUser->getId().", checking if a subscription does exist...");
			//Directly in database
			$dbProvider = ProviderDAO::getProviderByName('celery');
			if($dbProvider == NULL) {
				throw new Exception("Provider named 'celery' not found");
			}
			$providerPlanUuid = 'afrostreamambassadeurs';
			$dbProviderPlan = PlanDAO::getPlanByUuid($dbProvider->getId(), $providerPlanUuid);
			if($dbProviderPlan == NULL) {
				throw new Exception("ProviderPlan named ".$providerPlanUuid." not found");
			}
			$dbUser = UserDAO::getUserByUserBillingUuid($apiUser['userBillingUuid']);
			if($dbUser == NULL) {
				throw new Exception("User userBillingUuid=".$apiUser['userBillingUuid']." not found");
			}
			$billingsSubscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($dbUser->getId());
			$numberOfSusbscriptions = count($billingsSubscriptions); 
			if($numberOfSusbscriptions == 0) {
				ScriptsConfig::getLogger()->addInfo("importing a celery user...id=".$afrUser->getId().", checking if a subscription does exist : none");
				ScriptsConfig::getLogger()->addInfo("importing a celery user...id=".$afrUser->getId().", creating a subscription...");
				$dbSubscription = new BillingsSubscription();
				$dbSubscription->setSubscriptionBillingUuid(guid());
				$dbSubscription->setProviderId($dbProvider->getId());
				$dbSubscription->setUserId($dbUser->getId());
				$dbSubscription->setPlanId($dbProviderPlan->getId());
				$dbSubscription->setSubUid(guid());
				$dbSubscription->setSubStatus('active');
				$dbSubscription->setSubActivatedDate(DateTime::createFromFormat(DateTime::ISO8601, '2015-09-01T00:00:00+0100'));
				$dbSubscription->setSubPeriodStartedDate(DateTime::createFromFormat(DateTime::ISO8601, '2015-09-01T00:00:00+0100'));
				$dbSubscription->setSubPeriodEndsDate(DateTime::createFromFormat(DateTime::ISO8601, '2016-09-01T00:00:00+0100'));
				$dbSubscription->setUpdateType('import');
				$dbSubscription->setUpdateId(0);
				$dbSubscription->setDeleted(false);
				//
				$dbSubscription = BillingsSubscriptionDAO::addBillingsSubscription($dbSubscription);
				ScriptsConfig::getLogger()->addInfo("importing a celery user...id=".$afrUser->getId().", creating a subscription done, subscriptionBillingUuid=".$dbSubscription->getSubscriptionBillingUuid());
			} else if($numberOfSusbscriptions == 1) {
				ScriptsConfig::getLogger()->addInfo("importing a celery user...id=".$afrUser->getId().", checking if a subscription does exist : already have : 1 in total");
			} else {
				ScriptsConfig::getLogger()->addInfo("importing a celery user...id=".$afrUser->getId().", checking if a subscription does exist : already have : ".$numberOfSusbscriptions." in total");
			}
			//done
			ScriptsConfig::getLogger()->addInfo("importing a celery user done successfully, id=".$afrUser->getId());
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("importing a celery user failed, id=".$afrUser->getId()." error_code=".$e->getCode().", error_message=".$e->getMessage());
			throw $e;
		}
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