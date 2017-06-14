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
	
	protected $emailsEnabled;
	
	protected $maxRedemptionsByUser;
	
	protected $expiresDate = false;
	
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
	
	public function setDiscountType($discountType) {
		$this->discountType = $discountType;
	}
	
	public function getDiscountType() {
		return($this->discountType);
	}
	
	public function setAmountInCents($amountInCents) {
		$this->amountInCents = $amountInCents;
	}
	
	public function getAmountInCents() {
		return($this->amountInCents);
	}
	
	public function setCurrency($currency) {
		$this->currency	= $currency;
	}
	
	public function getCurrency() {
		return($this->currency);
	}
	
	public function setPercent($percent) {
		$this->percent = $percent;
	}
	
	public function getPercent() {
		return($this->percent);
	}
	
	public function setDiscountDuration($discountDuration) {
		$this->discountDuration = $discountDuration;
	}
	
	public function getDiscountDuration() {
		return($this->discountDuration);
	}
	
	public function setDiscountDurationUnit($discountDurationUnit) {
		$this->discountDurationUnit = $discountDurationUnit;
	}
	
	public function getDiscountDurationUnit() {
		return($this->discountDurationUnit);
	}
	
	public function setDiscountDurationLength($discountDurationLength) {
		$this->discountDurationLength = $discountDurationLength;
	}
	
	public function getDiscountDurationLength() {
		return($this->discountDurationLength);
	}
	
	public function setGeneratedMode($generatedMode) {
		$this->generatedMode = $generatedMode;
	}
	
	public function getGeneratedMode() {
		return($this->generatedMode);
	}
	
	public function setGeneratedCodeLength($generatedCodeLength) {
		$this->generatedCodeLength = $generatedCodeLength;
	}
	
	public function getGeneratedCodeLength() {
		return($this->generatedCodeLength);
	}
	
	public function setTotalNumber($totalNumber) {
		$this->totalNumber = $totalNumber;
	}
	
	public function getTotalNumber() {
		return($this->totalNumber);
	}
	
	public function setCouponsCampaignType(CouponCampaignType $couponsCampaignType) {
		$this->couponsCampaignType = $couponsCampaignType;
	}
	
	public function getCouponsCampaignType() {
		return($this->couponsCampaignType);
	}
		
	public function addTimeframe(CouponTimeframe $timeframe) {
		$this->timeframes[] = $timeframe->getValue();
	}
	
	public function getTimeframes() {
		return($this->timeframes);
	}
	
	public function setEmailsEnabled($emailsEnabled) {
		$this->emailsEnabled = $emailsEnabled;		
	}
	
	public function getEmailsEnabled() {
		return($this->emailsEnabled);
	}
	
	public function setMaxRedemptionsByUser($maxRedemptionsByUser) {
		$this->maxRedemptionsByUser = $maxRedemptionsByUser;
	}
	
	public function getMaxRedemptionsByUser() {
		return($this->maxRedemptionsByUser);
	}
	
	public function setExpiresDate(DateTime $expiresDate = NULL) {
		$this->expiresDate = $expiresDate;
	}
	
	public function getExpiresDate() {
		return($this->expiresDate);
	}
	
}

?>