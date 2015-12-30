<?php

require_once __DIR__ . '/config/config.php';

class BillingsApiClient {
	
	private $billingsApiUsers = NULL;
	private $billingsApiSubscriptions = NULL;
	
	public function __construct() {
	}
	
	public function getBillingsApiUsers() {
		if($this->billingsApiUsers == NULL) {
			$this->billingsApiUsers = new BillingsApiUsers($this);
		}
		return($this->billingsApiUsers);
	}
	
	public function getBillingsApiSubscriptions() {
		if($this->billingsApiSubscriptions == NULL) {
			$this->billingsApiSubscriptions = new BillingsApiSubscriptions($this);
		}
		return($this->billingsApiSubscriptions);
	}
	
}

?>