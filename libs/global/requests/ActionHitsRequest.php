<?php

require_once __DIR__ . '/../../db/dbGlobal.php';

class ActionHitsRequest {
	
	protected $offset = NULL;
	protected $limit = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setOffset($offset) {
		$this->offset = $offset;
	}
	
	public function getOffset() {
		return($this->offset);
	}
	
	public function setLimit($limit) {
		$this->limit = $limit;
	}
	
	public function getLimit() {
		return($this->limit);
	}
	
}

?>