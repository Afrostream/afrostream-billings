<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class ProviderWebHooksHandler {
	
	protected $provider = NULL;
	protected $platform = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
		$this->platform = BillingPlatformDAO::getPlatformById($this->provider->getPlatformId());
	}
	
	public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook') {
		$msg = "unsupported feature - process webHook - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
}

?>