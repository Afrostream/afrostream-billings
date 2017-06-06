<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

use Slim\Http\UploadedFile;

class ImportTransactionsRequest extends ActionRequest {
	
	protected $providerName = NULL;
	protected $uploadedFile = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setProviderName($providerName) {
		$this->providerName = $providerName;
	}
	
	public function getProviderName() {
		return($this->providerName);
	}
	
	public function setUploadedFile(UploadedFile $uploadFile) {
		$this->uploadedFile = $uploadFile;
	}
	
	public function getUploadedFile() {
		return($this->uploadedFile);
	}
	
}

?>