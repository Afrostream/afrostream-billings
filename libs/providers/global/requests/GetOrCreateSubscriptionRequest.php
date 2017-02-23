<?php

require_once __DIR__ . '/../../../global/requests/ActionRequest.php';

class GetOrCreateSubscriptionRequest extends ActionRequest {
	
	protected $userBillingUuid = NULL;
	protected $internalPlanUuid = NULL;
	protected $subscriptionProviderUuid = NULL;
	protected $billingInfoArray = array();
	protected $subOptsArray = array();
	
	public function __construct() {
		parent::__construct();
	}
	
	public function setUserBillingUuid($userBillingUuid) {
		$this->userBillingUuid = $userBillingUuid;
	}
	
	public function getUserBillingUuid() {
		return($this->userBillingUuid);
	}
	
	public function setInternalPlanUuid($internalPlanUuid) {
		$this->internalPlanUuid = $internalPlanUuid;
	}
	
	public function getInternalPlanUuid() {
		return($this->internalPlanUuid);	
	}
	
	public function setSubscriptionProviderUuid($subscriptionProviderUuid) {
		$this->subscriptionProviderUuid = $subscriptionProviderUuid;
	}
	
	public function getSubscriptionProviderUuid() {
		return($this->subscriptionProviderUuid);
	}
	
	public function setBillingInfoArray(array $billingInfoArray) {
		$this->billingInfoArray = $billingInfoArray;
	}
	
	public function getBillingInfoArray() {
		return($this->billingInfoArray);
	}
	
	public function setSubOptsArray(array $subOptsArray) {
		$this->subOptsArray = $subOptsArray;
	}
	
	public function getSubOptsArray() {
		return($this->subOptsArray);
	}
	
}

?>