<?php

class BillingStatsData {
	
	private $_id = NULL;
	private $date = NULL;
	private $providerid = NULL;
	private $subs_total = NULL;
	private $subs_new = NULL;
	private $subs_expired = NULL;
	
	public function __construct() {
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getId() {
		return($this->_id);
	}
	
	public function setDate(DateTime $date) {
		$this->date = $date;
	}
	
	public function getDate() {
		return($this->date);
	}
	
	public function setProviderId($id) {
		$this->providerid = $id;
	} 
	
	public function getProviderId() {
		return($this->providerid);
	}
	
	public function setSubsTotal($nb = NULL) {
		$this->subs_total = $nb;
	}
	
	public function getSubsTotal() {
		return($this->subs_total);
	}

	public function setSubsNew($nb = NULL) {
		$this->subs_new = $nb;
	}
	
	public function getSubsNew() {
		return($this->subs_new);
	}
	
	public function setSubsExpired($nb = NULL) {
		$this->subs_expired = $nb;
	}
	
	public function getSubsExpired() {
		return($this->subs_expired);
	}
	
}

?>