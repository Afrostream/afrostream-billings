<?php

require_once __DIR__ . '/../../../libs/db/BillingStatsData.php';
require_once __DIR__ . '/../../../libs/db/BillingStatsDataDAO.php';

class BillingStats {
	
	protected $provider = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
	}
	
	public function doUpdateStats(DateTime $from, DateTime $to) {
		$datas = $this->doGenerateStats($from, $to);
		foreach ($datas as $data) {
			$this->doSaveStats($data);
		}
	}
	
	protected function doGenerateStats(DateTime $from, DateTime $to) {
		return(array());
	}
	
	protected function doSaveStats(BillingStatsData $data) {
		if(BillingStatsDataDAO::getBillingStatsData($data->getProviderId(), $data->getDate()) == NULL) {
			//ADD
			return(BillingStatsDataDAO::addBillingStatsData($data));
		} else {
			//UPDATE
			return(BillingStatsDataDAO::updateBillingStatsData($data));
		}
	}
	
}

?>