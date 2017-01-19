<?php

class LogistaSalesReportLoader {
	
	private $salesReportFilePath;
	private $csvDelimiter = ';';
	//From line 1, RecordType = D
	private $productionDate;
	private $saleRecords = array();
	//From lastLine, RecordType = E
	private $hasChecksumRecord = false;
	private $numberOfRecords;
	
	public function __construct($salesReportFilePath) {
		$this->salesReportFilePath = $salesReportFilePath;
		$this->loadFile();
		$this->checkConsistency();
	}
	
	private function loadFile() {
		$salesReportFileRes = NULL;
		if(($salesReportFileRes = fopen($this->salesReportFilePath, 'r')) === false) {
			throw new Exception("file cannot be opened for reading");
		}
		$lineNumber = 0;
		while(($fields = fgetcsv($salesReportFileRes, NULL, $this->csvDelimiter)) !== false) {
			if($lineNumber == 0) {
				$this->loadHeaderRecord($fields);
			} else {
				$this->loadLineRecord($fields);
			}
			//done
			$lineNumber++;
		}
		fclose($salesReportFileRes);
		$salesReportFileRes = NULL;
	}
	
	private function loadHeaderRecord(array $fields) {
		if(count($fields) < 2) {
			throw new Exception("Header record cannot be loaded, number of fields expected is >= 2, number of fields is : ".count($fields));
		}
		$recordType = $fields[0];
		if($recordType != 'E') {
			throw new Exception("Line record is not a header record type");
		}
		$productionDate = DateTime::createFromFormat('Ymd', $fields[1], new DateTimeZone(config::$timezone));
		if($productionDate === false) {
			throw new Exception("Header record cannot be loaded, production date cannot be parsed : ".$fields[1]);
		}
		$this->productionDate = $productionDate;
	}
	
	private function loadLineRecord(array $fields) {
		if(count($fields) < 1) {
			throw new Exception("Line record cannot be loaded, number of fields expected is >= 1, number of fields is : ".count($fields));
		}
		$recordType = $fields[0];
		if($recordType == 'S') {
			$this->loadSaleRecord($fields);
		} else if($recordType == 'E') {
			$this->loadChecksumRecord($fields);
		} else {
			throw new Exception("Line record, unknown record type : ".$recordType);
		}
	}
	
	private function loadSaleRecord(array $fields) {
		if(count($fields) < 3) {
			throw new Exception("Sale record cannot be loaded, number of fields expected is >= 3, number of fields is : ".count($fields));
		}
		$recordType = $fields[0];
		if($recordType != 'S') {
			throw new Exception("Line record is not a sale record type");
		}
		$saleRecord = new SaleRecord();
		$saleRecord->setSerialNumber($fields[1]);
		$saleRecordDate = DateTime::createFromFormat('YmdHis', $fields[2], new DateTimeZone(config::$timezone));
		if($saleRecordDate === false) {
			throw new Exception("Sale record cannot be loaded, Sale date cannot be parsed : ".$fields[2]);
		}
		$saleRecord->setSaleDate($saleRecordDate);
		if(count($fields) > 3) {
			$saleRecord->setCustomerId($fields[3]);
		}
		if(count($fields) > 4) {
			$saleRecord->setShopId($fields[4]);
		}
		if(count($fields) > 5) {
			$saleRecord->setZipCode($fields[5]);
		}
		if(count($fields) > 6) {
			$saleRecord->setCountry($fields[6]);
		}
		if(count($fields) > 7) {
			$saleRecord->setTimezoneDiff($fields[7]);
		}
		//done
		$this->saleRecords[] = $saleRecord;
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
		if(count($this->saleRecords) != $this->numberOfRecords) {
			throw new Exception("Number of records differ, checksum says : ".$this->numberOfRecords.", found : ".count($this->saleRecords));
		}
	}
	
	public function getSaleRecords() {
		return($this->saleRecords);
	}
	
}

class SaleRecord {
	
	private $serialNumber;
	private $saleDate;
	private $customerId;
	private $shopId;
	private $zipCode;
	private $country;
	private $timezoneDiff;
	
	public function setSerialNumber($str) {
		$this->serialNumber = $str;
	}
	
	public function getSerialNumber() {
		return($this->serialNumber);
	}
	
	public function setSaleDate(DateTime $date) {
		$this->saleDate = $date;
	}
	
	public function getSaleDate() {
		return($this->saleDate);
	}
	
	public function setCustomerId($str) {
		$this->customerId = $str;
	}
	
	public function getCustomerId() {
		return($this->customerId);
	}
	
	public function setShopId($str) {
		$this->shopId = $str;
	}
	
	public function getShopId() {
		return($this->shopId);
	}
	
	public function setZipCode($str) {
		$this->zipCode = $str;
	}
	
	public function getZipCode() {
		return($this->zipCode);
	}
	
	public function setCountry($str) {
		$this->country = $str;
	}
	
	public function getCountry() {
		return($this->country);
	}
	
	public function setTimezoneDiff($str) {
		$this->timezoneDiff = $str;
	}
	
	public function getTimezoneDiff() {
		return($this->timezoneDiff);
	}
	
}

?>