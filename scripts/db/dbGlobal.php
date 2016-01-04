<?php

class AfrUser {
	
	private $_id;
	private $email;
	private $billing_provider;
	private $account_code;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getEmail() {
		return($this->email);
	}
	
	public function setEmail($str) {
		$this->email = $str;
	}
	
	public function getBillingProvider() {
		return($this->billing_provider);
	}
	
	public function setBillingProvider($str) {
		$this->billing_provider = $str;
	}
	
	public function getAccountCode() {
		return($this->account_code);
	}
	
	public function setAccountCode($str) {
		$this->account_code = $str;
	}
	
}

class AfrUserDAO {

	public static function getAfrUsers($id = NULL, $limit = 0, $offset = 0) {
		$query = "SELECT _id, email, billing_provider, account_code FROM \"Users\"";
		if(isset($id)) { $query.= " WHERE _id <= ".$id; }
		$query.= " ORDER BY _id DESC";//LAST USERS FIRST
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$result = pg_query_params(ScriptsConfig::getDbConn(), $query, array());
		$out = array();

		while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$afrUser = new AfrUser();
			$afrUser->setId($line["_id"]);
			$afrUser->setEmail($line["email"]);
			$afrUser->setBillingProvider($line["billing_provider"]);
			$afrUser->setAccountCode($line["account_code"]);
			array_push($out, $afrUser);
		}
		// free result
		pg_free_result($result);

		return($out);
	}
	
}

?>