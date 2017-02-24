<?php

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;

require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/BillingLogistaProcessDestructionReport.php';

class BillingLogistaProcessDestructionReportWorkers extends BillingsWorkers {
	
	private $processingType = 'logista_destruction_reporting';

	protected $partner;
	protected $billingLogistaProcessDestructionReport;

	public function __construct() {
		parent::__construct();
		$this->partner = BillingPartnerDAO::getPartnerByName('logista');
		$this->billingLogistaProcessDestructionReport = new BillingLogistaProcessDestructionReport($this->partner);
	}
	
	public function doProcessDestructionReports() {
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
			$destructionReportBasename = getEnv('PARTNER_ORDERS_LOGISTA_REPORT_FILE_BASENAME').'_'.getEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_PREFIX').getEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_ID').'_'.'destruction'.'_';
			foreach($fromLogistaDirFiles as $fromLogistaDirFile) {
				$filename = $fromLogistaDirFile['basename'];
				if(substr($filename, 0, strlen($destructionReportBasename)) === $destructionReportBasename) {
					$this->doProcessDestructionReport($fromLogistaDirFile);
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
	
	private function doProcessDestructionReport(array $fromLogistaDirFile) {
		try {
			ScriptsConfig::getLogger()->addInfo("processing destruction report file : ".$fromLogistaDirFile['basename']."...");
			$now = new DateTime();
			$now->setTimezone(new DateTimeZone(config::$timezone));
			$filesystem = new Filesystem(new Ftp([
					'host' => getEnv('PARTNER_ORDERS_LOGISTA_FTP_HOST'),
					'username' => getEnv('PARTNER_ORDERS_LOGISTA_FTP_USER'),
					'password' => getEnv('PARTNER_ORDERS_LOGISTA_FTP_PWD')
			]));
			$fromPath = $fromLogistaDirFile['dirname'].'/'.$fromLogistaDirFile['basename'];
			$toProcessingPath = $fromLogistaDirFile['dirname'].'/'.'processing'.'/'.$fromLogistaDirFile['basename'];
			$toProcessedPath = $fromLogistaDirFile['dirname'].'/'.'processed'.'/'.$fromLogistaDirFile['basename'];
			$toLOGPath = getEnv('PARTNER_ORDERS_LOGISTA_FTP_FOLDER_OUT').'/';
			$toLOGPath.= getEnv('PARTNER_ORDERS_LOGISTA_REPORT_FILE_BASENAME');
			$toLOGPath.= '_'.getEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_PREFIX').getEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_ID').'_'.'destruction_response'.'_';
			$toLOGPath.= $now->format('Ymd').'_'.$now->format('His');
			$toLOGPath.= '.csv';
			if($filesystem->rename($fromPath, $toProcessingPath) != true) {
				throw new Exception("file cannot be moved");
			}
			$stream = $filesystem->readStream($toProcessingPath);
			$contents = stream_get_contents($stream);
			fclose($stream);
			$stream = NULL;
			$destruction_report_file_path = NULL;
			if(($destruction_report_file_path = tempnam('', 'tmp')) === false) {
				throw new Exception('file cannot be created');
			}
			$destruction_report_file_res = NULL;
			if(($destruction_report_file_res = fopen($destruction_report_file_path, 'w')) === false) {
				throw new Exception('file cannot be opened for writing');
			}
			fwrite($destruction_report_file_res, $contents);
			fclose($destruction_report_file_res);
			$destruction_report_file_res = NULL;
			$logistaDestructionResponseReport = $this->billingLogistaProcessDestructionReport->doProcess($destruction_report_file_path);
			unlink($destruction_report_file_path);
			$destruction_report_file_path = NULL;
			//Save it in a file and upload it in the FTP
			$destruction_response_report_file_path = NULL;
			if(($destruction_response_report_file_path = tempnam('', 'tmp')) === false) {
				throw new Exception('file cannot be created');
			}
			$logistaDestructionResponseReport->saveTo($destruction_response_report_file_path);
			$destruction_response_report_file_res = NULL;
			if(($destruction_response_report_file_res = fopen($destruction_response_report_file_path, 'r')) === false) {
				throw new Exception('file cannot be opened for reading');
			}
			$filesystem->putStream($toLOGPath, $destruction_response_report_file_res);
			if (is_resource($destruction_response_report_file_res)) {
				fclose($destruction_response_report_file_res);
			}
			$destruction_response_report_file_res = NULL;
			unlink($destruction_response_report_file_path);
			$destruction_response_report_file_path = NULL;
			//done
			if($filesystem->rename($toProcessingPath, $toProcessedPath) != true) {
				throw new Exception("file cannot be moved");
			}
			ScriptsConfig::getLogger()->addInfo("processing destruction report file : ".$fromLogistaDirFile['basename']." done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("processing destruction report file : ".$fromLogistaDirFile['basename']." failed, message=".$e->getMessage());
		}
	}
	
}
	
?>