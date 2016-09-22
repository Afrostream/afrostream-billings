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
		(CASE WHEN BS.transactionlinkid IS NULL THEN NULL
			ELSE
		(SELECT transaction_creation_date FROM billing_transactions WHERE _id = BS.transactionlinkid)
		END) as related_transaction_creation_date,
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
	
	public static function getGocardlessSubscriptionsInfosForChartmogul(
			$limit = 0, $offset = 0) {
		$query =<<<EOL
		SELECT
			customer_external_id,
			customer_name,
			customer_email,
			customer_country,
			customer_state,
			plan_name,
			plan_interval,
			plan_interval_count,
			quantity,
			currency,
			amount_paid,
			started_at,
			cancelled_at
		FROM
		(SELECT
		BS.creation_date as creation_date,
		BU.user_provider_uuid as customer_external_id,
		(CASE WHEN length(BUOF.value) = 0 AND length(BUOL.value) = 0 THEN 'unknown' ELSE CONCAT(BUOF.value, ' ', BUOL.value) END) as customer_name,
		(CASE WHEN length(BUO.value) = 0 THEN 'unknown@domain.com' ELSE BUO.value END) as customer_email,
		(SELECT BT.country FROM billing_transactions BT WHERE BT.userid = BU._id AND BT.transaction_status = 'success' LIMIT 1) as customer_country,
		'' as customer_state,
		BPL.plan_uuid as plan_name,
		(CASE WHEN BIPL.period_unit = 'day' AND BIPL.period_length = '30' THEN 'month' ELSE BIPL.period_unit END) as plan_interval,
		(CASE WHEN BIPL.period_unit = 'day' AND BIPL.period_length = '30' THEN 1 ELSE BIPL.period_length END) as plan_interval_count,
		1 as quantity,
		BIPL.currency as currency,
		(SELECT (CASE WHEN (T.amount_paid_internal IS NULL OR T.amount_paid_internal < 0) THEN 0 ELSE T.amount_paid_internal END) FROM 
				(SELECT round(CAST(sum((CASE WHEN BT.transaction_type = 'purchase' THEN BT.amount_in_cents ELSE -BT.amount_in_cents END)) as numeric)/100, 2) as amount_paid_internal
					FROM billing_transactions BT 
					WHERE BT.transaction_type in('purchase', 'refund') AND BT.transaction_status = 'success' AND BT.subid = BS._id) as T) 
				as amount_paid,
		to_char(BS.sub_activated_date AT TIME ZONE 'Europe/Paris', 'YYYY-MM-DD 23:59') as started_at,
		to_char(BS.sub_expires_date AT TIME ZONE 'Europe/Paris', 'YYYY-MM-DD 00:00') as cancelled_at
		FROM billing_subscriptions BS
		INNER JOIN billing_providers BP ON (BS.providerid = BP._id)
		INNER JOIN billing_plans BPL ON (BS.planid = BPL._id)
		INNER JOIN billing_internal_plans_links BIPLL ON (BIPLL.provider_plan_id = BPL._id)
		INNER JOIN billing_internal_plans BIPL ON (BIPLL.internal_plan_id = BIPL._id)
		INNER JOIN billing_users BU ON (BS.userid = BU._id)
		LEFT JOIN billing_users_opts BUO ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.deleted = false)
		LEFT JOIN billing_users_opts BUOF ON (BU._id = BUOF.userid AND BUOF.key = 'firstName' AND BUOF.deleted = false) 
		LEFT JOIN billing_users_opts BUOL ON (BU._id = BUOL.userid AND BUOL.key = 'lastName' AND BUOL.deleted = false) 
		WHERE BU.deleted = false AND BS.deleted = false AND BP.name = 'gocardless') as TT WHERE TT.amount_paid > 0 ORDER BY TT.creation_date ASC
EOL;
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$query = sprintf($query);
		
		$result = pg_query(config::getDbConn(), $query);
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = $row;
		}
		// free result
		pg_free_result($result);
		return $out;
	}
	
	public static function getBachatSubscriptionsInfosForChartmogul(
			$limit = 0, $offset = 0) {
				$query =<<<EOL
		SELECT
			customer_external_id,
			customer_name,
			customer_email,
			customer_country,
			customer_state,
			plan_name,
			plan_interval,
			plan_interval_count,
			quantity,
			currency,
			amount_paid,
			started_at,
			cancelled_at
		FROM
		(SELECT
		BS.creation_date as creation_date,
		BU.user_provider_uuid as customer_external_id,
		(CASE WHEN length(BUOF.value) = 0 AND length(BUOL.value) = 0 THEN 'unknown' ELSE CONCAT(BUOF.value, ' ', BUOL.value) END) as customer_name,
		(CASE WHEN length(BUO.value) = 0 THEN 'unknown@domain.com' ELSE BUO.value END) as customer_email,
		'FR' as customer_country,
		'' as customer_state,
		BPL.plan_uuid as plan_name,
		(CASE WHEN BIPL.period_unit = 'day' AND BIPL.period_length = '30' THEN 'month' ELSE BIPL.period_unit END) as plan_interval,
		(CASE WHEN BIPL.period_unit = 'day' AND BIPL.period_length = '30' THEN 1 ELSE BIPL.period_length END) as plan_interval_count,
		1 as quantity,
		BIPL.currency as currency,
		round(CAST(((extract(day from (BS.sub_period_ends_date - BS.sub_activated_date)) + 1 ) * BIPL.amount_in_cents / BIPL.period_length) as numeric)/100, 2) as amount_paid,
		to_char(BS.sub_activated_date AT TIME ZONE 'Europe/Paris', 'YYYY-MM-DD 23:59') as started_at,
		to_char(BS.sub_expires_date AT TIME ZONE 'Europe/Paris', 'YYYY-MM-DD 00:00') as cancelled_at
		FROM billing_subscriptions BS
		INNER JOIN billing_providers BP ON (BS.providerid = BP._id)
		INNER JOIN billing_plans BPL ON (BS.planid = BPL._id)
		INNER JOIN billing_internal_plans_links BIPLL ON (BIPLL.provider_plan_id = BPL._id)
		INNER JOIN billing_internal_plans BIPL ON (BIPLL.internal_plan_id = BIPL._id)
		INNER JOIN billing_users BU ON (BS.userid = BU._id)
		LEFT JOIN billing_users_opts BUO ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.deleted = false)
		LEFT JOIN billing_users_opts BUOF ON (BU._id = BUOF.userid AND BUOF.key = 'firstName' AND BUOF.deleted = false)
		LEFT JOIN billing_users_opts BUOL ON (BU._id = BUOL.userid AND BUOL.key = 'lastName' AND BUOL.deleted = false)
		WHERE BU.deleted = false AND BS.deleted = false AND BP.name = 'bachat') as TT WHERE TT.amount_paid > 0 ORDER BY TT.creation_date ASC
EOL;
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$query = sprintf($query);
		
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