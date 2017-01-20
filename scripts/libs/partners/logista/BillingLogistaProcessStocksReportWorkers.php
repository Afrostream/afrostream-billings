<?php

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;

require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/BillingLogistaProcessStocksReport.php';

class BillingLogistaProcessStocksReportWorkers extends BillingsWorkers {
	
	private $processingType = 'logista_stocks_reporting';

	protected $partner;
	protected $billingLogistaProcessStocksReport;

	public function __construct() {
		parent::__construct();
		$this->partner = BillingPartnerDAO::getPartnerByName('logista');
		$this->billingLogistaProcessStocksReport = new BillingLogistaProcessStocksReport($this->partner);
	}
	
	public function doProcessStocksReports() {
		$starttime = microtime(true);
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay(NULL, $this->processingType, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo($this->processingType." bypassed - already done today -");
				return;
			}
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingType.'.hit');
		
			ScriptsConfig::getLogger()->addInfo($this->processingType." processing...");
		
			$processingLog = ProcessingLogDAO::addProcessingLog(NULL, $this->processingType);
			$filesystem = new Filesystem(new Ftp([
					'host' => getEnv('PARTNER_ORDERS_LOGISTA_FTP_HOST'),
					'username' => getEnv('PARTNER_ORDERS_LOGISTA_FTP_USER'),
					'password' => getEnv('PARTNER_ORDERS_LOGISTA_FTP_PWD')
			]));
			$fromLogistaDirFiles = $filesystem->listContents(getEnv('PARTNER_ORDERS_LOGISTA_FTP_FOLDER_IN'), false);
			ScriptsConfig::getLogger()->addError("listSize : ".count($fromLogistaDirFiles));
			$stocksReportBasename = getEnv('PARTNER_ORDERS_LOGISTA_REPORT_FILE_BASENAME').'_'.getEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_ID').'_'.'stocks'.'_';
			foreach($fromLogistaDirFiles as $fromLogistaDirFile) {
				$filename = $fromLogistaDirFile['basename'];
				ScriptsConfig::getLogger()->addError(var_export($fromLogistaDirFile, true));
				if(substr($filename, 0, strlen($stocksReportBasename)) === $stocksReportBasename) {
					$this->doProcessStocksReport($fromLogistaDirFile);
				}
			}
			//DONE
			$processingLog->setProcessingStatus('done');
			ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			ScriptsConfig::getLogger()->addInfo($this->processingType." processing done successfully");
			$processingLog = NULL;
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingType.'.success');
		} catch(Exception $e) {
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingType.'.error');
			$msg = "an error occurred while processing ".$this->processingType.", message=".$e->getMessage();
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
	
	private function doProcessStocksReport(array $fromLogistaDirFile) {
		$filesystem = new Filesystem(new Ftp([
				'host' => getEnv('PARTNER_ORDERS_LOGISTA_FTP_HOST'),
				'username' => getEnv('PARTNER_ORDERS_LOGISTA_FTP_USER'),
				'password' => getEnv('PARTNER_ORDERS_LOGISTA_FTP_PWD')
		]));
		$fromPath = $fromLogistaDirFile['dirname'].'/'.$fromLogistaDirFile['basename'];
		$toProcessingPath = $fromLogistaDirFile['dirname'].'/'.'processing'.'/'.$fromLogistaDirFile['basename'];
		$toProcessedPath = $fromLogistaDirFile['dirname'].'/'.'processed'.'/'.$fromLogistaDirFile['basename'];
		if($filesystem->rename($fromPath, $toProcessingPath) != true) {
			throw new Exception("file cannot be moved");
		}
		$stream = $filesystem->readStream($toProcessingPath);
		$contents = stream_get_contents($stream);
		fclose($stream);
		$stream = NULL;
		$stocks_report_file_path = NULL;
		if(($stocks_report_file_path = tempnam('', 'tmp')) === false) {
			throw new Exception('file cannot be created');
		}
		$stocks_report_file_res = NULL;
		if(($stocks_report_file_res = fopen($stocks_report_file_path, 'w')) === false) {
			throw new Exception('file cannot be opened for writing');
		}
		fwrite($stocks_report_file_res, $contents);
		fclose($stocks_report_file_res);
		$stocks_report_file_res = NULL;
		$this->billingLogistaProcessStocksReport->doProcess($stocks_report_file_path);
		unlink($stocks_report_file_path);
		$stocks_report_file_path = NULL;
		//done
		if($filesystem->rename($toProcessingPath, $toProcessedPath) != true) {
			throw new Exception("file cannot be moved");
		}
	}
	
}
	
?>