<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../BillingsWorkers.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';

class BillingsChartmogulWorkers extends BillingsWorkers {

	private $processingTypeMergeCustomers = 'chartmogul_merge_customers';
	private $processingTypeSyncCustomers = 'chartmogul_sync_customers';
	//TODO : should by dynamically loaded
	private $supportedProviderNames = ['celery', 'recurly', 'braintree', 'bachat', 'gocardless', 'afr'];
	private $supportedProviders = array();
	private $supportedProvidersIds = array();
	
	public function __construct() {
		parent::__construct();
		ChartMogul\Configuration::getDefaultConfiguration()
		->setAccountToken(getEnv('CHARTMOGUL_API_ACCOUNT_TOKEN'))
		->setSecretKey(getEnv('CHARTMOGUL_API_SECRET_KEY'));
		foreach ($this->supportedProviderNames as $providerName) {
			$provider = ProviderDAO::getProviderByName($providerName);
			$this->supportedProviders[] = $provider;
			$this->supportedProvidersIds[] = $provider->getId();
		}
	}
	
	public function doMergeCustomers() {
		$starttime = microtime(true);
		$processingLog  = NULL;
		try {
			$processingLogsOfTheDay = ProcessingLogDAO::getProcessingLogByDay(NULL, $this->processingTypeMergeCustomers, $this->today);
			if(self::hasProcessingStatus($processingLogsOfTheDay, 'done')) {
				ScriptsConfig::getLogger()->addInfo("merging chartmogul customers bypassed - already done today -");
				return;
			}
			BillingStatsd::inc('route.scripts.workers.providers.global.workertype.'.$this->processingTypeMergeCustomers.'.hit');
			
			ScriptsConfig::getLogger()->addInfo("merging chartmogul customers...");
			
			$processingLog = ProcessingLogDAO::addProcessingLog(NULL, $this->processingTypeMergeCustomers);
			//
			$chartmogulStatus_array = ['pending'];
			//
			$limit = 1000;
			$offset = 0;
			$idx = 0;
			$lastId = NULL;
			$totalCounter = NULL;
			do {
				$users = UserDAO::getUsersByChartmogulStatus($limit, $offset, $lastId, $this->supportedProvidersIds, $chartmogulStatus_array);
				if(is_null($totalCounter)) {$totalCounter = $users['total_counter'];}
				$idx+= count($users['users']);
				$lastId = $users['lastId'];
				//
				ScriptsConfig::getLogger()->addInfo("processing...total_counter=".$totalCounter.", idx=".$idx);
				foreach($users['users'] as $user) {
					try {
						$this->doMergeCustomer($user);
					} catch(Exception $e) {
						$msg = "an error occurred while merging chartmogul customer for user with id=".$user->getId().", message=".$e->getMessage();
						ScriptsConfig::getLogger()->addError($msg);
					}
				}
			} while ($idx < $totalCounter && count($users['users']) > 0);
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
			$timingInMillis = round((microtime(true) - $starttime) * 1000);
			BillingStatsd::timing('route.scripts.workers.providers.global.workertype.'.$this->processingTypeMergeCustomers.'.timing', $timingInMillis);
			if(isset($processingLog)) {
				ProcessingLogDAO::updateProcessingLogProcessingStatus($processingLog);
			}
		}		
	}
	
	public function doSyncCustomers() {
		$starttime = microtime(true);
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
			$chartmogulStatus_array = ['waiting', 'failed'];
			//
			$limit = 1000;
			$offset = 0;
			$idx = 0;
			$lastId = NULL;
			$totalCounter = NULL;
			do {
				$users = UserDAO::getUsersByChartmogulStatus($limit, $offset, $lastId, $this->supportedProvidersIds, $chartmogulStatus_array);
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
			$timingInMillis = round((microtime(true) - $starttime) * 1000);
			BillingStatsd::timing('route.scripts.workers.providers.global.workertype.'.$this->processingTypeSyncCustomers.'.timing', $timingInMillis);
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
	
	private function doMergeCustomer(User $user) {
		try {
			ScriptsConfig::getLogger()->addInfo("merging chartmogul customer for user with id=".$user->getId()."...");
			//get users
			$users = self::filterUsersBySupportedProvidersIds(UserDAO::getUsersByUserReferenceUuid($user->getUserReferenceUuid()), $this->supportedProvidersIds);
			$masterUser = self::getFirstUserByChartmogulMergeStatus($users, 'master');
			if($masterUser == NULL) {
				$masterUser = self::getFirstUserByChartmogulMergeStatus($users, 'pending');
				$masterUser->setChartmogulMergeStatus('master');
				$masterUser = UserDAO::updateChartmogulStatus($masterUser);
			}
			//update users
			$users = self::filterUsersBySupportedProvidersIds(UserDAO::getUsersByUserReferenceUuid($user->getUserReferenceUuid()), $this->supportedProvidersIds);
			$pendingUsers = self::getUsersByChartmogulMergeStatus($users, 'pending');
			foreach ($pendingUsers as $pendingUser) {
				try {
					try {
						if(ChartMogul\Enrichment\Customer::merge(["customer_uuid" => $pendingUser->getChartmogulCustomerUuid()],
						 	["customer_uuid" => $masterUser->getChartmogulCustomerUuid()]) == false) {
						 		throw new Exception("charmogul api : cannot merge, no reason given");
						}
					} catch(Exception $e) {
						throw $e;
					}
					try {
						//START TRANSACTION
						pg_query("BEGIN");
						$pendingUser->setChartmogulMergeStatus('slave');
						$pendingUser = UserDAO::updateChartmogulStatus($pendingUser);
						//COMMIT
						pg_query("COMMIT");
					} catch(Exception $e) {
						pg_query("ROLLBACK");
						throw $e;
					}
				} catch(Exception $e) {
					ScriptsConfig::getLogger()->addWarning("cannot merge from chartmogul_customer_uuid="
							.$pendingUser->getChartmogulCustomerUuid()." to chartmogul_customer_uuid="
							.$masterUser->getChartmogulCustomerUuid().", message=".$e->getMessage());
				}
			}
			//
			ScriptsConfig::getLogger()->addInfo("merging chartmogul customer for user with id=".$user->getId()." done successfully");
		} catch(Exception $e) {
			$msg = "an error occurred while merging chartmogul customer for user with id=".$user->getId().", message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			throw $e;
		}
		return($user);
	}
	
	private static function filterUsersBySupportedProvidersIds(array $users, array $supportedProvidersIds) {
		$result = array();
		foreach ($users as $user) {
			if(in_array($user->getProviderId(), $supportedProvidersIds)) {
				$result[] = $user;
			}
		}
		return($result);
	}
	
	private static function getFirstUserByChartmogulMergeStatus(array $users, $chartmogulMergeStatus) {
		foreach ($users as $user) {
			if($user->getChartmogulMergeStatus() == $chartmogulMergeStatus) {
				return($user);
			}
		}
		return NULL;		
	}
	
	private static function getUsersByChartmogulMergeStatus(array $users, $chartmogulMergeStatus) {
		$result = array();
		foreach ($users as $user) {
			if($user->getChartmogulMergeStatus() == $chartmogulMergeStatus) {
				$result[] = $user;
			}
		}
		return($result);
	}
}

?>