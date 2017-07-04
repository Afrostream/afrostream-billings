<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class UpdateInternalPlanRequest extends ActionRequest {
	
	protected $internalPlanUuid = NULL;
	protected $internalplanOptsArray = array();
	protected $name = NULL;
	protected $description = NULL;
	protected $details = NULL;
	protected $isVisible = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setInternalPlanUuid($internalPlanUuid) {
		$this->internalPlanUuid = $internalPlanUuid;
	}
	
	public function getInternalPlanUuid() {
		return($this->internalPlanUuid);
	}
	
	public function setInternalPlanOptsArray(array $internalplanOptsArray) {
		$this->internalplanOptsArray = $internalplanOptsArray;
	}
	
	public function getInternalplanOptsArray() {
		return($this->internalplanOptsArray);
	}
	
	public function setName($str) {
		$this->name = $str;
	}
	
	public function getName() {
		return($this->name);
	}
	
	public function setDescription($str) {
		$this->description = $str;
	}
	
	public function getDescription() {
		return($this->description);
	}
	
	public function setDetails(array $details) {
		$this->details = $details;
	}
	
	public function getDetails() {
		return($this->details);
	}
	
	public function setIsVisible($bool) {
		$this->isVisible = $bool;
	}
	
	public function getIsVisible() {
		return($this->isVisible);
	}
	
}

?>