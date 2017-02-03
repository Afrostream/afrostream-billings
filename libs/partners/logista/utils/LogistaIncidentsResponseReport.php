<?php

class LogistaIncidentsResponseReport {
	
	private $csvDelimiter = ',';
	//From line 1
	private $productionDate;
	private $incidentResponseRecords = array();
	
	public function setProductionDate(DateTime $date) {
		$this->productionDate = $date;
	}
	
	public function getProductionDate() {
		return($this->productionDate);
	}
	
	public function addIncidentResponseRecord(IncidentResponseRecord $incidentResponseRecord) {
		$this->incidentResponseRecords[] = $incidentResponseRecord;
	}
	
	public function getIncidentResponseRecords() {
		return($this->incidentResponseRecords);
	}
	
	public function saveTo($incidents_response_report_file_path) {
		$incidents_response_report_file_res = NULL;
		if(($incidents_response_report_file_res = fopen($incidents_response_report_file_path, 'w')) === false) {
			throw new Exception('file cannot be opened for writing');
		}
		//Header Record
		$headerFields = array();
		$headerFields[] = 'D';
		$headerFields[] = $this->productionDate->format('Ymd');
		fputs($incidents_response_report_file_res, implode($this->csvDelimiter, $headerFields)."\r");
		//Serial Number Records
		foreach ($this->incidentResponseRecords as $incidentResponseRecord) {
			$serialNumberFields = array();
			$serialNumberFields[] = $incidentResponseRecord->getRecordType();
			$serialNumberFields[] = $incidentResponseRecord->getSerialNumber();
			$serialNumberFields[] = $incidentResponseRecord->getShopId();
			$serialNumberFields[] = $incidentResponseRecord->getResponse();
			$serialNumberFields[] = $incidentResponseRecord->getCreditNoteAmount();
			$serialNumberFields[] = $incidentResponseRecord->getRequestId();
			fputs($incidents_response_report_file_res, implode($this->csvDelimiter, $serialNumberFields)."\r");
		}
		//End File Record
		$totalAmount = 0;
		foreach ($this->incidentResponseRecords as $incidentResponseRecord) {
			$totalAmount += $incidentResponseRecord->getCreditNoteAmount();
		}
		$endFileFields = array();
		$endFileFields[] = 'END';
		$endFileFields[] = $totalAmount;
		$endFileFields[] = 'EUR';
		fputs($incidents_response_report_file_res, implode($this->csvDelimiter, $endFileFields)."\r");
		fclose($incidents_response_report_file_res);
		$incidents_response_report_file_res = NULL;
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
	
	public function getResponse() {
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
		return($this->requestId);
	}
	
}

?>