<?php

class BillingsBachatWorkers {
	
	public function __construct() {
	}
	
	public function doSyncSubscriptions() {
		$provider_name = "bachat";
		
		$provider = ProviderDAO::getProviderByName($provider_name);
		
		if($provider == NULL) {
			$msg = "unknown provider named : ".$provider_name;
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//TODO
	}
	
	//TODO : syncOneSubscriptionOnly

	
}

?>