<?php

require_once __DIR__ . '/config/config.php';

class BillingsApiClient {
	
	private $billingsApiUser = NULL;
	
	public function __construct() {
	}
	
	public function getBillingsApiUser() {
		if($this->billingsApiUser == NULL) {
			$this->billingsApiUser = new BillingsApiUser($this);
		}
		return($this->billingsApiUser);
	}
	
}

?>