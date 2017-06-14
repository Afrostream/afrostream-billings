<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class UpdateInternalCouponsCampaignRequest extends ActionRequest {
	
	protected $couponsCampaignInternalBillingUuid = NULL;
	protected $name = NULL;
	protected $description = NULL;
	protected $emailsEnabled = NULL;
	protected $timeframes = NULL;// onSubCreation / onSubLifetime
	protected $maxRedemptionsByUser = NULL;
	protected $totalNumber = NULL;
	protected $generatedCodeLength = NULL;
	protected $expiresDate = false;
	//TODO : LATER
	//userNotificationsEnabled
		
	public function __construct() {
		parent::__construct();
	}
	
	public function setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid) {
		$this->couponsCampaignInternalBillingUuid = $couponsCampaignInternalBillingUuid;
	}
	
	public function getCouponsCampaignInternalBillingUuid() {
		return($this->couponsCampaignInternalBillingUuid);
	}
	
	public function setName($str) {
		$this->name = $str;
	}
	
	public function getName() {
		return($this->name);
	}
	
	public function setDescription($str) {
		$this->description = $str;
	}
	
	public function getDescription() {
		return($this->description);
	}
	
	public function setEmailsEnabled($bool) {
		$this->emailsEnabled = $bool;
	}
	
	public function getEmailsEnabled() {
		return($this->emailsEnabled);
	}
	
	public function addTimeframe(CouponTimeframe $timeframe) {
		if($this->timeframes == NULL) {
			$this->timeframes = array();
		}
		$this->timeframes[] = $timeframe->getValue();
	}
	
	public function getTimeframes() {
		return($this->timeframes);
	}
	
	public function setMaxRedemptionsByUser($maxRedemptionsByUser) {
		$this->maxRedemptionsByUser = $maxRedemptionsByUser;	
	}
	
	public function getMaxRedemptionsByUser() {
		return($this->maxRedemptionsByUser);
	}
	
	public function setTotalNumber($totalNumber) {
		$this->totalNumber = $totalNumber;
	}
	
	public function getTotalNumber() {
		return($this->totalNumber);
	}
	
	public function setGeneratedCodeLength($generatedCodeLength) {
		$this->generatedCodeLength = $generatedCodeLength;
	}
	
	public function getGeneratedCodeLength() {
		return($this->generatedCodeLength);
	}
	
	public function setExpiresDate(DateTime $expiresDate = NULL) {
		$this->expiresDate = $expiresDate;
	}
	
	public function getExpiresDate() {
		return($this->expiresDate);
	}
	
}

?>