<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../../libs/db/dbExports.php';
require_once __DIR__ . '/BillingGlobalStats.php';
require_once __DIR__ . '/../providers/orange/BillingOrangeStats.php';
require_once __DIR__ . '/../providers/bouygues/BillingBouyguesStats.php';

class BillingStatsFactory {
	
	private function __construct() {
	}
	
	public static function getBillingStats(Provider $provider) {
		$out = NULL;
		switch($provider->getName()) {
			case 'orange' :
				$out = new BillingOrangeStats($provider);
				break;
			case 'bouygues' :
				$out = new BillingBouyguesStats($provider);
				break;
			default :
				$out = new BillingGlobalStats($provider);
				break;
		}
		return($out);
	}
	
}

?>