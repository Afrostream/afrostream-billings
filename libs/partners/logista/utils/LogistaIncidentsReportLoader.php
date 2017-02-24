<?php

//same Loader for Incidents And Destructions (same file format)
class LogistaIncidentsReportLoader {
	
	private $incidentsReportFilePath;
	private $csvDelimiter = ',';
	//From line 1
	private $productionDate;
	private $incidentRecords = array();
	//From lastLine, EOF
	private $hasChecksumRecord = false;
	private $numberOfRecords;
	
	public function __construct($incidentsReportFilePath) {
		$this->incidentsReportFilePath = $incidentsReportFilePath;
		$this->loadFile();
		$this->checkConsistency();
	}
	
	private function loadFile() {
		$incidentsReportFileRes = NULL;
		if(($incidentsReportFileRes = fopen($this->incidentsReportFilePath, 'r')) === false) {
			throw new Exception("file cannot be opened for reading");
		}
		$lineNumber = 0;
		while(($fields = fgetcsv($incidentsReportFileRes, NULL, $this->csvDelimiter)) !== false) {
			if($lineNumber == 0) {
				$this->loadHeaderRecord($fields);
			} else {
				$this->loadLineRecord($fields);
			}
			//done
			$lineNumber++;
		}
		fclose($incidentsReportFileRes);
		$incidentsReportFileRes = NULL;
	}
	
	private function loadHeaderRecord(array $fields) {
		if(count($fields) < 2) {
			throw new Exception("Header record cannot be loaded, number of fields expected is >= 2, number of fields is : ".count($fields));
		}
		$this->numberOfRecords = $fields[0];
		$productionDate = DateTime::createFromFormat('Ymd', $fields[1], new DateTimeZone(config::$timezone));
		if($productionDate === false) {
			throw new Exception("Header record cannot be loaded, production date cannot be parsed : ".$fields[1]);
		}
		$productionDate->setTime(0, 0, 0);
		$this->productionDate = $productionDate;
	}
	
	private function loadLineRecord(array $fields) {
		if(count($fields) < 1) {
			throw new Exception("Line record cannot be loaded, number of fields expected is >= 1, number of fields is : ".count($fields));
		}
		$field0 = $fields[0];
		if($field0 == 'EOF') {
			$this->loadChecksumRecord($fields);
		} else {
			$this->loadIncidentRecord($fields);
		}
	}
	
	private function loadIncidentRecord(array $fields) {
		if(count($fields) < 2) {
			throw new Exception("Incident record cannot be loaded, number of fields expected is >= 2, number of fields is : ".count($fields));
		}
		$incidentRecord = new IncidentRecord();
		$incidentRecord->setSerialNumber($fields[0]);
		$incidentRecord->setShopId($fields[1]);
		if(count($fields) > 2) {
			$incidentRecord->setRequestId($fields[2]);
		}
		//done
		$this->incidentRecords[] = $incidentRecord;
	}
	
	private function loadChecksumRecord(array $fields) {
		if(count($fields) < 1) {
			throw new Exception("Checksum record cannot be loaded, number of fields expected is >= 1, number of fields is : ".count($fields));
		}
		$fields0 = $fields[0];
		if($fields0 != 'EOF') {
			throw new Exception("Line record is not a checksum record type");
		}
		$this->hasChecksumRecord = true;
	}
	
	private function checkConsistency() {
		if(!$this->hasChecksumRecord) {
			throw new Exception("No checksum record found");
		}
		if(count($this->incidentRecords) != $this->numberOfRecords) {
			throw new Exception("Number of records differ, checksum says : ".$this->numberOfRecords.", found : ".count($this->incidentRecords));
		}
	}
	
	public function getIncidentRecords() {
		return($this->incidentRecords);
	}
	
}

class IncidentRecord {
	
	private $serialNumber;
	private $shopId;
	private $requestId;
	
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
	
	public function setRequestId($str) {
		$this->requestId = $str;	
	}
	
	public function getRequestId() {
		return($this->requestId);
	}
	
}

?>