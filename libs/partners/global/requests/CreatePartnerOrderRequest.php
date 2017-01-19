<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class CreatePartnerOrderRequest extends ActionRequest {
	
	private $partnerName;
	private $partnerOrderName;
	private $partnerOrderType;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setPartnerName($str) {
		$this->partnerName = $str;
	}
	
	public function getPartnerName() {
		return($this->partnerName);
	}
	
	public function setPartnerOrderName($str) {
		$this->partnerOrderName = $str;
	}
	
	public function getPartnerOrderName() {
		return($this->partnerOrderName);
	}
	
	public function setPartnerOrderType($str) {
		$this->partnerOrderType = $str;
	}
	
	public function getPartnerOrderType() {
		return($this->partnerOrderType);
	}
	
}

?>