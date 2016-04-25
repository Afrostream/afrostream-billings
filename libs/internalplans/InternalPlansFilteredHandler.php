<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/InternalPlansHandler.php';
require_once __DIR__ . '/../subscriptions/SubscriptionsHandler.php';

class InternalPlansFilteredHandler extends InternalPlansHandler {
	
	public function __construct() {
		parent::__construct();
	}
	
	public function doGetInternalPlan($internalPlanUuid) {
		return(parent::doGetInternalPlan($internalPlanUuid));
	}
	
	public function doGetInternalPlans($provider_name = NULL, $contextBillingUuid = NULL, $isVisible = NULL, $filtered_array = NULL) {
		$contextBillingUuid = $this->selectContextBillingUuid($contextBillingUuid);
		$internalPlans = parent::doGetInternalPlans($provider_name, $contextBillingUuid, $isVisible);
		$internalPlansFiltered = array();
		//TODO : LATER
		/*if(isset($filtered_array)) {
			$filterEnabled = false;
			if(array_key_exists('filterEnabled', $filtered_array)) {
				$filterEnabled = (boolval($filtered_array['filterEnabled'])) == 1 ? true : false;
			}
			if($filterEnabled === true) {
				foreach ($internalPlans as $internalPlan) {
					//TODO	
				}
			} else {
				$internalPlansFiltered = $internalPlans;
			}
		} else {
			$internalPlansFiltered = $internalPlans;
		}*/
		$internalPlansFiltered = $internalPlans;
		return($internalPlansFiltered);
	}
	
	public function doCreate($internalPlanUuid,	$name, $description, $amount_in_cents, $currency, $cycle, $period_unit_str, $period_length, $internalplan_opts_array) {
		return(parent::doCreate($internalPlanUuid, $name, $description, $amount_in_cents, $currency, $cycle, $period_unit_str, $period_length, $internalplan_opts_array));
	}
	
	public function doAddToProvider($internalPlanUuid, Provider $provider) {
		return(parent::doAddToProvider($internalPlanUuid, $provider));
	}
	
	public function doUpdateInternalPlanOpts($internalPlanUuid, array $internalplan_opts_array) {
		return(parent::doUpdateInternalPlanOpts($internalPlanUuid, $internalplan_opts_array));
	}
	
	private function selectContextBillingUuid($currentContextBillingUuid = NULL, $filtered_array = NULL) {
		$contextBillingUuid = NULL;
		if(isset($currentContextBillingUuid)) {
			$contextBillingUuid = $currentContextBillingUuid;
		} 
		//TODO : LATER
		/*else if(isset($filtered_array)) {
			$userReferenceUuid = NULL;
			if(array_key_exists('filterUserReferenceUuid', $filtered_array)) {
				$userReferenceUuid = $filtered_array['filterUserReferenceUuid'];
			}
			if(isset($userReferenceUuid)) {
				$subscriptionsHandler = new SubscriptionsHandler();
				$subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUserReferenceUuid($userReferenceUuid);
				if(count($subscriptions) == 0) {
					$contextBillingUuid = 'common';
				} else {
					$contextBillingUuid = 'returning';
				}
			}
		}*/
		return($contextBillingUuid);
	}
}

?>