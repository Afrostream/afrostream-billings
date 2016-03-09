<?php

class AfrUser {
	
	private $_id;
	private $email;
	private $billing_provider;
	private $account_code;
	private $first_name;
	private $last_name;
	
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
	
	public function getFirstName() {
		return($this->first_name);
	}
	
	public function setFirstName($str) {
		$this->first_name = $str;
	}
	
	public function getLastName() {
		return($this->last_name);
	}
	
	public function setLastName($str) {
		$this->last_name = $str;
	}
	
}

class AfrUserDAO {

	private static $sfields = "_id, email, billing_provider, account_code, first_name, last_name";
	
	private static function getAfrUserFromRow($row) {
		$out = new AfrUser();
		$out->setId($row["_id"]);
		$out->setEmail($row["email"]);
		$out->setBillingProvider($row["billing_provider"]);
		$out->setAccountCode($row["account_code"]);
		$out->setFirstName($row["first_name"]);
		$out->setLastName($row["last_name"]);
		return($out);
	}
	
	public static function getAfrUsers($id = NULL, $limit = 0, $offset = 0) {
		$query = "SELECT ".self::$sfields." FROM \"Users\"";
		if(isset($id)) { $query.= " WHERE _id <= ".$id; }
		$query.= " ORDER BY _id DESC";//LAST USERS FIRST
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$result = pg_query_params(ScriptsConfig::getDbConn(), $query, array());
		$out = array();

		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getAfrUserFromRow($row));
		}
		// free result
		pg_free_result($result);

		return($out);
	}
	
	public static function getAfrUserByAccountCode($account_code) {
		$query = "SELECT ".self::$sfields." FROM \"Users\" WHERE account_code = $1";
		$result = pg_query_params(ScriptsConfig::getDbConn(), $query, array($account_code));
		$out = NULL;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getAfrUserFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getAfrUserById($id) {
		$query = "SELECT ".self::$sfields." FROM \"Users\" WHERE _id = $1";
		$result = pg_query_params(ScriptsConfig::getDbConn(), $query, array($id));
		$out = NULL;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out =  self::getAfrUserFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function updateFirstName(AfrUser $user) {
		$query = "UPDATE \"Users\" SET first_name = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$user->getFirstName(),
						$user->getId()));
		return(self::getAfrUserById($user->getId()));	
	}
	
	public static function updateLastName(AfrUser $user) {
		$query = "UPDATE \"Users\" SET last_name = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$user->getLastName(),
						$user->getId()));
		return(self::getAfrUserById($user->getId()));		
	}
}

?>