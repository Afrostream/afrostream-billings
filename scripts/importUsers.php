<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db/dbGlobal.php';
require_once __DIR__ . '/../client/BillingsApiClient.php';
require_once __DIR__ . '/../client/BillingsApiUsers.php';
require_once __DIR__ . '/../client/BillingsApiSubscriptions.php';
require_once __DIR__ . '/../libs/db/dbGlobal.php';
require_once __DIR__ . '/../libs/utils/utils.php';
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

$running = true;

while($running) {
	try {
		while(count($afrUsers = AfrUserDAO::getAfrUsers($limit, $offset)) > 0) {
			$offset = $offset + $limit;
			//
			foreach ($afrUsers as $afrUser) {
				$accountCode = $afrUser->getAccountCode();
				switch($afrUser->getBillingProvider()) {
					case 'celery' :
						if(isset($accountCode)) { 
							$celery_count++;
							//$billingsImportUsers->doImportCeleryUser($afrUser);
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
					default :
						throw new Exception("unknown BillingProvider : ".$afrUser->getBillingProvider());
						break;
				}
			}
		}
		$running = false;
	} catch(Exception $e) {
		ScriptsConfig::getLogger()->addError("unexpected exception, continuing, message=".$e->getMessage());
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
		$apiUser = $this->billingsApiClient->getBillingsApiUsers()->getUser($apiGetUserRequest);
		if($apiUser == NULL) {
			ScriptsConfig::getLogger()->addError("user does not exist, creating it...");
			$apiCreateUserRequest = new ApiCreateUserRequest();
			$apiCreateUserRequest->setProviderName("celery");
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
		} else {
			ScriptsConfig::getLogger()->addError("user does already exist");
		}
		ScriptsConfig::getLogger()->addError("userBillingUuid=".$apiUser['userBillingUuid']);
		
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
			ScriptsConfig::getLogger()->addError("no subscription found, will create one");
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
			$dbSubscription->setSubCollectionMode('manual');
			$dbSubscription->setUpdateType('import');
			$dbSubscription->setUpdateId(0);
			$dbSubscription->setDeleted('false');
			//
			$dbSubscription = BillingsSubscriptionDAO::addBillingsSubscription($dbSubscription);
			ScriptsConfig::getLogger()->addError("subscription created, SubscriptionBillingUuid=".$dbSubscription->getSubscriptionBillingUuid());
		} else if($numberOfSusbscriptions == 1) {
			ScriptsConfig::getLogger()->addError("nothing to do, already have a subscription");
		} else {
			ScriptsConfig::getLogger()->addError("nothing to do, already have subscriptions (".$numberOfSusbscriptions." in total");
		}
		//done
		ScriptsConfig::getLogger()->addError("importing a celery user done successfully");
	}
	
	public function doImportRecurlyUser(AfrUser $afrUser) {

		ScriptsConfig::getLogger()->addError("importing a recurly user...");
		//check if user exists
		$apiGetUserRequest = new ApiGetUserRequest();
		$apiGetUserRequest->setProviderName("recurly");
		$apiGetUserRequest->setUserReferenceUuid($afrUser->getId());
		ScriptsConfig::getLogger()->addError("checking if user exist");
		$apiUser = $this->billingsApiClient->getBillingsApiUsers()->getUser($apiGetUserRequest);
		if($apiUser == NULL) {
			ScriptsConfig::getLogger()->addError("user does not exist, creating it...");
			$apiCreateUserRequest = new ApiCreateUserRequest();
			$apiCreateUserRequest->setProviderName("recurly");
			$apiCreateUserRequest->setUserReferenceUuid($afrUser->getId());
			$apiCreateUserRequest->setUserProviderUuid($afrUser->getAccountCode());
			$apiCreateUserRequest->setUserOpts("email", $afrUser->getEmail());
			$apiCreateUserRequest->setUserOpts("firstName", "firstNameValue");
			$apiCreateUserRequest->setUserOpts("lastName", "lastNameValue");
			try {
				$apiUser = $this->billingsApiClient->getBillingsApiUsers()->createUser($apiCreateUserRequest);
			} catch(Exception $e) {
				ScriptsConfig::getLogger()->addError("could not create user, message=".$e->getMessage());
				ScriptsConfig::getLogger()->addError("importing a recurly user bypassed");
				return;
			}
			//user does now exist
			//check
			$apiUser = $this->billingsApiClient->getBillingsApiUsers()->getUser($apiGetUserRequest);
			if($apiUser == NULL) {
				throw new Exception("User should now exist !");
			}
		} else {
			ScriptsConfig::getLogger()->addError("user does already exist");
		}
		ScriptsConfig::getLogger()->addError("userBillingUuid=".$apiUser['userBillingUuid']);
		//update subscriptions
		$apiUpdateSubscriptionsRequest = new ApiUpdateSubscriptionsRequest();
		$apiUpdateSubscriptionsRequest->setUserBillingUuid($apiUser['userBillingUuid']);
		$apiSubscriptions = $this->billingsApiClient->getBillingsApiSubscriptions()->update($apiUpdateSubscriptionsRequest);
		if($apiSubscriptions == NULL) {
			ScriptsConfig::getLogger()->addError("userBillingUuid=".$apiUser['userBillingUuid'].", updated subscriptions found : 0");
		} else {
			ScriptsConfig::getLogger()->addError("userBillingUuid=".$apiUser['userBillingUuid'].", updated subscriptions found : ".count($apiSubscriptions));
		}
		//done
		ScriptsConfig::getLogger()->addError("importing a recurly user done successfully");
	}
}

?>