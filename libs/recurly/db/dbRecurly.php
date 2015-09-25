<?php

class BillingRecurlyWebHookDAO {
	
	public static function getBillingRecurlyWebHookById($id) {
		$query = "SELECT _id, post_data, processing_status, creation_date FROM billing_recurly_webhooks WHERE _id = $1";
		
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new BillingRecurlyWebHook();
			$out->setId($line["_id"]);
			$out->setPostData($line["post_data"]);
			$out->setProcessingStatus($line["processing_status"]);
			$out->setCreationDate($line["creation_date"]);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function addBillingRecurlyWebHook($post_data) {
		$query = "INSERT INTO billing_recurly_webhooks (post_data) VALUES ($1) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, array($post_data));
		$row = pg_fetch_row($result);
		return(self::getBillingRecurlyWebHookById($row[0]));
	}
	
	public static function updateProcessingStatusById($id, $status) {
		$query = "UPDATE billing_recurly_webhooks SET processing_status = $1 WHERE _id = $2";
		pg_query_params(config::getDbConn(), $query, array($status ,$id));
	}
}


class BillingRecurlyWebHook {
	
	private $_id;
	private $post_data;
	private $processing_status;
	private $creation_date;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getPostData() {
		return($this->post_data);
	}
	
	public function setPostData($post_data) {
		$this->post_data = $post_data;
	}
	
	public function getProcessingStatus() {
		return($this->processing_status);
	}
	
	public function setProcessingStatus($status) {
		$this->processing_status = $status;
	}
	
	public function getCreationDate() {
		return($this->creation_date);
	}
	
	public function setCreationDate($date) {
		$this->creation_date = $date;
	}
	
}

class BillingRecurlyWebHookLogDAO {
	
	public static function addBillingRecurlyWebHookLog($webhook_id) {
		$query = "INSERT INTO billing_recurly_webhook_logs (webhookid) VALUES ($1) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, array($webhook_id));
		$row = pg_fetch_row($result);
		return(self::getBillingRecurlyWebHookLogById($row[0]));
	}
	
	public static function updateBillingRecurlyWebHookLogProcessingStatus(BillingRecurlyWebHookLog $billingRecurlyWebHookLog) {
		$query = "UPDATE billing_recurly_webhook_logs SET processing_status = $1, ended_date = CURRENT_TIMESTAMP, message = $2 WHERE _id = $3";
		$result = pg_query_params(config::getDbConn(), $query, array($billingRecurlyWebHookLog->getProcessingStatus(), $billingRecurlyWebHookLog->getMessage(), $billingRecurlyWebHookLog->getId()));
		$row = pg_fetch_row($result);
		return(self::getBillingRecurlyWebHookLogById($row[0]));
	}
	
	public static function getBillingRecurlyWebHookLogById($id) {
		$query = "SELECT _id, webhookid, processing_status, started_date, ended_date, message FROM billing_recurly_webhook_logs WHERE _id = $1";
	
		$result = pg_query_params(config::getDbConn(), $query, array($id));
	
		$out = null;
	
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new BillingRecurlyWebHookLog();
			$out->setId($line["_id"]);
			$out->setWebHookId($line["webhookid"]);
			$out->setProcessingStatus($line["processing_status"]);
			$out->setStartedDate($line["started_date"]);
			$out->setEndedDate($line["ended_date"]);
			$out->setMessage($line["message"]);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
}

class BillingRecurlyWebHookLog {
	
	private $_id;
	private $webhook_id;
	private $processing_status;
	private $started_date;
	private $ended_date;
	private $message;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function setWebHookId($id) {
		$this->webhook_id = $id;
	}
	
	public function getWebHookId() {
		return($this->webhook_id);
	}
	
	public function getProcessingStatus() {
		return($this->processing_status);
	}
	
	public function setProcessingStatus($status) {
		$this->processing_status = $status;
	}
	
	public function getStartedDate() {
		return($this->started_date);
	}
	
	public function setStartedDate($date) {
		$this->started_date = $date;
	}
	
	public function getEndedDate() {
		return($this->ended_date);
	}
	
	public function setEndedDate($date) {
		$this->ended_date = $date;
	}
	
	public function getMessage() {
		return($this->message);
	}
	
	public function setMessage($msg) {
		$this->message = $msg;
	}
	
}

?>