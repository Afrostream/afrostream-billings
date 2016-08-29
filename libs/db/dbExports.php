<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/dbGlobal.php';

class dbExports {
	
	public static function getTransactionsInfos(
			DateTime $dateStart, DateTime $dateEnd,
			$limit = 0, $offset = 0) {
		$dateStart->setTimezone(new DateTimeZone(config::$timezone));
		$date_start_str = dbGlobal::toISODate($dateStart);
		$dateEnd->setTimezone(new DateTimeZone(config::$timezone));
		$date_end_str = dbGlobal::toISODate($dateEnd);
		$query =<<<EOL
		SELECT BP.name as provider_name,
		BS.transaction_creation_date as transaction_creation_date,
		BS.transaction_billing_uuid as transaction_billing_uuid,
		BS.transaction_provider_uuid as transaction_provider_uuid,
		BS.transaction_type as transaction_type,
		BS.transaction_status as transaction_status,
		(CASE WHEN BS.transactionlinkid IS NULL THEN NULL
			ELSE
		(SELECT transaction_billing_uuid FROM billing_transactions WHERE _id = BS.transactionlinkid)
		END) as related_transaction_billing_uuid,
		(CASE WHEN BS.transaction_type = 'purchase' THEN BS.amount_in_cents 
			ELSE 
		NULL
		END) as purchase_amount_in_cents,
		(CASE WHEN BS.transaction_type = 'refund' THEN BS.amount_in_cents 
			ELSE 
		NULL
		END) as refund_amount_in_cents,
		BS.currency as currency,
		BS.country as country,
		NULL as invoice_billing_uuid,
		BS.invoice_provider_uuid as invoice_provider_uuid,
		(CASE WHEN BS.userid IS NULL THEN NULL 
			ELSE
		(SELECT user_billing_uuid FROM billing_users WHERE _id = BS.userid)
		END) as user_billing_uuid,
		(CASE WHEN BS.userid IS NULL THEN NULL 
			ELSE
		(SELECT user_provider_uuid FROM billing_users WHERE _id = BS.userid)
		END) as user_provider_uuid,
		(CASE WHEN BS.subid IS NULL THEN NULL 
			ELSE
		(SELECT subscription_billing_uuid FROM billing_subscriptions WHERE _id = BS.subid)
		END) as subscription_billing_uuid,
		(CASE WHEN BS.subid IS NULL THEN NULL 
			ELSE
		(SELECT sub_uuid FROM billing_subscriptions WHERE _id = BS.subid)
		END) as subscription_provider_uuid,
		(CASE WHEN BS.couponid IS NULL THEN NULL 
			ELSE
		(SELECT coupon_billing_uuid FROM billing_coupons WHERE _id = BS.couponid)
		END) as coupon_billing_uuid
		FROM
		billing_transactions BS
		INNER JOIN billing_providers BP ON (BS.providerid = BP._id)
		WHERE transaction_type in ('purchase', 'refund')
		AND transaction_status = 'success'
		AND BS.transaction_creation_date AT TIME ZONE 'Europe/Paris' BETWEEN '%s' AND '%s'
		ORDER BY BS.transaction_creation_date ASC
EOL;
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$query = sprintf($query, $date_start_str, $date_end_str);
		
		$result = pg_query(config::getDbConn(), $query);
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = $row;
		}
		// free result
		pg_free_result($result);
		return $out;
	}
	
}

?>