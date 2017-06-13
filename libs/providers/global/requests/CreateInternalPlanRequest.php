<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class CreateInternalPlanRequest extends ActionRequest {
	
	protected $internalPlanUuid = NULL;
	protected $name = NULL;
	protected $description = NULL;
	protected $amountInCents = NULL;
	protected $currency = NULL;
	protected $cycle = NULL;
	protected $periodUnit = NULL;
	protected $periodLength = NULL;
	protected $vatRate = NULL;
	protected $internalplanOptsArray = array();
	protected $trialEnabled = NULL;
	protected $trialPeriodLength = NULL;
	protected $trialPeriodUnit = NULL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setInternalPlanUuid($internalPlanUuid) {
		$this->internalPlanUuid = $internalPlanUuid;
	}
	
	public function getInternalPlanUuid() {
		return($this->internalPlanUuid);
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
	
	public function setAmountInCents($amountInCents) {
		$this->amountInCents = $amountInCents;
	}
	
	public function getAmountInCents() {
		return($this->amountInCents);
	}
	
	public function setCurrency($currency) {
		$this->currency = $currency;
	}
	
	public function getCurrency() {
		return($this->currency);
	}
	
	public function setCycle($cycle) {
		$this->cycle = $cycle;
	}
	
	public function getCycle() {
		return($this->cycle);
	}
	
	public function setPeriodUnit($periodUnit) {
		$this->periodUnit = $periodUnit;
	}
	
	public function getPeriodUnit() {
		return($this->periodUnit);
	}
	
	public function setPeriodLength($periodLength) {
		$this->periodLength = $periodLength;
	}
	
	public function getPeriodLength() {
		return($this->periodLength);
	}
	
	public function setVatRate($vatRate) {
		$this->vatRate = $vatRate;
	}
	
	public function getVateRate() {
		return($this->vatRate);
	}
	
	public function setInternalPlanOptsArray(array $internalplanOptsArray) {
		$this->internalplanOptsArray = $internalplanOptsArray;
	}
	
	public function getInternalplanOptsArray() {
		return($this->internalplanOptsArray);
	}
	
	public function setTrialEnabled($trialEnabled) {
		$this->trialEnabled = $trialEnabled;
	}
	
	public function getTrialEnabled() {
		return($this->trialEnabled);
	}
	
	public function setTrialPeriodLength($trialPeriodLength) {
		return($this->trialPeriodLength = $trialPeriodLength);
	}
	
	public function getTrialPeriodLength() {
		return($this->trialPeriodLength);
	}
	
	public function setTrialPeriodUnit($trialPeriodUnit) {
		$this->trialPeriodUnit = $trialPeriodUnit;
	}
	
	public function getTrialPeriodUnit() {
		return($this->trialPeriodUnit);
	}
	
}

?>