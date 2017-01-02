<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/BillingsExportBachatSubscriptions.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';

use Aws\S3\S3Client;

class BillingsExportBachatSubscriptionsWorkers extends BillingsWorkers {

	private $provider = NULL;
	private $processingType = 'subscriptions_export';
	
	public function __construct() {
		parent::__construct();
		$this->provider = ProviderDAO::getProviderByName('bachat');
	}
	
	public function doExportSubscriptions() {
		$starttime = microtime(true);
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($this->provider->getId(), $this->processingType, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("exporting daily bachat subscriptions bypassed - already done today -");
				return;
			}
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.hit');
			ScriptsConfig::getLogger()->addInfo("exporting daily bachat subscriptions...");
			
			$processingLog = ProcessingLogDAO::addProcessingLog($this->provider->getId(), $this->processingType);
			//
			$billingsExportBachatSubscriptions = new BillingsExportBachatSubscriptions();
			//
					$s3 = S3Client::factory(array(
							'region' => getEnv('AWS_REGION'),
							'version' => getEnv('AWS_VERSION')));
			$bucket = getEnv('AWS_BUCKET_BILLINGS_EXPORTS');
			$now = new DateTime();
			$now->setTimezone(new DateTimeZone(config::$timezone));
			//DAILY CHARTMOGUL
			$dailyDateFormat = "Ymd";
			$minusOneDay = new DateInterval("P1D");
			$minusOneDay->invert = 1;
			$lastdaysCount = getEnv('EXPORTS_DAILY_NUMBER_OF_DAYS');
			$dayToProcess = clone $now;
			$dailyCounter = 0;
			while($dailyCounter < $lastdaysCount) {
				$dayToProcess->add($minusOneDay);
				$dayToProcessBeginningOfDay = clone $dayToProcess;
				$dayToProcessBeginningOfDay->setTime(0,0,0);
				$dayToProcessEndOfDay = clone $dayToProcess;
				$dayToProcessEndOfDay->setTime(23,59,59);
				$dailyFileName = "subscriptions-exports-chartmogul-bachat-daily-".$dayToProcessBeginningOfDay->format($dailyDateFormat).".csv";
				$dailyKey = getEnv('AWS_ENV').'/'.getEnv('AWS_FOLDER_SUBSCRIPTIONS').'/daily/'.$dayToProcessBeginningOfDay->format($dailyDateFormat).'/'.$dailyFileName;
				if($s3->doesObjectExist($bucket, $dailyKey) == false) {
					$export_subscriptions_file_path = NULL;
					if(($export_subscriptions_file_path = tempnam('', 'tmp')) === false) {
						throw new Exception('file for exporting daily chartmogul bachat subscriptions cannot be created');
					}
					$billingsExportBachatSubscriptions->doExportSubscriptionsForChartmogul($dayToProcessBeginningOfDay, $dayToProcessEndOfDay, $export_subscriptions_file_path);
					$s3->putObject(array(
							'Bucket' => $bucket,
							'Key' => $dailyKey,
							'SourceFile' => $export_subscriptions_file_path
					));
					if($dailyCounter == 0) {
						//ONLY SEND BY EMAIL THE LAST ONE
						if(getEnv('EXPORTS_DAILY_EMAIL_ACTIVATED') == 1) {
							$sendgrid = new SendGrid(getenv('SENDGRID_API_KEY'));
							$email = new SendGrid\Email();
							$email->setTos(explode(';', getEnv('EXPORTS_SUBSCRIPTIONS_DAILY_EMAIL_TOS')))
							->setBccs(explode(';', getEnv('EXPORTS_SUBSCRIPTIONS_DAILY_EMAIL_BCCS')))
							->setFrom(getEnv('EXPORTS_EMAIL_FROM'))
							->setFromName(getEnv('EXPORTS_EMAIL_FROMNAME'))
							->setSubject('['.getEnv('BILLINGS_ENV').'] Afrostream Daily Chartmogul Bachat Subscriptions Export : '.$dayToProcessBeginningOfDay->format($dailyDateFormat))
							->setText('See File(s) attached')
							->addAttachment($export_subscriptions_file_path, $dailyFileName);
							$sendgrid->send($email);
						}
					}
					//
					unlink($export_subscriptions_file_path);
					$export_subscriptions_file_path = NULL;
				}
				//DONE
				$dailyCounter++;
			}
			//MONTHLY CHARTMOGUL
			$firstDayToProceedLastMonth = getEnv('EXPORTS_MONTHLY_FIRST_DAY_OF_MONTH');
			$monthlyDateFormat = "Ym";
			$lastmonthsCount = getEnv('EXPORTS_MONTHLY_NUMBER_OF_MONTHS');
			$monthToProcess = clone $now;
			$monthlyCounter = 0;
			$dayOfMonth = $now->format('j');
			if($dayOfMonth >= $firstDayToProceedLastMonth) {
				while($monthlyCounter < $lastmonthsCount) {
					$monthToProcess->modify("first day of last month");
					$monthToProcessBeginning = clone $monthToProcess;
					$monthToProcessBeginning->modify('first day of this month');
					$monthToProcessBeginning->setTime(0, 0, 0);
					$monthToProcessEnd = clone $monthToProcess;
					$monthToProcessEnd->modify('last day of this month');
					$monthToProcessEnd->setTime(23,59,59);
					$monthyFileName = "subscriptions-exports-chartmogul-bachat-monthy-".$monthToProcessBeginning->format($monthlyDateFormat).".csv";
					$monthlyKey = getEnv('AWS_ENV').'/'.getEnv('AWS_FOLDER_SUBSCRIPTIONS').'/monthly/'.$monthToProcessBeginning->format($monthlyDateFormat).'/'.$monthyFileName;
					if($s3->doesObjectExist($bucket, $monthlyKey) == false) {
						$export_subscriptions_file_path = NULL;
						if(($export_subscriptions_file_path = tempnam('', 'tmp')) === false) {
							throw new Exception('file for exporting monthly chartmogul bachat subscriptions cannot be created');
						}
						$billingsExportBachatSubscriptions->doExportSubscriptionsForChartmogul($monthToProcessBeginning, $monthToProcessEnd, $export_subscriptions_file_path);
						$s3->putObject(array(
								'Bucket' => $bucket,
								'Key' => $monthlyKey,
								'SourceFile' => $export_subscriptions_file_path
						));
						if($monthlyCounter == 0) {
							//ONLY SEND BY EMAIL THE LAST ONE
							if(getEnv('EXPORTS_MONTHLY_EMAIL_ACTIVATED') == 1) {
								$sendgrid = new SendGrid(getenv('SENDGRID_API_KEY'));
								$email = new SendGrid\Email();
								$email->setTos(explode(';', getEnv('EXPORTS_SUBSCRIPTIONS_MONTHLY_EMAIL_TOS')))
								->setBccs(explode(';', getEnv('EXPORTS_SUBSCRIPTIONS_MONTHLY_EMAIL_BCCS')))
								->setFrom(getEnv('EXPORTS_EMAIL_FROM'))
								->setFromName(getEnv('EXPORTS_EMAIL_FROMNAME'))
								->setSubject('['.getEnv('BILLINGS_ENV').'] Afrostream Monthly Chartmogul Bachat Subscriptions Export : '.$monthToProcessBeginning->format($monthlyDateFormat))
								->setText('See File(s) attached')
								->addAttachment($export_subscriptions_file_path, $monthyFileName);
								$sendgrid->send($email);
							}
						}
						//
						unlink($export_subscriptions_file_path);
						$export_subscriptions_file_path = NULL;
					}
					//DONE
					$monthlyCounter++;
				}
			}
			//DONE
			$processingLog->setProcessingStatus('done');
			ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			ScriptsConfig::getLogger()->addInfo("exporting daily bachat subscriptions done successfully");
			$processingLog = NULL;
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.success');
		} catch(Exception $e) {
			BillingStatsd::inc('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.error');
			$msg = "an error occurred while exporting daily bachat subscriptions, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($processingLog)) {
				$processingLog->setProcessingStatus('error');
				$processingLog->setMessage($msg);
			}
		} finally {
			$timingInMillis = round((microtime(true) - $starttime) * 1000);
			BillingStatsd::timing('route.scripts.workers.providers.'.$this->provider->getName().'.workertype.'.$this->processingType.'.timing', $timingInMillis);
			if(isset($processingLog)) {
				ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			}
		}
	}
	
}

?>