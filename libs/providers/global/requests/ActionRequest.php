<?php

class ActionRequest {

	private $origin = 'api';//other possible values : hook, script, import, sync, etc...
	
	public function __construct() {
	}
	
	public function setOrigin($origin) {
		$this->origin = $origin;
	}
	
	public function getOrigin() {
		return($this->origin);
	}
	
}

?>