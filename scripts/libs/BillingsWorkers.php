<?php

class BillingsWorkers {
	
	private static $timezone = "Europe/Paris";
	
	protected $today = NULL;
	
	public function __construct() {
		$this->today = new DateTime(NULL, new DateTimeZone(self::$timezone));
		$this->today->setTime(0, 0, 0);
	}
	
	protected static function hasProcessingStatus($processingLogs, $processing_status) {
		$has = false;
		foreach($processingLogs as $processingLog) {
			if($processingLog->getProcessingStatus() == $processing_status) {
				$has = true;
				break;
			}
		}
		return($has);
	}

}

?>