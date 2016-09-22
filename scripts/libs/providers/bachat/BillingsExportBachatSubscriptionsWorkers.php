<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/BillingsExportBachatSubscriptions.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';

use Aws\S3\S3Client;

class BillingsExportBachatSubscriptionsWorkers extends BillingsWorkers {

	private $providerid = NULL;
	
	public function __construct() {
		parent::__construct();
		$this->providerid = ProviderDAO::getProviderByName('bachat')->getId();
	}
	
	public function doExportSubscriptions() {
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($this->providerid, 'subscriptions_export', $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("exporting daily bachat subscriptions bypassed - already done today -");
				exit;
			}
			
			ScriptsConfig::getLogger()->addInfo("exporting daily bachat subscriptions...");
			
			$processingLog = ProcessingLogDAO::addProcessingLog($this->providerid, 'subscriptions_export');
			//
			$billingsExportBachatSubscriptions = new BillingsExportBachatSubscriptions();
			//
			$s3 = S3Client::factory(array(
							'region' => getEnv('AWS_REGION'),
							'version' => getEnv('AWS_VERSION')));
			$bucket = getEnv('AWS_BUCKET_BILLINGS_EXPORTS');
			$now = new DateTime();
			$now->setTimezone(new DateTimeZone(config::$timezone));
			$dailyDateFormat = "Ymd";
			//DAILY CHARTMOGUL
			$dailyFileName = "subscriptions-exports-chartmogul-bachat-daily-".$now->format($dailyDateFormat).".csv";
			$dailyKey = getEnv('AWS_ENV').'/'.getEnv('AWS_FOLDER_SUBSCRIPTIONS').'/daily/'.$dailyFileName;
			if($s3->doesObjectExist($bucket, $dailyKey) == false) {
				$export_subscriptions_file_path = NULL;
				if(($export_subscriptions_file_path = tempnam('', 'tmp')) === false) {
					throw new Exception('file for exporting daily chartmogul bachat subscriptions cannot be created');
				}	
				$billingsExportBachatSubscriptions->doExportSubscriptionsForChartmogul($export_subscriptions_file_path);
				$s3->putObject(array(
						'Bucket' => $bucket,
						'Key' => $dailyKey,
						'SourceFile' => $export_subscriptions_file_path
				));
				//
				unlink($export_subscriptions_file_path);
				$export_subscriptions_file_path = NULL;
			}
			//DONE
			$processingLog->setProcessingStatus('done');
			ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			ScriptsConfig::getLogger()->addInfo("exporting daily bachat subscriptions done successfully");
			$processingLog = NULL;
		} catch(Exception $e) {
			$msg = "an error occurred while exporting daily bachat subscriptions, message=".$e->getMessage();
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