<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../global/webhooks/ProviderWebHooksHandler.php';

class BachatWebHooksHandler extends ProviderWebHooksHandler {
	
	public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing bachat webHook with id=".$billingsWebHook->getId()."...");
			config::getLogger()->addInfo("processing bachat webHook with id=".$billingsWebHook->getId()." nothing has to be done");
			config::getLogger()->addInfo("processing bachat webHook with id=".$billingsWebHook->getId()." done successfully");
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing bachat webHook with id=".$billingsWebHook->getId().", message=".$e->getMessage();
			config::getLogger()->addError("processing bachat webHook with id=".$billingsWebHook->getId()." failed : ". $msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
}

?>