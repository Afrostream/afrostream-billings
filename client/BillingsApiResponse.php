<?php

class BillingsApiResponse {
	
	private $response_as_json = NULL;
	private $objectName = NULL;
	
	public function __construct($response, $objectName) {
		$this->response = json_decode($response, true);
		$this->objectName = $objectName;
	}
	
	public function getStatus() {
		return($this->response['status']);
	}
	
	public function getStatusCode() {
		return($this->response['statusCode']);
	}
	
	public function getStatusMessage() {
		return($this->response['statusMessage']);
	}
	
	public function getObject() {
		return($this->response['response'][$this->objectName]);
	}
	
 }

?>