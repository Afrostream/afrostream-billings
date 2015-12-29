<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db/dbGlobal.php';
require_once __DIR__ . '/../client/BillingsApiClient.php';
require_once __DIR__ . '/../client/BillingsApiUsers.php';

/*
 * Tool to import users from Afrostream DB
 */

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

$providers = array('all', 'celery', 'recurly');

$providerName = 'all';

if(isset($_GET["-providerName"])) {
	$providerName = $_GET["-providerName"];
	if(!in_array($providerName, $providers)) {
		die("-providerName must be one of follows : ".print_r($providers, true));
	}
}

$billingsImportUsers = new BillingsImportUsers();

$limit = 1000;
$offset = 0;

$celery_count = 0;
$recurly_count = 0;

while(count($afrUsers = AfrUserDAO::getAfrUsers($limit, $offset)) > 0) {
	$offset = $offset + $limit;
	//
	foreach ($afrUsers as $afrUser) {
		$accountCode = $afrUser->getAccountCode();
		switch($afrUser->getBillingProvider()) {
			case 'celery' :
				if(isset($accountCode)) { 
					$celery_count++;
					$billingsImportUsers->doImportCeleryUser($afrUser);
				}
				break;
			case 'recurly' :
				if(isset($accountCode)) {
					$recurly_count++;
					$billingsImportUsers->doImportRecurlyUser($afrUser);
				}
				break;
			case '' :
				//same as recurly
				if(isset($accountCode)) {
					$recurly_count++;
					$billingsImportUsers->doImportRecurlyUser($afrUser);
				}
				break;
		}
	}
}

class BillingsImportUsers {
	
	private $billingsApiClient = NULL;
	
	public function __construct() {
		$this->billingsApiClient = new BillingsApiClient();
	}
	
	public function doImportCeleryUser(AfrUser $afrUser) {
		ScriptsConfig::getLogger()->addError("importing a celery user...");
		//check if user exists
		$apiGetUserRequest = new ApiGetUserRequest();
		$apiGetUserRequest->setProviderName("celery");
		$apiGetUserRequest->setUserReferenceUuid($afrUser->getId());
		ScriptsConfig::getLogger()->addError("checking if user exist");
		$apiUser = $this->billingsApiClient->getBillingsApiUser()->getUser($apiGetUserRequest);
		if($apiUser == NULL) {
			ScriptsConfig::getLogger()->addError("user does not exist, creating it...");
			$apiCreateUserRequest = new ApiCreateUserRequest();
			$apiCreateUserRequest->setProviderName("celery");
			$apiCreateUserRequest->setUserReferenceUuid($afrUser->getId());
			$apiCreateUserRequest->setUserProviderUuid($afrUser->getAccountCode());
			$apiCreateUserRequest->setUserOpts("email", $afrUser->getEmail());
			$apiCreateUserRequest->setUserOpts("firstName", "firstNameValue");
			$apiCreateUserRequest->setUserOpts("lastName", "lastNameValue");
			$apiUser = $this->billingsApiClient->getBillingsApiUser()->createUser($apiCreateUserRequest);
			//user does now exist
			//check
			$apiUser = $this->billingsApiClient->getBillingsApiUser()->getUser($apiGetUserRequest);
			if($apiUser == NULL) {
				throw new Exception("User should now exist !");
			}
		} else {
			ScriptsConfig::getLogger()->addError("user does already exist");
		}
		ScriptsConfig::getLogger()->addError("userBillingUuid=".$apiUser['userBillingUuid']);
		
		//Directly in database
		$dbUser = UserDAO::getUserByUserBillingUuid($apiUser['userBillingUuid']);
		$billingsSubscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($dbUser->getId());
		$numberOfSusbscriptions = count($billingsSubscriptions); 
		if($numberOfSusbscriptions == 0) {
			ScriptsConfig::getLogger()->addError("no subscription found, will create one");
			$dbSubscription = new BillingsSubscription();
			//TODO :
			/*$db_subscription->setSubscriptionBillingUuid(guid());
			$db_subscription->setProviderId();
			$db_subscription->setUserId($dbUser->getId());
			$db_subscription->setPlanId();
			$db_subscription->setSubUid(guid());//random
			$db_subscription->setSubStatus('active');
			$db_subscription->setSubActivatedDate();
			$db_subscription->setSubPeriodStartedDate();
			$db_subscription->setSubPeriodEndsDate();
			$db_subscription->setUpdateType();
			$db_subscription->setUpdateId(0);
			$db_subscription->setDeleted('false');*/
			//
			BillingsSubscriptionDAO::addBillingsSubscription($subscription);
		} else if($numberOfSusbscriptions == 1) {
			ScriptsConfig::getLogger()->addError("nothing to do, already have a subscription");
		} else {
			ScriptsConfig::getLogger()->addError("nothing to do, already have subscriptions (".$numberOfSusbscriptions." in total");
		}
		ScriptsConfig::getLogger()->addError("importing a celery user done successfully");
	}
	
	public function doImportRecurlyUser(AfrUser $afrUser) {
		
	}
}


?>