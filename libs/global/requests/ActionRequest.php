<?php

require_once __DIR__ . '/../../db/dbGlobal.php';

class ActionRequest {

	protected $origin = 'api';//other possible values : hook, script, import, sync, etc...
	protected $platform = NULL; 
	
	public function __construct() {
		$platform = BillingPlatformDAO::getPlatformById(1);/* 1 = www.afrostream.tv */
	}
	
	public function setOrigin($origin) {
		$this->origin = $origin;
	}
	
	public function getOrigin() {
		return($this->origin);
	}
	
	public function getPlatform() {
		return($this->platform);
	}
	
}

?>