<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../../libs/db/dbStats.php';
require_once __DIR__ . '/BillingStats.php';

class BillingGlobalStats extends BillingStats {
	
	protected function doGenerateStats(DateTime $from, DateTime $to) {
		$fromDate = clone $from;
		$toDate = clone $to;
		$out = array();
		$moreOneDay = new DateInterval("P1D");
		while($fromDate < $toDate) {
			$startingDay = clone $fromDate;
			$startingDay->setTime(0, 0, 0);
			$endingDay = clone $fromDate;
			$endingDay->setTime(23, 59, 59);
			//
			ScriptsConfig::getLogger()->addInfo("retrieving stats for provider ".$this->provider->getName().
					" - date=".$startingDay->format("Ymd")."...");
			//
			$data = new BillingStatsData();
			$data->setDate($startingDay);
			$data->setProviderId($this->provider->getId());
			//total
		 	$data->setSubsTotal(dbStats::getNumberOfActiveSubscriptions($endingDay, NULL, $this->provider->getId(), $this->provider->getPlatformId())['total']);
			//new
			$data->setSubsNew(dbStats::getNumberOfActivatedSubscriptions($startingDay, $this->provider->getId(), $this->provider->getPlatformId())['total']);
			//expired
			$data->setSubsExpired(dbStats::getNumberOfExpiredSubscriptions($startingDay, $endingDay, $this->provider->getId(), $this->provider->getPlatformId())['total']);
			//
			$out[] = $data;
			ScriptsConfig::getLogger()->addInfo("retrieved stats for provider ".$this->provider->getName().
					" - date=".$startingDay->format("Ymd").
					" : total=".$data->getSubsTotal().
					", new=".$data->getSubsNew().
					", expired=".$data->getSubsExpired());
			//DONE
			$fromDate = $fromDate->add($moreOneDay); 
		}
		return($out);
	}
	
}

?>