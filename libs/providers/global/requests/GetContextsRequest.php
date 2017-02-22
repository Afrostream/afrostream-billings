<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetContextsRequest extends ActionRequest {
	
	private $contextCountry = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setContextCountry($contextCountry) {
		$this->contextCountry = $contextCountry;
	}
	
	public function getContextCountry() {
		return($this->contextCountry);
	}
	
}

?>