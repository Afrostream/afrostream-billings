<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class CreateInternalCouponsCampaignRequest extends ActionRequest {
	
	protected $name;
	protected $description;
	protected $prefix;
	protected $discountType;// percent / amount
	
	protected $amountInCents;// null OR > 0
	protected $currency;// null OR currency (iso)
	
	protected $percent;// null OR > 0
	
	protected $discountDuration;// once / repeating / forever
	
	protected $discountDurationUnit;// null / month / year
	protected $discountDurationLength;// null OR > 0
	
	protected $generatedMode;// single / bulk
	
	protected $generatedCodeLength;// null OR > 0
	
	protected $totalNumber;// null OR > 0
	
	protected $couponsCampaignType;// promo / prepaid / sponsorship
	
	protected $timeframes = array();// onSubCreation / onSubLifetime
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setName($name) {
		$this->name = $name;
	}
	
	public function getName() {
		return($this->name);
	}
	
	public function setDescription($description) {
		$this->description = $description;
	}
	
	public function getDescription() {
		return($this->description);
	}
	
	public function setPrefix($prefix) {
		$this->prefix = $prefix;
	}
	
	public function getPrefix() {
		return($this->prefix);
	}
	
	public function setAmountInCents($amountInCents) {
		$this->amountInCents = $amountInCents;
	}
	
	public function getAmountInCents() {
		return($this->amountInCents);
	}
	
}

?>