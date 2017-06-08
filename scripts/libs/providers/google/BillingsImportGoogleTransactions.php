<?php

require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';
require_once __DIR__ . '/../../../../libs/transactions/TransactionsHandler.php';
require_once __DIR__ . '/../../../../libs/providers/global/requests/ImportTransactionsRequest.php';

class BillingsImportGoogleTransactions {
	
	private $provider = NULL;
	private $platform = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
		$this->platform = BillingPlatformDAO::getPlatformById($this->provider->getPlatformId());
	}
	
	public function doImportTransactions(DateTime $from, DateTime $to) {
		try {
			ScriptsConfig::getLogger()->addInfo("importing transactions from google...");
			//
			if($from > $to) {
				throw new Exception("'to' parameter must be greater than 'from'");
			}
			if(!array_key_exists('reportingBucketId', $this->provider->getOpts())) {
				throw new Exception("providerOpts parameter 'reportingBucketId' is missing");
			}
			//
			$transactionsHandler = new TransactionsHandler();
			//
			$googleClient = new GoogleClient(json_decode($this->provider->getConfigFile(), true));
			while($from <= $to) {
				//
				try {
					ScriptsConfig::getLogger()->addInfo("importing transactions from google for : ".$from->format('Ym'));
					//
					$bucket = $this->provider->getOpts()['reportingBucketId'];
					$fileZipPath = 'sales/salesreport_'.$from->format('Ym').'.zip';
					$filePathInZip = 'salesreport_'.$from->format('Ym').'.csv';
					$content = $googleClient->getContentFileFromZip($bucket, $fileZipPath, $filePathInZip);
					//
					$current_file_path = NULL;
					if(($current_file_path = tempnam('', 'tmp')) === false) {
						throw new Exception('temporary file cannot be created');
					}
					file_put_contents($current_file_path, $content);
					//
					$importTransactionsRequest = new ImportTransactionsRequest();
					$importTransactionsRequest->setPlatform($this->platform);
					$importTransactionsRequest->setProviderName($this->provider->getName());
					$importTransactionsRequest->setOrigin('import');
					$importTransactionsRequest->setUploadedFile($current_file_path);
					$transactionsHandler->doImportTransactions($importTransactionsRequest);
					unlink($current_file_path);
					$current_file_path = NULL;
					ScriptsConfig::getLogger()->addInfo("importing transactions from google for : ".$from->format('Ym')." done successfully");
				} catch(Exception $e) {
					ScriptsConfig::getLogger()->addInfo("importing transactions from google for : ".$from->format('Ym')." failed, message=".$e->getMessage());
				}
				//done
				$from = $from->add(new DateInterval("P1M"));
			}
			//
			ScriptsConfig::getLogger()->addInfo("importing transactions from google done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("unexpected exception while importing transactions from google, message=".$e->getMessage());
		}
	}
		
}

?>