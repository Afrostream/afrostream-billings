<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class ImportTransactionsRequest extends ActionRequest {
	
	protected $providerName = NULL;
	protected $uploadedFile = NULL;
	protected $fileType = 'salesreport';
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setProviderName($providerName) {
		$this->providerName = $providerName;
	}
	
	public function getProviderName() {
		return($this->providerName);
	}
	
	public function setUploadedFile($filepath) {
		$this->uploadedFile = $filepath;
	}
	
	public function getUploadedFile() {
		return($this->uploadedFile);
	}
	
	public function setFileType($fileType) {
		$this->fileType = $fileType;
	}
	
	public function getFileType() {
		return($this->fileType);
	}
	
}

?>