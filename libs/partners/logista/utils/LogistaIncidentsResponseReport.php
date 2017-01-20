<?php

class LogistaIncidentsResponseReport {
	
	private $csvDelimiter = ',';
	//From line 1
	private $incidentResponseRecords = array();
	
	public function addIncidentResponseRecord(IncidentResponseRecord $incidentResponseRecord) {
		$this->incidentResponseRecords[] = $incidentResponseRecord;
	}
	
	public function getIncidentResponseRecords() {
		return($this->incidentResponseRecords);
	}
	
}

class IncidentResponseRecord {
	
	private $recordType;
	private $serialNumber;
	private $shopId;
	private $response;
	private $creditNoteAmount;
	private $requestId;
	
	public function setRecordType($recordType) {
		$this->recordType = $recordType;
	}
	
	public function getRecordType() {
		return($this->recordType);
	}
	
	public function setSerialNumber($str) {
		$this->serialNumber = $str;
	}
	
	public function getSerialNumber() {
		return($this->serialNumber);
	}
	
	public function setShopId($str) {
		$this->shopId = $str;
	}
	
	public function getShopId() {
		return($this->shopId);
	}
	
	public function setResponse($str) {
		$this->response = $str;	
	}
	
	public function getReponse() {
		return($this->response);
	}
	
	public function setCreditNoteAmount($str) {
		$this->creditNoteAmount = $str;
	}
	
	public function getCreditNoteAmount() {
		return($this->creditNoteAmount);
	}
	
	public function setRequestId($str) {
		$this->requestId = $str;	
	}
	
	public function getRequestId() {
		retrurn($this->requestId);
	}
	
}

?>