<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class CreateContextRequest extends ActionRequest {
	
	private $contextBillingUuid = NULL;
	private $contextCountry = NULL;
	private $name = NULL;
	private $description = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setContextBillingUuid($contextBillingUuid) {
		$this->contextBillingUuid = $contextBillingUuid;
	}
	
	public function getContextBillingUuid() {
		return($this->contextBillingUuid);
	}
	
	public function setContextCountry($contextCountry) {
		$this->contextCountry = $contextCountry;
	}
	
	public function getContextCountry() {
		return($this->contextCountry);
	}
	
	public function setName($name) {
		$this->name = $name;
	}
	
	public function getName() {
		return($this->name);
	}
	
	public function setDescription($description) {
		$this->description = $description;
	}
	
	public function getDescription() {
		return($this->description);
	}
	
}

?>