<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../BillingsWorkers.php';
require_once __DIR__ . '/BillingExportTransactions.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';

use Aws\S3\S3Client;

class BillingExportTransactionsWorkers extends BillingsWorkers {
	
	private $platform;
	private $processingType = 'transactions_export';
	
	public function __construct(BillingPlatform $platform) {
		parent::__construct();
		$this->platform = $platform;
	}
	
	public function doExportTransactions() {
		$starttime = microtime(true);
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay($this->platform->getId(), NULL, $this->processingType, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("exporting transactions bypassed - already done today -");
				return;
			}
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingType.'.hit');
		
			ScriptsConfig::getLogger()->addInfo("exporting transactions...");
		
			$processingLog = ProcessingLogDAO::addProcessingLog($this->platform->getId(), NULL, $this->processingType);
			//
			$billingExportTransactions = new BillingExportTransactions($this->platform);
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
				$generated_files = array();
				$dayToProcess->add($minusOneDay);
				$dayToProcessBeginningOfDay = clone $dayToProcess;
				$dayToProcessBeginningOfDay->setTime(0,0,0);
				$dayToProcessEndOfDay = clone $dayToProcess;
				$dayToProcessEndOfDay->setTime(23,59,59);
				// - YESTERDAY -
				$dailyYesterdayFileName = "transactions-exports-yesterday-daily-".$dayToProcessBeginningOfDay->format($dailyDateFormat).".csv";
				$dailyYesterdayKey = getEnv('AWS_ENV').'/'.getEnv('AWS_FOLDER_TRANSACTIONS').'/daily/'.$dayToProcessBeginningOfDay->format($dailyDateFormat).'/'.$dailyYesterdayFileName;
				if($s3->doesObjectExist($bucket, $dailyYesterdayKey) == false) {
					$export_transactions_file_path = NULL;
					if(($export_transactions_file_path = tempnam('', 'tmp')) === false) {
						throw new Exception('file for exporting transactions cannot be created');
					}
					$billingExportTransactions->doExportTransactions($dayToProcessBeginningOfDay, $dayToProcessEndOfDay, $export_transactions_file_path);
					$s3->putObject(array(
							'Bucket' => $bucket,
							'Key' => $dailyYesterdayKey,
							'SourceFile' => $export_transactions_file_path
					));
					//
					$generated_files[$dailyYesterdayFileName] = $export_transactions_file_path;
				}
				// - MONTH SLIDING -
				$firstDayOfMonth = clone $dayToProcess;
				$firstDayOfMonth->modify('first day of this month');
				$firstDayOfMonth->setTime(0, 0, 0);
				$dailyMonthSlidingFileName = "transactions-exports-month-sliding-daily-".$dayToProcessBeginningOfDay->format($dailyDateFormat).".csv";
				$dailyMonthSlidingKey = getEnv('AWS_ENV').'/'.getEnv('AWS_FOLDER_TRANSACTIONS').'/daily/'.$dayToProcessBeginningOfDay->format($dailyDateFormat).'/'.$dailyMonthSlidingFileName;
				if($s3->doesObjectExist($bucket, $dailyMonthSlidingKey) == false) {
					$export_transactions_file_path = NULL;
					if(($export_transactions_file_path = tempnam('', 'tmp')) === false) {
						throw new Exception('file for exporting transactions cannot be created');
					}
					$billingExportTransactions->doExportTransactions($firstDayOfMonth, $dayToProcessEndOfDay, $export_transactions_file_path);
					$s3->putObject(array(
							'Bucket' => $bucket,
							'Key' => $dailyMonthSlidingKey,
							'SourceFile' => $export_transactions_file_path
					));
					//
					$generated_files[$dailyMonthSlidingFileName] = $export_transactions_file_path;
				}
				if($dailyCounter == 0) {
					//ONLY SEND BY EMAIL THE LAST ONE
					if(getEnv('EXPORTS_DAILY_EMAIL_ACTIVATED') == 1) {
						$sendgrid = new SendGrid(getenv('SENDGRID_API_KEY'));
						$mail = new SendGrid\Mail();
						$email = new SendGrid\Email(getEnv('EXPORTS_EMAIL_FROMNAME'), getEnv('EXPORTS_EMAIL_FROM'));
						$mail->setFrom($email);
						$personalization = new SendGrid\Personalization();
						$to_array = explode(';', getEnv('EXPORTS_TRANSACTIONS_DAILY_EMAIL_TOS'));
						foreach ($to_array as $to) {
							$personalization->addTo(new SendGrid\Email(NULL, $to));
						}
						$bcc_array = explode(';', getEnv('EXPORTS_TRANSACTIONS_DAILY_EMAIL_BCCS'));
						foreach ($bcc_array as $bcc) {
							$personalization->addBcc(new SendGrid\Email(NULL, $bcc));
						}
						$personalization->setSubject('['.getEnv('BILLINGS_ENV').'] Afrostream Daily Transactions Export : '.$dayToProcessBeginningOfDay->format($dailyDateFormat));
						$mail->addPersonalization($personalization);
						$content = new SendGrid\Content('text/plain', 'See File(s) attached');
						$mail->addContent($content);
						foreach ($generated_files as $filename => $filepath) {
							$attachment = new SendGrid\Attachment();
							$attachment->setFilename($filename);
							$attachment->setContentID($filename);
							$attachment->setDisposition('attachment');
							$attachment->setContent(base64_encode(file_get_contents($filepath)));
							$mail->addAttachment($attachment);
						}
						$response = $sendgrid->client->mail()->send()->post($mail);
						if($response->statusCode() != 202) {
							ScriptsConfig::getLogger()->addError('sending mail using sendgrid failed, statusCode='.$response->statusCode());
							ScriptsConfig::getLogger()->addError('sending mail using sendgrid failed, body='.$response->body());
							ScriptsConfig::getLogger()->addError('sending mail using sendgrid failed, headers='.var_export($response->headers(), true));
						}
					}
				}
				foreach ($generated_files as $filename => $filepath) {
					unlink($filepath);
				}
				//DONE
				$dailyCounter++;
			}
			//MONTH
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
					$monthyFileName = "transactions-exports-current-month-monthly-".$monthToProcessBeginning->format($monthlyDateFormat).".csv";
					$monthlyKey = getEnv('AWS_ENV').'/'.getEnv('AWS_FOLDER_TRANSACTIONS').'/monthly/'.$monthToProcessBeginning->format($monthlyDateFormat).'/'.$monthyFileName;
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
								$mail = new SendGrid\Mail();
								$email = new SendGrid\Email(getEnv('EXPORTS_EMAIL_FROMNAME'), getEnv('EXPORTS_EMAIL_FROM'));
								$mail->setFrom($email);
								$personalization = new SendGrid\Personalization();
								$to_array = explode(';', getEnv('EXPORTS_TRANSACTIONS_MONTHLY_EMAIL_TOS'));
								foreach ($to_array as $to) {
									$personalization->addTo(new SendGrid\Email(NULL, $to));
								}
								$bcc_array = explode(';', getEnv('EXPORTS_TRANSACTIONS_MONTHLY_EMAIL_BCCS'));
								foreach ($bcc_array as $bcc) {
									$personalization->addBcc(new SendGrid\Email(NULL, $bcc));
								}
								$personalization->setSubject('['.getEnv('BILLINGS_ENV').'] Afrostream Monthly Transactions Export : '.$monthToProcessBeginning->format($monthlyDateFormat));
								$mail->addPersonalization($personalization);
								$content = new SendGrid\Content('text/plain', 'See File(s) attached');
								$mail->addContent($content);
								$attachment = new SendGrid\Attachment();
								$attachment->setFilename($monthyFileName);
								$attachment->setContentID($monthyFileName);
								$attachment->setDisposition('attachment');
								$attachment->setContent(base64_encode(file_get_contents($export_transactions_file_path)));
								$mail->addAttachment($attachment);
								$response = $sendgrid->client->mail()->send()->post($mail);
								if($response->statusCode() != 202) {
									ScriptsConfig::getLogger()->addError('sending mail using sendgrid failed, statusCode='.$response->statusCode());
									ScriptsConfig::getLogger()->addError('sending mail using sendgrid failed, body='.$response->body());
									ScriptsConfig::getLogger()->addError('sending mail using sendgrid failed, headers='.var_export($response->headers(), true));
								}
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
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingType.'.success');
		} catch(Exception $e) {
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingType.'.error');
			$msg = "an error occurred while exporting transactions, message=".$e->getMessage();
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