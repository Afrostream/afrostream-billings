<?php

class UserDAO {
	
	public static function getUserById($id) {
		$query = "SELECT _id, email, billing_provider, account_code, active FROM \"Users\" WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new User();
			$out->setId($line["_id"]);
			$out->setEmail($line["email"]);
			$out->setBillingProvider($line["billing_provider"]);
			$out->setAccountCode($line["account_code"]);
			$out->setActive($line["active"]);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getUserByAccountCode($account_code) {
		$query = "SELECT _id, email, billing_provider, account_code, active FROM \"Users\" WHERE account_code = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($account_code));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new User();
			$out->setId($line["_id"]);
			$out->setEmail($line["email"]);
			$out->setBillingProvider($line["billing_provider"]);
			$out->setAccountCode($line["account_code"]);
			$out->setActive($line["active"]);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

class User {
	
	private $_id;
	private $email;
	private $billing_provider;
	private $account_code;
	private $active;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getEmail() {
		return($this->email);
	}
	
	public function setEmail($email) {
		$this->email = $email;
	}
	
	public function getBillingProvider() {
		return($this->billing_provider);
	}
	
	public function setBillingProvider($billing_provider) {
		$this->billing_provider = $billing_provider;
	}
	
	public function getAccountCode() {
		return($this->account_code);
	}
	
	public function setAccountCode($account_code) {
		$this->account_code = $account_code;
	}
	
	public function getActive() {
		return($this->active);
	}
	
	public function setActive($active) {
		$this->$active = $active;
	}
	
}

?>