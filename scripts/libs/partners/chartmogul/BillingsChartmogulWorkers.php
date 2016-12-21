<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';

class BillingsChartmogulWorkers extends BillingsWorkers {

	private $processingTypeMergeCustomers = 'merge_customers';
	private $processingTypeSyncCustomers = 'sync_customers';
	
	public function __construct() {
		parent::__construct();
		ChartMogul\Configuration::getDefaultConfiguration()
		->setAccountToken(getEnv('CHARTMOGUL_API_ACCOUNT_TOKEN'))
		->setSecretKey(getEnv('CHARTMOGUL_API_SECRET_KEY'));
	}
	
	public function doMergeCustomers() {
		$processingLog  = NULL;
		try {
			try {
				//Sample
				//$custId = 'cus_3dbe4aaa-a351-11e6-9b5a-4b4e13282e35';
				//$externalId = '243b08a4-0cbd-aa79-25ed-4c146047d398';
				//$customer = ChartMogul\Enrichment\Customer::all([
				//		'external_id' => $externalId
				//]);
				//$customer = ChartMogul\Enrichment\Customer::retrieve($custId);
				//sissou_972@hotmail.com
				//$externalId = "4013c270-50ce-11e5-9199-4bb4ee4a104f";//(RECURLY)
				$externalIdInRecurly = "sissou_972@hotmail.com";//(RECURLY)
				$externalIdInBraintree = "691322408";//(BRAINTREE)
				$customerFromRecurly = ChartMogul\Enrichment\Customer::all([
					'external_id' => $externalIdInRecurly
				]);
				ScriptsConfig::getLogger()->addInfo("customerFromRecurly=".$customerFromRecurly);
				$customerFromBraintree = ChartMogul\Enrichment\Customer::all([
					'external_id' => $externalIdInBraintree
				]);
				$bool = ChartMogul\Enrichment\Customer::merge(
						["customer_uuid" => $customerFromRecurly->entries[0]->uuid], 
						["customer_uuid" => $customerFromBraintree->entries[0]->uuid]);
				//ScriptsConfig::getLogger()->addInfo("Chartmogul CustomerCustId : ".$customer->entries[0]->uuid);
				ScriptsConfig::getLogger()->addInfo("bool : ".$bool);
			} catch (Exception $e) {
				ScriptsConfig::getLogger()->addInfo("Exception !!".$e->getMessage());
			}
			exit;
			//
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay(NULL, $this->processingTypeMergeCustomers, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("merging chartmogul customers bypassed - already done today -");
				return;
			}
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingTypeMergeCustomers.'.hit');
			
			ScriptsConfig::getLogger()->addInfo("merging chartmogul customers...");
			
			$processingLog = ProcessingLogDAO::addProcessingLog(NULL, $this->processingTypeMergeCustomers);
			//
			//DONE
			$processingLog->setProcessingStatus('done');
			ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			ScriptsConfig::getLogger()->addInfo("merging chartmogul customers done successfully");
			$processingLog = NULL;
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingTypeMergeCustomers.'.success');
		} catch(Exception $e) {
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingTypeMergeCustomers.'.error');
			$msg = "an error occurred while merging chartmogul customers, message=".$e->getMessage();
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
	
	public function doSyncCustomers() {
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay(NULL, $this->processingTypeSyncCustomers, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("syncing chartmogul customers bypassed - already done today -");
				return;
			}
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingTypeSyncCustomers.'.hit');
				
			ScriptsConfig::getLogger()->addInfo("syncing chartmogul customers...");
			
			$processingLog = ProcessingLogDAO::addProcessingLog(NULL, $this->processingTypeSyncCustomers);
			//
			$providerIds = array();
			//TODO : should by dynamically loaded
			$providerNames = ['celery', 'recurly', 'braintree', 'bachat', 'gocardless', 'afr'];
			foreach ($providerNames as $providerName) {
				$provider = ProviderDAO::getProviderByName($providerName);
				if($provider == NULL) {
					$msg = "unknown provider named : ".$providerName;
					ScriptsConfig::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$providerIds[] = $provider->getId();
			}
			//
			$chartmogulStatus_array = ['waiting', 'failed'];
			//
			$limit = 1000;
			$offset = 0;
			$idx = 0;
			$lastId = NULL;
			$totalCounter = NULL;
			do {
				$users = UserDAO::getUsersByChartmogulStatus($limit, $offset, $lastId, $providerIds, $chartmogulStatus_array);
				if(is_null($totalCounter)) {$totalCounter = $users['total_counter'];}
				$idx+= count($users['users']);
				$lastId = $users['lastId'];
				//
				ScriptsConfig::getLogger()->addInfo("processing...total_counter=".$totalCounter.", idx=".$idx);
				foreach($users['users'] as $user) {
					try {
						$user = $this->doSyncCustomer($user);
					} catch(Exception $e) {
						$msg = "an error occurred while syncing chartmogul customer for user with id=".$user->getId().", message=".$e->getMessage();
						ScriptsConfig::getLogger()->addError($msg);
					}
				}
			} while ($idx < $totalCounter && count($users['users']) > 0);
			//DONE
			$processingLog->setProcessingStatus('done');
			ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			ScriptsConfig::getLogger()->addInfo("syncing chartmogul customers done successfully");
			$processingLog = NULL;
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingTypeSyncCustomers.'.success');
		} catch(Exception $e) {
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingTypeSyncCustomers.'.error');
			$msg = "an error occurred while syncing chartmogul customers, message=".$e->getMessage();
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
	
	private function doSyncCustomer(User $user) {
		$chartmogulCustomerUuid = NULL;
		$chartmogulStatus = NULL;
		try {
			ScriptsConfig::getLogger()->addInfo("syncing chartmogul customer for user with id=".$user->getId()."...");
			$chartmogulCustomers = ChartMogul\Enrichment\Customer::all([
					'external_id' => $user->getUserProviderUuid()
			]);
			if(count($chartmogulCustomers->entries) == 0) {
				//Exception
				throw new Exception('no customer found with external_id='.$user->getUserProviderUuid());
			}
			if(count($chartmogulCustomers->entries) > 1) {
				//Exception
				throw new Exception('more than one customer found with external_id='.$user->getUserProviderUuid());
			}
			$chartmogulCustomer = $chartmogulCustomers->entries[0];
			$chartmogulCustomerUuid = $chartmogulCustomer->uuid;
			$chartmogulStatus = 'pending';
			ScriptsConfig::getLogger()->addInfo("syncing chartmogul customer for user with id=".$user->getId()." done successfully");
		} catch(Exception $e) {
			$chartmogulStatus = 'failed';
			$msg = "an error occurred while syncing chartmogul customer for user with id=".$user->getId().", message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			throw $e;
		} finally {
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				if(isset($chartmogulCustomerUuid)) {
					$user->setChartmogulCustomerUuid($chartmogulCustomerUuid);
					$user = UserDAO::updateChartmogulCustomerUuid($user);
				}
				$user->setChartmogulMergeStatus($chartmogulStatus);
				$user = UserDAO::updateChartmogulStatus($user);
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
		}
		return($user);
	}
	
}

?>