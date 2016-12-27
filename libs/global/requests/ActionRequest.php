<?php

class ActionRequest {

	private $isAnApiRequest = true;
	
	public function __construct() {
	}
	
	public function setIsAnApiRequest(boolean $isAnApiRequest) {
		$this->isAnApiRequest = $isAnApiRequest;
	}
	
	public function getIsAnApiRequest() {
		return($this->isAnApiRequest);
	}
	
}

?>