<?php

require_once __DIR__ . '/../../client/BillingsApiClient.php';
require_once __DIR__ . '/../../client/BillingsApiSubscriptions.php';
require_once __DIR__ . '/../../client/BillingsApiClient.php';
require_once __DIR__ . '/../../libs/db/dbGlobal.php';

class BillingsUpdateSubscriptions {
	
	private $billingsApiClient = NULL;
	
	public function __construct() {
		$this->billingsApiClient = new BillingsApiClient();
	}
	
	public function doUpdateSubscriptions(User $user) {
		try {
			ScriptsConfig::getLogger()->addInfo("updating subscriptions for user id=".$user->getId()."...");
			$apiUpdateSubscriptionsRequest = new ApiUpdateSubscriptionsRequest();
			$apiUpdateSubscriptionsRequest->setUserBillingUuid($user->getUserBillingUuid());
			$apiSubscriptions = $this->billingsApiClient->getBillingsApiSubscriptions()->update($apiUpdateSubscriptionsRequest);
			if($apiSubscriptions == NULL) {
				ScriptsConfig::getLogger()->addInfo("updating subscriptions for user id=".$user->getId().", updated subscriptions found : 0");
			} else {
				ScriptsConfig::getLogger()->addInfo("updating subscriptions for user id=".$user->getId().", updated subscriptions found : ".count($apiSubscriptions));
			}
			//done
			ScriptsConfig::getLogger()->addInfo("updating subscriptions for user id=".$user->getId()." done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("updating subscriptions fo user id=".$user->getId()." failed, error_code=".$e->getCode().", error_message=".$e->getMessage());
			throw $e;
		}
	}
	
}

?>