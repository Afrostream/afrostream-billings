<?php

require_once __DIR__ . '/../../db/dbGlobal.php';

class ActionRequest {

	protected $origin = 'api';//other possible values : hook, script, import, sync, etc...
	protected $platform = NULL;
	
	public function __construct() {
	}
	
	public function setOrigin($origin) {
		$this->origin = $origin;
	}
	
	public function getOrigin() {
		return($this->origin);
	}
	
	public function getPlatform() {
		if($this->platform == NULL) {
			if($this->origin == 'api') {
				$this->platform = BillingPlatformDAO::getPlatformById(getEnv('PLATFORM_DEFAULT_ID'));
			} else {
				throw new Exception("platform has not been correctly initialized");
			}
		}
		return($this->platform);
	}
	
	public function setPlatform(BillingPlatform $platform) {
		$this->platform = $platform;
	}
	
}

?>