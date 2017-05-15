<?php

require_once __DIR__ . '/ActionRequest.php';

class ActionHitsRequest extends ActionRequest {
	
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