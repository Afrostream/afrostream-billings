<?php

require_once __DIR__ . '/../../../config/config.php';

class WebHooksHander {
	
	
	public function __construct() {
	}
	
	public function doProcessWebHook(Recurly_PushNotification $ioRecurly_PushNotification) {
		switch ($notification->type) {
			case "new_subscription_notification" :
				doProcessNewSubscription($ioRecurly_PushNotification);
				break;
			case "updated_subscription_notification" :
				doProcesssUpdatedSubscription($ioRecurly_PushNotification);
				break;
			case "canceled_subscription_notification":
				doProcessCanceledSubscription($ioRecurly_PushNotification);
				break;
			case "expired_subscription_notification":
				doProcessExpiredSubscription($ioRecurly_PushNotification);
				break;
			case "renewed_subscription_notification":
				doProcessRenewedSubscription($ioRecurly_PushNotification);
				break;
			case "reactivated_account_notification":
				doProcessReactivatedAccount($ioRecurly_PushNotification);
				break;
			default :
				config::getLogger()->addWarning('notification type : '. $notification->type. ' is not yet implemented.');
				break;
		}
	}
	
	private function doProcessNewSubscription(Recurly_PushNotification $ioRecurly_PushNotification) {
		config::getLogger()->addInfo('Processing notification type '.$ioRecurly_PushNotification->type.'...');
		
		config::getLogger()->addInfo('Processing notification type ended successfully');
	}
	
	private function doProcesssUpdatedSubscription($ioRecurly_PushNotification) {
		config::getLogger()->addInfo('Processing notification type '.$ioRecurly_PushNotification->type.'...');
		
		config::getLogger()->addInfo('Processing notification type ended successfully');
	}
	private function doProcessCanceledSubscription($ioRecurly_PushNotification) {
		config::getLogger()->addInfo('Processing notification type '.$ioRecurly_PushNotification->type.'...');
		
		config::getLogger()->addInfo('Processing notification type ended successfully');
	}
	
	private function doProcessExpiredSubscription($ioRecurly_PushNotification) {
		config::getLogger()->addInfo('Processing notification type '.$ioRecurly_PushNotification->type.'...');
		
		config::getLogger()->addInfo('Processing notification type ended successfully');
	}
	
	private function doProcessRenewedSubscription($ioRecurly_PushNotification) {
		config::getLogger()->addInfo('Processing notification type '.$ioRecurly_PushNotification->type.'...');
		
		config::getLogger()->addInfo('Processing notification type ended successfully');
	}
	
	private function	doProcessReactivatedAccount($ioRecurly_PushNotification) {
		config::getLogger()->addInfo('Processing notification type '.$ioRecurly_PushNotification->type.'...');
		
		config::getLogger()->addInfo('Processing notification type ended successfully');
	}
	
}

?>