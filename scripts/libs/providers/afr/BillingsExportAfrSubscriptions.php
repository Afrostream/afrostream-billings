<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../../libs/db/dbExports.php';

class BillingsExportAfrSubscriptions {
	
	private $provider = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
	}
	
	public function doExportSubscriptionsForChartmogul(DateTime $from, DateTime $to, $export_subs_file_path) {
		try {
			ScriptsConfig::getLogger()->addInfo("exporting chartmogul afr subscriptions...");
			//
			$export_subs_file_res = NULL;
			if(($export_subs_file_res = fopen($export_subs_file_path, 'w')) === false) {
				throw new Exception('file for exporting chartmogul afr subscriptions cannot be opened for writing');
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
			while(count($subscriptions = dbExports::getAfrSubscriptionsInfosForChartmogul($from, $to, $limit, $offset)) > 0) {
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
			ScriptsConfig::getLogger()->addInfo("exporting chartmogul afr subscriptions done");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("unexpected exception while exporting chartmogul afr subscriptions, message=".$e->getMessage());
		}
	}
	
}

?>