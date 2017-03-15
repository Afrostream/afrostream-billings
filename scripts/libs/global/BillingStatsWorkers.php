<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../BillingsWorkers.php';
require_once __DIR__ . '/BillingStatsFactory.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';

class BillingStatsWorkers extends BillingsWorkers {
	
	private $platform;
	private $processingType = 'stats_generator';
	
	public function __construct(BillingPlatform $platform) {
		parent::__construct();
		$this->platform = $platform;
	}
	
	public function doGenerateStats(DateTime $from, DateTime $to) {
		$starttime = microtime(true);
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($this->platform->getId(), NULL, $this->processingType, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("generating stats bypassed - already done today -");
				return;
			}
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingType.'.hit');
			ScriptsConfig::getLogger()->addInfo("generating stats...");
			$processingLog = ProcessingLogDAO::addProcessingLog($this->platform->getId(), NULL, $this->processingType);
			//
			$providers = ProviderDAO::getProviders($this->platform->getId());
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
			$timingInMillis = round((microtime(true) - $starttime) * 1000);
			BillingStatsd::timing('route.scripts.workers.providers.global.workertype.'.$this->processingType.'.timing', $timingInMillis);
			if(isset($processingLog)) {
				ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			}
		}		
	}
	
}

?>