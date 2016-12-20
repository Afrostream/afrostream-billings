<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../BillingsWorkers.php';
require_once __DIR__ . '/BillingStatsFactory.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';

class BillingStatsWorkers extends BillingsWorkers {
	
	private $processingType = 'stats_generator';
	
	public function __construct() {
		parent::__construct();
	}
	
	public function doGenerateStats(DateTime $from, DateTime $to) {
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay(NULL, $this->processingType, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("generating stats bypassed - already done today -");
				return;
			}
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingType.'.hit');
			ScriptsConfig::getLogger()->addInfo("generating stats...");
			$processingLog = ProcessingLogDAO::addProcessingLog(NULL, $this->processingType);
			//
			$providers = ProviderDAO::getProviders();
			foreach ($providers as $provider) {
				$providerBillingStats = BillingStatsFactory::getBillingStats($provider);
				$providerBillingStats->doUpdateStats($from, $to);
			}
			//DONE
			$processingLog->setProcessingStatus('done');
			ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			ScriptsConfig::getLogger()->addInfo("generating stats done successfully");
			$processingLog = NULL;
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingType.'.success');
		} catch(Exception $e) {
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingType.'.error');
			$msg = "an error occurred while generating stats, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($processingLog)) {
				$processingLog->setProcessingStatus('error');
				$processingLog->setMessage($msg);
			}
		} finally {
			if(isset($processingLog)) {
				ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			}
		}		
	}
	
}

?>