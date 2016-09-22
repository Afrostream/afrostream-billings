<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../../libs/db/dbExports.php';

class BillingsExportGocardlessSubscriptions {
	
	private $providerid = NULL;
	
	public function __construct() {
		$this->providerid = ProviderDAO::getProviderByName('gocardless')->getId();
	}
	
	public function doExportSubscriptionsForChartmogul($export_subs_file_path) {
		try {
			ScriptsConfig::getLogger()->addInfo("exporting chartmogul gocardless subscriptions...");
			//
			$export_subs_file_res = NULL;
			if(($export_subs_file_res = fopen($export_subs_file_path, 'w')) === false) {
				throw new Exception('file for exporting chartmogul gocardless subscriptions cannot be opened for writing');
			}
			$csvDelimiter = ',';
			$fields = array();
			$fields[] = 'customer_external_id';
			$fields[] = 'customer_name';
			$fields[] = 'customer_email';
			$fields[] = 'customer_country';
			$fields[] = 'customer_state';
			$fields[] = 'plan_name';
			$fields[] = 'plan_interval';
			$fields[] = 'plan_interval_count';
			$fields[] = 'quantity';
			$fields[] = 'currency';
			$fields[] = 'amount_paid';
			$fields[] = 'started_at';
			$fields[] = 'cancelled_at';
			fputcsv($export_subs_file_res, $fields, $csvDelimiter);
			$offset = 0;
			$limit = 1000;
			while(count($subscriptions = dbExports::getGocardlessSubscriptionsInfosForChartmogul($limit, $offset)) > 0) {
				$offset = $offset + $limit;
				//
				foreach($subscriptions as $subscription) {
					fputcsv($export_subs_file_res, $subscription, $csvDelimiter);
				}
			}
			//DONE
			fclose($export_subs_file_res);
			$export_subs_file_res = NULL;
			//
			ScriptsConfig::getLogger()->addInfo("exporting chartmogul gocardless subscriptions done");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("unexpected exception while exporting chartmogul gocardless subscriptions, message=".$e->getMessage());
		}
	}
	
}

?>