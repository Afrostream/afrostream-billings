<?php

require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/logista/BillingLogistaOrderTool.php';

class BillingOrderToolFactory {
	
	private function __construct() {
	}
	
	public static function getBillingOrderToolFactory(BillingPartner $partner) {
		$out = NULL;
		switch($partner->getName()) {
			case 'logista' :
				$out = new BillingLogistaOrderTool($partner);
				break;
			default :
				
				break;
		}
		return($out);
	}
	
}

?>