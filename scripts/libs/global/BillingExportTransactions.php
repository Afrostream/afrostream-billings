<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../../libs/db/dbExports.php';

class BillingExportTransactions {
	
	public function __construct() {
	}
	
	public function doExportTransactions(DateTime $from, DateTime $to, $export_transactions_file_path) {
		$export_transactions_file_res = NULL;
		if(($export_transactions_file_res = fopen($export_transactions_file_path, 'w')) === false) {
			throw new Exception('file for exporting transactions cannot be opened for writing');
		}
		$csvDelimiter = ';';
		$fields = array();
		$fields[] = 'provider_name';
		$fields[] = 'transaction_creation_date';
		$fields[] = 'transaction_billing_uuid';
		$fields[] = 'transaction_provider_uuid';
		$fields[] = 'transaction_type';
		$fields[] = 'transaction_status';
		$fields[] = 'related_transaction_billing_uuid';
		$fields[] = 'related_transaction_creation_date';
		$fields[] = 'purchase_amount_in_cents';
		$fields[] = 'refund_amount_in_cents';
		$fields[] = 'currency';
		$fields[] = 'country';
		$fields[] = 'invoice_billing_uuid';
		$fields[] = 'invoice_provider_uuid';
		$fields[] = 'user_billing_uuid';
		$fields[] = 'user_provider_uuid';
		$fields[] = 'subscription_billing_uuid';
		$fields[] = 'subscription_provider_uuid';
		$fields[] = 'coupon_billing_uuid';
		fputcsv($export_transactions_file_res, $fields, $csvDelimiter);
		$offset = 0;
		$limit = 1000;
		while(count($transactions = dbExports::getTransactionsInfos($from, $to, $limit, $offset)) > 0) {
			$offset = $offset + $limit;
			//
			foreach($transactions as $transaction) {
				fputcsv($export_transactions_file_res, $transaction, $csvDelimiter);
			}
		}
		//DONE
		fclose($export_transactions_file_res);
		$export_transactions_file_res = NULL;
	}
	
}

?>