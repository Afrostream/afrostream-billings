<?php

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;

require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/BillingLogistaProcessIncidentsReport.php';

class BillingLogistaProcessIncidentsReportWorkers extends BillingsWorkers {
	
	private $processingType = 'logista_incidents_reporting';

	protected $partner;
	protected $billingLogistaProcessIncidentsReport;

	public function __construct(BillingPartner $partner) {
		parent::__construct();
		$this->partner = $partner;
		$this->billingLogistaProcessIncidentsReport = new BillingLogistaProcessIncidentsReport($this->partner);
	}
	
	public function doProcessIncidentsReports() {
		$starttime = microtime(true);
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($this->partner->getPlatformId(), NULL, $this->processingType, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo($this->processingType." bypassed - already done today -");
				return;
			}
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingType.'.hit');
		
			ScriptsConfig::getLogger()->addInfo($this->processingType." processing...");
		
			$processingLog = ProcessingLogDAO::addProcessingLog($this->partner->getPlatformId(), NULL, $this->processingType);
			$filesystem = new Filesystem(new Ftp([
					'host' => getEnv('PARTNER_ORDERS_LOGISTA_FTP_HOST'),
					'username' => getEnv('PARTNER_ORDERS_LOGISTA_FTP_USER'),
					'password' => getEnv('PARTNER_ORDERS_LOGISTA_FTP_PWD')
			]));
			$fromLogistaDirFiles = $filesystem->listContents(getEnv('PARTNER_ORDERS_LOGISTA_FTP_FOLDER_IN'), false);
			$incidentsReportBasename = getEnv('PARTNER_ORDERS_LOGISTA_REPORT_FILE_BASENAME').'_'.getEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_PREFIX').getEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_ID').'_'.'incidents'.'_';
			foreach($fromLogistaDirFiles as $fromLogistaDirFile) {
				$filename = $fromLogistaDirFile['basename'];
				if(substr($filename, 0, strlen($incidentsReportBasename)) === $incidentsReportBasename) {
					$this->doProcessIncidentsReport($fromLogistaDirFile);
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
	
	private function doProcessIncidentsReport(array $fromLogistaDirFile) {
		try {
			ScriptsConfig::getLogger()->addInfo("processing incident report file : ".$fromLogistaDirFile['basename']."...");
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
			$toLOGPath.= '_'.getEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_PREFIX').getEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_ID').'_'.'incidents_response'.'_';
			$toLOGPath.= $now->format('Ymd').'_'.$now->format('His');
			$toLOGPath.= '.csv';
			if($filesystem->rename($fromPath, $toProcessingPath) != true) {
				throw new Exception("file cannot be moved");
			}
			$stream = $filesystem->readStream($toProcessingPath);
			$contents = stream_get_contents($stream);
			fclose($stream);
			$stream = NULL;
			$incidents_report_file_path = NULL;
			if(($incidents_report_file_path = tempnam('', 'tmp')) === false) {
				throw new Exception('file cannot be created');
			}
			$incidents_report_file_res = NULL;
			if(($incidents_report_file_res = fopen($incidents_report_file_path, 'w')) === false) {
				throw new Exception('file cannot be opened for writing');
			}
			fwrite($incidents_report_file_res, $contents);
			fclose($incidents_report_file_res);
			$incidents_report_file_res = NULL;
			$logistaIncidentsResponseReport = $this->billingLogistaProcessIncidentsReport->doProcess($incidents_report_file_path);
			unlink($incidents_report_file_path);
			$incidents_report_file_path = NULL;
			//Save it in a file and upload it in the FTP
			$incidents_response_report_file_path = NULL;
			if(($incidents_response_report_file_path = tempnam('', 'tmp')) === false) {
				throw new Exception('file cannot be created');
			}
			$logistaIncidentsResponseReport->saveTo($incidents_response_report_file_path);
			$incidents_response_report_file_res = NULL;
			if(($incidents_response_report_file_res = fopen($incidents_response_report_file_path, 'r')) === false) {
				throw new Exception('file cannot be opened for reading');
			}
			$filesystem->putStream($toLOGPath, $incidents_response_report_file_res);
			if (is_resource($incidents_response_report_file_res)) {
				fclose($incidents_response_report_file_res);
			}
			$incidents_response_report_file_res = NULL;
			unlink($incidents_response_report_file_path);
			$incidents_response_report_file_path = NULL;
			//done
			if($filesystem->rename($toProcessingPath, $toProcessedPath) != true) {
				throw new Exception("file cannot be moved");
			}
			ScriptsConfig::getLogger()->addInfo("processing incident report file : ".$fromLogistaDirFile['basename']." done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("processing incident report file : ".$fromLogistaDirFile['basename']." failed, message=".$e->getMessage());
		}
	}
	
}
	
?>