<?php

class ActionRequest {

	protected $origin = 'api';//other possible values : hook, script, import, sync, etc...
	protected $platform = NULL; 
	
	public function __construct() {
		//$platform =
	}
	
	public function setOrigin($origin) {
		$this->origin = $origin;
	}
	
	public function getOrigin() {
		return($this->origin);
	}
	
}

?>