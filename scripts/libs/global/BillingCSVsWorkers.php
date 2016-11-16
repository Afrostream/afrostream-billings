<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../BillingsWorkers.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';

use Aws\S3\S3Client;

class BillingCSVsWorkers extends BillingsWorkers {
	
	public function __construct() {
		parent::__construct();
	}
	
	public function doGenerateCSVs() {
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay(NULL, 'csvs_generator', $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("generating csvs bypassed - already done today -");
				return;
			}
		
			ScriptsConfig::getLogger()->addInfo("generating csvs...");
		
			$processingLog = ProcessingLogDAO::addProcessingLog(NULL, 'csvs_generator');
			$s3 = S3Client::factory(array(
					'region' => getEnv('AWS_REGION'),
					'version' => getEnv('AWS_VERSION')));
			$bucket = getEnv('AWS_BUCKET_BILLINGS_EXPORTS');
			//
			$minusOneDay = new DateInterval("P1D");
			$minusOneDay->invert = 1;
			$yesterday = new DateTime();
			$yesterday->setTimezone(new DateTimeZone(config::$timezone));
			$yesterday->add($minusOneDay);
			
			$yesterdayBeginningOfDay = clone $yesterday;
			$yesterdayBeginningOfDay->setTime(0,0,0);
			$yesterdayEndOfDay = clone $yesterday;
			$yesterdayEndOfDay->setTime(23,59,59);
			//
			$dailyDateFormat = "Ymd";
			//
			$billingCSVsTasksName = 'BillingCSVsTasks.csv';
			$csvDelimiter = ',';
			$fields = NULL;
			//
			$s3BillingCSVsTasksKey = getEnv('AWS_ENV').'/'.getEnv('AWS_FOLDER_CSVS').'/'.$billingCSVsTasksName;
			if($s3->doesObjectExist($bucket, $s3BillingCSVsTasksKey) == false) {
				throw new Exception("file containing tasks which path in s3 is '".$s3BillingCSVsTasksKey."' cannot be found");
			}
			$billing_csvs_tasks_file_path = NULL;
			if(($billing_csvs_tasks_file_path = tempnam('', 'tmp')) === false) {
				throw new Exception('file containing tasks cannot be created');
			}
			$s3Result = $s3->getObject(array(
								'Bucket' => $bucket,
								'Key' => $s3BillingCSVsTasksKey,
								'SaveAs' => $billing_csvs_tasks_file_path));
			$billing_csvs_tasks_file_res = NULL;
			if(($billing_csvs_tasks_file_res = fopen($billing_csvs_tasks_file_path, 'r')) === false) {
				throw new Exception("file containing tasks named '".$filename."' cannot be opened for reading");
			}
			//first line (headers)
			//$fields[0] = 'DATABASE'
			//$fields[1] = 'PREFIX'
			//$fields[2] = 'SQL'
			if(($fields = fgetcsv($billing_csvs_tasks_file_res, NULL, $csvDelimiter)) === false) {
				throw new Exception("cannot read file containing tasks named '".$filename."' as csv");
			}
			while(($fields = fgetcsv($billing_csvs_tasks_file_res, NULL, $csvDelimiter)) !== false) {
				$dailyFileName = "csv-".$fields[1]."-daily-".$yesterdayEndOfDay->format($dailyDateFormat).".csv";
				$dailyKey = getEnv('AWS_ENV').'/'.getEnv('AWS_FOLDER_CSVS').'/daily/'.$yesterdayEndOfDay->format($dailyDateFormat).'/'.$dailyFileName;
				try {
					if($s3->doesObjectExist($bucket, $dailyKey) == false) {
						ScriptsConfig::getLogger()->addInfo("generating csv named '".$dailyFileName."'...");
						$start_time = microtime(true);
						//CREATE FILE
						$csv_file_path = NULL;
						if(($csv_file_path = tempnam('', 'tmp')) === false) {
							throw new Exception('file for generating csv cannot be created');
						}
						//OPEN FILE
						$csv_file_res = NULL;
						if(($csv_file_res = fopen($csv_file_path, 'w')) === false) {
							throw new Exception('file for generating csv cannot be opened for writing');
						}
						//FILL FILE
						$limit = 1000;
						$offset = 0;
						$idx = 0;
						$lastId = NULL;
						$totalCounter = NULL;
						do {
							$result = dbGlobal::loadSqlResult($fields[2], $limit, $offset);
							$offset = $offset + $limit;
							if(is_null($totalCounter)) {$totalCounter = $result['total_counter'];}
							$idx+= count($result['rows']);
							$lastId = $result['lastId'];
							//
							foreach($result['rows'] as $row) {
								fputcsv($csv_file_res, array_slice($row, 2));
							}
						} while ($idx < $totalCounter && count($result['rows']) > 0);
						//UPLOAD FILE
						$s3->putObject(array(
								'Bucket' => $bucket,
								'Key' => $dailyKey,
								'SourceFile' => $csv_file_path
						));
						//CLOSE FILE
						fclose($csv_file_res);
						$csv_file_res = NULL;
						//DELETE FILE
						unlink($csv_file_path);
						$csv_file_path = NULL;
						$end_time = microtime(true);
						ScriptsConfig::getLogger()->addInfo("generating csv named '".$dailyFileName."' done successfully, time taken=".number_format($end_time - $start_time, 3)." secs");
					} else {
						ScriptsConfig::getLogger()->addInfo("generating csv named '".$dailyFileName."' bypassed, it already exists");
					}
				} catch(Exception $e) {
					$msg = "an error occurred while generating csv named '".$dailyFileName."', message=".$e->getMessage();
					ScriptsConfig::getLogger()->addError($msg);					
				}
			}
			//
			fclose($billing_csvs_tasks_file_res);
			$billing_csvs_tasks_file_res = NULL;
			unlink($billing_csvs_tasks_file_path);
			$billing_csvs_tasks_file_path = NULL;
			//DONE
			$processingLog->setProcessingStatus('done');
			ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			ScriptsConfig::getLogger()->addInfo("generating csvs done successfully");
			$processingLog = NULL;
		} catch(Exception $e) {
			$msg = "an error occurred while generating csvs, message=".$e->getMessage();
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