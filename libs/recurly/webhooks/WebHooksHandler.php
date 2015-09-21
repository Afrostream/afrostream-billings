<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../libs/recurly/db/dbRecurly.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';

class WebHooksHander {
	
	
	public function __construct() {
	}
	
	public function doSaveWebHook($post_data) {
		try {
			$billingRecurlyWebHook = BillingRecurlyWebHookDAO::addBillingRecurlyWebHook($post_data);
			config::getLogger()->addInfo("post_data saved successfully, id=".$billingRecurlyWebHook->getId());
			return($billingRecurlyWebHook);
		} catch (Exception $e) {
			config::getLogger()->addError("an error occurred while saving post_data, message=" . $e->getMessage());
		}
	}
	
	public function doProcessWebHook($id) {
		try {
			$billingRecurlyWebHook = BillingRecurlyWebHookDAO::getBillingRecurlyWebHookById($id);
			BillingRecurlyWebHookDAO::updateProcessingStatusById($id, 'running');
			$notification = new Recurly_PushNotification($billingRecurlyWebHook->getPostData());
			$this->doProcessNotification($notification);
			BillingRecurlyWebHookDAO::updateProcessingStatusById($id, 'done');
		} catch(Exception $e) {
			BillingRecurlyWebHookDAO::updateProcessingStatusById($id, 'error');
		}
	}
	
	private function doProcessNotification(Recurly_PushNotification $notification) {
		switch ($notification->type) {
			case "new_subscription_notification" :
				$this->doProcessNewSubscription($notification);
				break;
			case "updated_subscription_notification" :
				$this->doProcesssUpdatedSubscription($notification);
				break;
			case "canceled_subscription_notification":
				$this->doProcessCanceledSubscription($notification);
				break;
			case "expired_subscription_notification":
				$this->doProcessExpiredSubscription($notification);
				break;
			case "renewed_subscription_notification":
				$this->doProcessRenewedSubscription($notification);
				break;
			case "reactivated_account_notification":
				$this->doProcessReactivatedAccount($notification);
				break;
			default :
				config::getLogger()->addWarning('notification type : '. $notification->type. ' is not yet implemented.');
				break;
		}
	}
	
	private function doProcessNewSubscription(Recurly_PushNotification $notification) {

		config::getLogger()->addInfo('Processing notification type '.$notification->type.'...');
		
		$account_code = NULL;
		
		foreach ($notification->account->children() as $children) {
			if($children->getName() == 'account_code') {
				$account_code = $children;
				break;
			}
		}
		
		if($account_code == NULL) {
			//todo
		}
		
		config::getLogger()->addError('Searching user with account_code='.$account_code.'...');
		$user = UserDAO::getUserByAccountCode($account_code);
		if($user == NULL) {
			config::getLogger()->addError('Searching user with account_code='.$account_code.' failed, no user found');
		} else {
			config::getLogger()->addInfo('Searching user with account_code='.$account_code.' ended successfully. user_id='.$user->getId());
		}
		//
		
		//
		config::getLogger()->addInfo('Processing notification type '.$notification->type.' ended successfully');
	}
	
	private function doProcesssUpdatedSubscription(Recurly_PushNotification $notification) {
		config::getLogger()->addInfo('Processing notification type '.$notification->type.'...');
		
		config::getLogger()->addInfo('Processing notification type '.$notification->type.' ended successfully');
	}
	private function doProcessCanceledSubscription(Recurly_PushNotification $notification) {
		config::getLogger()->addInfo('Processing notification type '.$notification->type.'...');
		
		config::getLogger()->addInfo('Processing notification type '.$notification->type.' ended successfully');
	}
	
	private function doProcessExpiredSubscription(Recurly_PushNotification $notification) {
		config::getLogger()->addInfo('Processing notification type '.$notification->type.'...');
		
		$account_code = NULL;
		
		foreach ($notification->account->children() as $children) {
			if($children->getName() == 'account_code') {
				$account_code = $children;
				break;
			}
		}
		
		if($account_code == NULL) {
			//todo
		}
		
		config::getLogger()->addError('Searching user with account_code='.$account_code.'...');
		$user = UserDAO::getUserByAccountCode($account_code);
		if($user == NULL) {
			config::getLogger()->addError('Searching user with account_code='.$account_code.' failed, no user found');
		} else {
			config::getLogger()->addInfo('Searching user with account_code='.$account_code.' ended successfully. user_id='.$user->getId());
		}
		config::getLogger()->addInfo('Processing notification type '.$notification->type.' ended successfully');
	}
	
	private function doProcessRenewedSubscription(Recurly_PushNotification $notification) {
		config::getLogger()->addInfo('Processing notification type '.$notification->type.'...');
		
		config::getLogger()->addInfo('Processing notification type '.$notification->type.' ended successfully');
	}
	
	private function	doProcessReactivatedAccount(Recurly_PushNotification $notification) {
		config::getLogger()->addInfo('Processing notification type '.$notification->type.'...');
		
		config::getLogger()->addInfo('Processing notification type '.$notification->type.' ended successfully');
	}
	
}

?>