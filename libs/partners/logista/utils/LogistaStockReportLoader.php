<?php

class LogistaStockReportLoader {
	
	private $stockReportFilePath;
	private $csvDelimiter = ';';
	//From line 1, RecordType = D
	private $stockDate;
	private $stockRecords = array();
	//From lastLine, RecordType = E
	private $hasChecksumRecord = false;
	private $numberOfRecords;
	
	public function __construct($stockReportFilePath) {
		$this->stockReportFilePath = $stockReportFilePath;
		$this->loadFile();
		$this->checkConsistency();
	}
	
	private function loadFile() {
		$stockReportFileRes = NULL;
		if(($stockReportFileRes = fopen($this->stockReportFilePath, 'r')) === false) {
			throw new Exception("file cannot be opened for reading");
		}
		$lineNumber = 0;
		while(($fields = fgetcsv($stockReportFileRes, NULL, $this->csvDelimiter)) !== false) {
			if($lineNumber == 0) {
				$this->loadHeaderRecord($fields);
			} else {
				$this->loadLineRecord($fields);
			}
			//done
			$lineNumber++;
		}
		fclose($stockReportFileRes);
		$stockReportFileRes = NULL;
	}
	
	private function loadHeaderRecord(array $fields) {
		if(count($fields) < 2) {
			throw new Exception("Header record cannot be loaded, number of fields expected is >= 2, number of fields is : ".count($fields));
		}
		$recordType = $fields[0];
		if($recordType != 'E') {
			throw new Exception("Line record is not a header record type");
		}
		$stockDate = DateTime::createFromFormat('Ymd', $fields[1], new DateTimeZone(config::$timezone));
		if($stockDate === false) {
			throw new Exception("Header record cannot be loaded, stock date cannot be parsed : ".$fields[1]);
		}
		$this->stockDate = $stockDate;
	}
	
	private function loadLineRecord(array $fields) {
		if(count($fields) < 1) {
			throw new Exception("Line record cannot be loaded, number of fields expected is >= 1, number of fields is : ".count($fields));
		}
		$recordType = $fields[0];
		if($recordType == 'M') {
			$this->loadStockRecord($fields);
		} else if($recordType == 'E') {
			$this->loadChecksumRecord($fields);
		} else {
			throw new Exception("Line record, unknown record type : ".$recordType);
		}
	}
	
	private function loadStockRecord(array $fields) {
		if(count($fields) < 2) {
			throw new Exception("Stock record cannot be loaded, number of fields expected is >= 2, number of fields is : ".count($fields));
		}
		$recordType = $fields[0];
		if($recordType != 'M') {
			throw new Exception("Line record is not a stock record type");
		}
		$stockRecord = new StockRecord();
		$stockRecord->setSerialNumber($fields[1]);
		if(count($fields) > 2) {
			$stockRecord->setEAN($fields[2]);
		}
		//done
		$this->stockRecords[] = $stockRecord;
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
		if(count($this->stockRecords) != $this->numberOfRecords) {
			throw new Exception("Number of records differ, checksum says : ".$this->numberOfRecords.", found : ".count($this->stockRecords));
		}
	}
	
	public function getStockRecords() {
		return($this->stockRecords);
	}
	
}

class StockRecord {
	
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