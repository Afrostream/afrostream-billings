<?php

require_once __DIR__ . '/../../config/config.php';

class BillingUsersInternalPlanChangeHandler {

	private $platform;
	private $notifyDaysAgoCounter = 35;
	private $changeDaysAgoCounter = 7;

	public function __construct(BillingPlatform $platform) {
		$this->platform = $platform;
	}
	
	public function notifyUsersPlanChange($fromInternalPlanUuid, $toInternalPlanUuid) {
		try {
			
		} catch(Exception $e) {
			
		}
	}
	
	public function doUsersPlanChange($fromInternalPlanUuid, $toInternalPlanUuid) {
		
	}

}

?>