<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../BillingsWorkers.php';
require_once __DIR__ . '/BillingExportTransactions.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';

use Aws\S3\S3Client;

class BillingExportTransactionsWorkers extends BillingsWorkers {
	
	public function __construct() {
		parent::__construct();
	}
	
	public function doExportTransactions() {
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay(NULL, 'transactions_export', $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("exporting transactions bypassed - already done today -");
				exit;
			}
		
			ScriptsConfig::getLogger()->addInfo("exporting transactions...");
		
			$processingLog = ProcessingLogDAO::addProcessingLog(NULL, 'transactions_export');
			//
			$billingExportTransactions = new BillingExportTransactions();
			//
			$s3 = S3Client::factory(array(
							'region' => getEnv('AWS_REGION'),
							'version' => getEnv('AWS_VERSION')));
			$bucket = getEnv('AWS_BUCKET_BILLINGS_EXPORTS');
			$now = new DateTime();
			$now->setTimezone(new DateTimeZone(config::$timezone));
			//DAYS
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
				//
				$dailyFileName = "transactions-exports-daily-".$dayToProcessBeginningOfDay->format($dailyDateFormat).".csv";
				$dailyKey = getEnv('AWS_ENV').'/'.getEnv('AWS_FOLDER_TRANSACTIONS').'/daily/'.$dailyFileName;
				if($s3->doesObjectExist($bucket, $dailyKey) == false) {
					$export_transactions_file_path = NULL;
					if(($export_transactions_file_path = tempnam('', 'tmp')) === false) {
						throw new Exception('file for exporting transactions cannot be created');
					}	
					$billingExportTransactions->doExportTransactions($dayToProcessBeginningOfDay, $dayToProcessEndOfDay, $export_transactions_file_path);
					$s3->putObject(array(
							'Bucket' => $bucket,
							'Key' => $dailyKey,
							'SourceFile' => $export_transactions_file_path
					));
					if($dailyCounter == 0) {
						//ONLY SEND BY EMAIL THE LAST ONE
						if(getEnv('EXPORTS_DAILY_EMAIL_ACTIVATED') == 1) {
							$sendgrid = new SendGrid(getenv('SENDGRID_API_KEY'));
							$email = new SendGrid\Email();
							$email->setTos(explode(';', getEnv('EXPORTS_TRANSACTIONS_DAILY_EMAIL_TOS')))
							->setBccs(explode(';', getEnv('EXPORTS_TRANSACTIONS_DAILY_EMAIL_BCCS')))
							->setFrom(getEnv('EXPORTS_EMAIL_FROM'))
							->setFromName(getEnv('EXPORTS_EMAIL_FROMNAME'))
							->setSubject('['.getEnv('BILLINGS_ENV').'] Afrostream Daily Transactions Export : '.$dayToProcessBeginningOfDay->format($dailyDateFormat))
							->setText('See File attached')
							->addAttachment($export_transactions_file_path, $dailyFileName);
							$sendgrid->send($email);
						}
					}
					//
					unlink($export_transactions_file_path);
					$export_transactions_file_path = NULL;
				}
				//DONE
				$dailyCounter++;
			}
			//MONTH
			$firstDayToProceedLastMonth = getEnv('EXPORTS_MONTHLY_FIRST_DAY_OF_MONTH');
			$monthlyDateFormat = "Ym";
			$minusOneMonth = new DateInterval("P1M");
			$minusOneMonth->invert = 1;
			$lastmonthsCount = getEnv('EXPORTS_MONTHLY_NUMBER_OF_MONTHS');
			$monthToProcess = clone $now;
			$monthlyCounter = 0;
			$dayOfMonth = $now->format('j');
			if($dayOfMonth >= $firstDayToProceedLastMonth) {
				while($monthlyCounter < $lastmonthsCount) {
					$monthToProcess->add($minusOneMonth);
					$monthToProcessBeginning = clone $monthToProcess;
					$monthToProcessBeginning->modify('first day of this month');
					$monthToProcessBeginning->setTime(0, 0, 0);
					$monthToProcessEnd = clone $monthToProcess;
					$monthToProcessEnd->modify('last day of this month');
					$monthToProcessEnd->setTime(23,59,59);
					$monthyFileName = "transactions-exports-monthy-".$monthToProcessBeginning->format($monthlyDateFormat).".csv";
					$monthlyKey = getEnv('AWS_ENV').'/'.getEnv('AWS_FOLDER_TRANSACTIONS').'/monthly/'.$monthyFileName;
					if($s3->doesObjectExist($bucket, $monthlyKey) == false) {
						$export_transactions_file_path = NULL;
						if(($export_transactions_file_path = tempnam('', 'tmp')) === false) {
							throw new Exception('file for exporting transactions cannot be created');
						}
						$billingExportTransactions->doExportTransactions($monthToProcessBeginning, $monthToProcessEnd, $export_transactions_file_path);
						$s3->putObject(array(
								'Bucket' => $bucket,
								'Key' => $monthlyKey,
								'SourceFile' => $export_transactions_file_path
						));
						if($monthlyCounter == 0) {
							//ONLY SEND BY EMAIL THE LAST ONE
							if(getEnv('EXPORTS_MONTHLY_EMAIL_ACTIVATED') == 1) {
								$sendgrid = new SendGrid(getenv('SENDGRID_API_KEY'));
								$email = new SendGrid\Email();
								$email->setTos(explode(';', getEnv('EXPORTS_TRANSACTIONS_MONTHLY_EMAIL_TOS')))
								->setBccs(explode(';', getEnv('EXPORTS_TRANSACTIONS_MONTHLY_EMAIL_BCCS')))
								->setFrom(getEnv('EXPORTS_EMAIL_FROM'))
								->setFromName(getEnv('EXPORTS_EMAIL_FROMNAME'))
								->setSubject('['.getEnv('BILLINGS_ENV').'] Afrostream Monthly Transactions Export : '.$monthToProcessBeginning->format($monthlyDateFormat))
								->setText('See File attached')
								->addAttachment($export_transactions_file_path, $monthyFileName);
								$sendgrid->send($email);
							}
						}
						//
						unlink($export_transactions_file_path);
						$export_transactions_file_path = NULL;
					}
					//DONE
					$monthlyCounter++;
				}
			}
			//DONE
			$processingLog->setProcessingStatus('done');
			ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			ScriptsConfig::getLogger()->addInfo("exporting transactions done successfully");
			$processingLog = NULL;
		} catch(Exception $e) {
			$msg = "an error occurred while exporting transactions, message=".$e->getMessage();
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