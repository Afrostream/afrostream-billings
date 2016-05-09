<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/InternalPlansHandler.php';
require_once __DIR__ . '/../subscriptions/SubscriptionsHandler.php';

class InternalPlansFilteredHandler extends InternalPlansHandler {
	
	public function __construct() {
		parent::__construct();
	}
	
	public function doGetInternalPlans($provider_name = NULL, $contextBillingUuid = NULL, $contextCountry = NULL, $isVisible = NULL, $country = NULL, $filtered_array = NULL) {
		$contextBillingUuid = $this->selectContextBillingUuid($contextBillingUuid, $filtered_array);
		$contextCountry = $this->selectContextCountry($contextCountry, $filtered_array);
		$internalPlans = parent::doGetInternalPlans($provider_name, $contextBillingUuid, $contextCountry, $isVisible, $country);
		$internalPlansFiltered = array();
		if(isset($filtered_array)) {
			$filterEnabled = false;
			if(array_key_exists('filterEnabled', $filtered_array)) {
				$filterEnabled = $filtered_array['filterEnabled'] === 'true' ? true : false;
			}
			if($filterEnabled === true) {
				foreach ($internalPlans as $internalPlan) {
					if(!$this->isFiltered($internalPlan, $filtered_array)) {
						$internalPlansFiltered[] = $internalPlan;
					}
				}
			} else {
				$internalPlansFiltered = $internalPlans;
			}
		} else {
			$internalPlansFiltered = $internalPlans;
		}
		return($internalPlansFiltered);
	}
	
	private function selectContextBillingUuid($currentContextBillingUuid = NULL, $filtered_array = NULL) {
		$contextBillingUuid = NULL;
		if(isset($currentContextBillingUuid)) {
			$contextBillingUuid = $currentContextBillingUuid;
			config::getLogger()->addInfo("contextBillingUuid set to : ".$contextBillingUuid);
		} else if(isset($filtered_array)) {
			$filterEnabled = false;
			if(array_key_exists('filterEnabled', $filtered_array)) {
				$filterEnabled = $filtered_array['filterEnabled'] === 'true' ? true : false;
			}
			if($filterEnabled === true) {
				$userReferenceUuid = NULL;
				if(array_key_exists('filterUserReferenceUuid', $filtered_array)) {
					$userReferenceUuid = $filtered_array['filterUserReferenceUuid'];
				}
				if(isset($userReferenceUuid)) {
					$subscriptionsHandler = new SubscriptionsHandler();
					$subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUserReferenceUuid($userReferenceUuid);
					if(count($subscriptions) == 0) {
						$contextBillingUuid = 'common';
						config::getLogger()->addInfo("contextBillingUuid set to ".$contextBillingUuid." because no subscription was found for userReferenceUuid=".$userReferenceUuid);
					} else {
						$lastSubscription = $subscriptions[0];
						if(	$lastSubscription->getSubStatus() == 'expired'
							&& 
							$lastSubscription->getSubExpiresDate() == $lastSubscription->getSubCanceledDate()) 
						{
							$contextBillingUuid = 'reactivation';
							config::getLogger()->addInfo("contextBillingUuid set to ".$contextBillingUuid." because last subscription expired because of failed payment for userReferenceUuid=".$userReferenceUuid);
						} else {
							$contextBillingUuid = 'returning';
							config::getLogger()->addInfo("contextBillingUuid set to ".$contextBillingUuid." because there is old subscriptions for userReferenceUuid=".$userReferenceUuid);
						}
					}
				} else {
					$contextBillingUuid = 'common';
					config::getLogger()->addInfo("contextBillingUuid set to ".$contextBillingUuid." because no userReferenceUuid was given");					
				}
			} else {
				config::getLogger()->addInfo("no contextBillingUuid, filter NOT enabled");
			}
		} else {
			config::getLogger()->addInfo("no contextBillingUuid");
		}
		return($contextBillingUuid);
	}

	private function selectContextCountry($currentContextCountry = NULL, $filtered_array = NULL) {
		$contextCountry = NULL;
		if(isset($currentContextCountry)) {
			$contextCountry = $currentContextCountry;
			config::getLogger()->addInfo("contextCountry set to : ".$contextCountry);
		} else if(isset($filtered_array)) {
			$filterEnabled = false;
			if(array_key_exists('filterEnabled', $filtered_array)) {
				$filterEnabled = $filtered_array['filterEnabled'] === 'true' ? true : false;
			}
			if($filterEnabled === true) {
				if(array_key_exists('filterCountry', $filtered_array)) {
					$contextCountry = $filtered_array['filterCountry'];
				}
			} else {
				config::getLogger()->addInfo("no contextCountry, filter NOT enabled");
			}
		} else {
			config::getLogger()->addInfo("no contextCountry");
		}
		return($contextCountry);
	}
	
	private function isFiltered(InternalPlan $internalPlan, array $filtered_array) {
		//no FILTER (for the moment)
		return(false);
	}

}

?>