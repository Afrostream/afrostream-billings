<?php

class LogistaStocksReportLoader {
	
	private $stocksReportFilePath;
	private $csvDelimiter = ';';
	//From line 1, RecordType = D
	private $stocksDate;
	private $stocksRecords = array();
	//From lastLine, RecordType = E
	private $hasChecksumRecord = false;
	private $numberOfRecords;
	
	public function __construct($stocksReportFilePath) {
		$this->stocksReportFilePath = $stocksReportFilePath;
		$this->loadFile();
		$this->checkConsistency();
	}
	
	private function loadFile() {
		$stocksReportFileRes = NULL;
		if(($stocksReportFileRes = fopen($this->stocksReportFilePath, 'r')) === false) {
			throw new Exception("file cannot be opened for reading");
		}
		$lineNumber = 0;
		while(($fields = fgetcsv($stocksReportFileRes, NULL, $this->csvDelimiter)) !== false) {
			if($lineNumber == 0) {
				$this->loadHeaderRecord($fields);
			} else {
				$this->loadLineRecord($fields);
			}
			//done
			$lineNumber++;
		}
		fclose($stocksReportFileRes);
		$stocksReportFileRes = NULL;
	}
	
	private function loadHeaderRecord(array $fields) {
		if(count($fields) < 2) {
			throw new Exception("Header record cannot be loaded, number of fields expected is >= 2, number of fields is : ".count($fields));
		}
		$recordType = $fields[0];
		if($recordType != 'D') {
			throw new Exception("Line record is not a header record type");
		}
		$stocksDate = DateTime::createFromFormat('Ymd', $fields[1], new DateTimeZone(config::$timezone));
		if($stocksDate === false) {
			throw new Exception("Header record cannot be loaded, stocks date cannot be parsed : ".$fields[1]);
		}
		$this->stocksDate = $stocksDate;
	}
	
	private function loadLineRecord(array $fields) {
		if(count($fields) < 1) {
			throw new Exception("Line record cannot be loaded, number of fields expected is >= 1, number of fields is : ".count($fields));
		}
		$recordType = $fields[0];
		if($recordType == 'M') {
			$this->loadStocksRecord($fields);
		} else if($recordType == 'E') {
			$this->loadChecksumRecord($fields);
		} else {
			throw new Exception("Line record, unknown record type : ".$recordType);
		}
	}
	
	private function loadStocksRecord(array $fields) {
		if(count($fields) < 2) {
			throw new Exception("Stocks record cannot be loaded, number of fields expected is >= 2, number of fields is : ".count($fields));
		}
		$recordType = $fields[0];
		if($recordType != 'M') {
			throw new Exception("Line record is not a stocks record type");
		}
		$stocksRecord = new StocksRecord();
		$stocksRecord->setSerialNumber($fields[1]);
		if(count($fields) > 2) {
			$stocksRecord->setEAN($fields[2]);
		}
		//done
		$this->stocksRecords[] = $stocksRecord;
	}
	
	private function loadChecksumRecord(array $fields) {
		if(count($fields) < 2) {
			throw new Exception("Checksum record cannot be loaded, number of fields expected is >= 2, number of fields is : ".count($fields));
		}
		$recordType = $fields[0];
		if($recordType != 'E') {
			throw new Exception("Line record is not a checksum record type");
		}
		$this->numberOfRecords = $fields[1];
		$this->hasChecksumRecord = true;
	}
	
	private function checkConsistency() {
		if(!$this->hasChecksumRecord) {
			throw new Exception("No checksum record found");
		}
		if(count($this->stocksRecords) != $this->numberOfRecords) {
			throw new Exception("Number of records differ, checksum says : ".$this->numberOfRecords.", found : ".count($this->stockRecords));
		}
	}
	
	public function getStocksRecords() {
		return($this->stocksRecords);
	}
	
}

class StocksRecord {
	
	private $serialNumber;
	private $ean;
	
	public function setSerialNumber($str) {
		$this->serialNumber = $str;
	}
	
	public function getSerialNumber() {
		return($this->serialNumber);
	}
	
	public function setEAN($str) {
		$this->ean = $str;
	}
	
	public function getEAN() {
		return($this->ean);
	}
	
}

?>