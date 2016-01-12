<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../subscriptions/BachatSubscriptionsHandler.php';

class BachatWebHooksHandler {
	
	public function __construct() {
	}
		
	public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing bachat webHook with id=".$billingsWebHook->getId()."...");
			//TODO
			$this->doProcessNotification($notification, $update_type, $billingsWebHook->getId());
			config::getLogger()->addInfo("processing bachat webHook with id=".$billingsWebHook->getId()." done successully");
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing bachat webHook with id=".$billingsWebHook->getId().", message=".$e->getMessage();
			config::getLogger()->addError("processing bachat webHook with id=".$billingsWebHook->getId()." failed : ". $msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
		
}

?>