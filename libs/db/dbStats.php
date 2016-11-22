<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/dbGlobal.php';

class dbStats {
	
	/**
	 * Result as this :
	 * "total" => 8888,
	 * providers =>	"recurly" => 4444,
	 * 				"gocardless" => 2222,
	 * 				"bachat" => 2222
	 */
	public static function getNumberOfSubscriptions(DateTime $date) {
		$date->setTimezone(new DateTimeZone(config::$timezone));
		$date_as_str = dbGlobal::toISODate($date);
		$query = "SELECT BP.name as provider_name, count(*) as counter FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users BU";
		$query.= " ON (BS.userid = BU._id)";
		$query.= " LEFT JOIN billing_users_opts BUO";
		$query.= " ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.deleted = false)";
		$query.= " WHERE (BUO.value not like '%yopmail.com' OR BUO.value is null)";
		$query.= " AND BS.creation_date <='".$date_as_str."'";
		$query.= " GROUP BY BP._id";
		$result = pg_query(config::getDbConn(), $query);
		$total = 0;
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$total+= $row['counter'];
			$out['providers'][$row['provider_name']]['total'] = $row['counter'];
		}
		// free result
		pg_free_result($result);
		$out['total'] = $total;
		return($out);
	}
	/**
	 * Result as this :
	 * "total" => 8888,
	 * providers =>	"recurly" => 4444,
	 * 				"gocardless" => 2222,
	 * 				"bachat" => 2222 
	 */
	public static function getNumberOfActiveSubscriptions(DateTime $date, $providerIdsToIgnore_array = NULL, $providerId = NULL) {
		$date->setTimezone(new DateTimeZone(config::$timezone));
		$date_as_str = dbGlobal::toISODate($date);
		$params = array();
		$query = "SELECT BP.name as provider_name, count(*) as counter FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users BU";
		$query.= " ON (BS.userid = BU._id)";
		$query.= " LEFT JOIN billing_users_opts BUO";
		$query.= " ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.deleted = false)";
		$query.= " WHERE";
		$query.= " BS.sub_activated_date <= '".$date_as_str."'";
		$query.= " AND (BS.sub_expires_date IS NULL OR BS.sub_expires_date > '".$date_as_str."')";
		if(isset($providerIdsToIgnore_array) && count($providerIdsToIgnore_array) > 0) {
			$firstLoop = true;
			$query.= " AND BS.providerid not in (";
			foreach($providerIdsToIgnore_array as $providerIdToIgnore) {
				$params[] = $providerIdToIgnore;
				if($firstLoop) {
					$firstLoop = false;
					$query .= "$".(count($params));
				} else {
					$query .= ", $".(count($params));
				}
			}
			$query.= ")";
		}
		if(isset($providerId)) {
			$params[] = $providerId;
			$query.= " AND BS.providerid = $".(count($params));
		}
		$query.= " GROUP BY BP._id";
		$result = pg_query_params(config::getDbConn(), $query, $params);
		$total = 0;
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$total+= $row['counter'];
			$out['providers'][$row['provider_name']]['total'] = $row['counter'];
		}
		// free result
		pg_free_result($result);
		$out['total'] = $total;
		return($out);
	}
	
	public static function getNumberOfActivatedSubscriptions(DateTime $date, $providerId = NULL) {
		$date->setTimezone(new DateTimeZone(config::$timezone));
		$date_as_str = dbGlobal::toISODate($date);
		$params = array();
		$query = "SELECT BP.name as provider_name, count(*) as counter, count(BSB._id) as counter_returning FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users BU";
		$query.= " ON (BS.userid = BU._id)";
		$query.= " LEFT JOIN billing_users_opts BUO";
		$query.= " ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.deleted = false)";
		$query.= " LEFT JOIN billing_users BUB ON (BU.user_reference_uuid = BUB.user_reference_uuid)";
		$query.= " LEFT JOIN billing_subscriptions BSB ON (BSB.userid = BUB._id AND BSB._id < BS._id )";
		$query.= " WHERE (BUO.value not like '%yopmail.com' OR BUO.value is null)";
		$query.= " AND";
		$query.= " date(BS.sub_activated_date AT TIME ZONE 'Europe/Paris') = date('".$date_as_str."')";
		if(isset($providerId)) {
			$params[] = $providerId;
			$query.= " AND BS.providerid = $".(count($params));
		}
		$query.= " GROUP BY BP._id";
		$result = pg_query_params(config::getDbConn(), $query, $params);
		$total = 0;
		$total_returning = 0;
		$total_new = 0;
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$total+= $row['counter'];
			$total_returning+= $row['counter_returning'];
			$total_new+= $row['counter'] - $row['counter_returning'];
			$out['providers'][$row['provider_name']]['total'] = $row['counter'];
			$out['providers'][$row['provider_name']]['returning'] = $row['counter_returning'];
			$out['providers'][$row['provider_name']]['new'] = $row['counter'] - $row['counter_returning'];
		}
		// free result
		pg_free_result($result);
		$out['total'] = $total;
		$out['returning'] = $total_returning;
		$out['new'] = $total_new;
		return($out);
	}
	
	public static function getActivatedSubscriptions(DateTime $date_start, DateTime $date_end) {
		$date_start->setTimezone(new DateTimeZone(config::$timezone));
		$date_start_str = dbGlobal::toISODate($date_start);
		$date_end->setTimezone(new DateTimeZone(config::$timezone));
		$date_end_str = dbGlobal::toISODate($date_end);
		$query = "SELECT BU._id as userid, (CASE WHEN BUO.value is null THEN 'unknown@domain.com' ELSE BUO.value END) as email, BIPL.name as internal_plan_name, BP.name as provider_name FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_plans BPL ON (BS.planid = BPL._id)";
		$query.= " INNER JOIN billing_internal_plans_links BIPLL ON (BIPLL.provider_plan_id = BPL._id)";
		$query.= " INNER JOIN billing_internal_plans BIPL ON (BIPLL.internal_plan_id = BIPL._id)";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users BU";
		$query.= " ON (BS.userid = BU._id)";
		$query.= " LEFT JOIN billing_users_opts BUO";
		$query.= " ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.deleted = false)";
		$query.= " WHERE (BUO.value not like '%yopmail.com' OR BUO.value is null)";
		$query.= " AND";
		$query.= " (BS.sub_activated_date AT TIME ZONE 'Europe/Paris') >= '".$date_start_str."'";
		$query.= " AND";
		$query.= " (BS.sub_activated_date AT TIME ZONE 'Europe/Paris') < '".$date_end_str."'";
		$result = pg_query(config::getDbConn(), $query);
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$sub = array();
			$sub['userid'] = $row['userid'];
			$sub['email'] = $row['email'];
			$sub['internal_plan_name'] = $row['internal_plan_name'];
			$sub['provider_name'] = $row['provider_name'];
			$out[] = $sub;
		}
		// free result
		pg_free_result($result);
		return($out);
	}
	
	public static function getFutureSubscriptions(DateTime $date_start, DateTime $date_end) {
		$date_start->setTimezone(new DateTimeZone(config::$timezone));
		$date_start_str = dbGlobal::toISODate($date_start);
		$date_end->setTimezone(new DateTimeZone(config::$timezone));
		$date_end_str = dbGlobal::toISODate($date_end);
		$query = "SELECT BU._id as userid, (CASE WHEN BUO.value is null THEN 'unknown@domain.com' ELSE BUO.value END) as email, BIPL.name as internal_plan_name, BP.name as provider_name,";
		$query.= " BS.sub_activated_date as sub_activated_date FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_plans BPL ON (BS.planid = BPL._id)";
		$query.= " INNER JOIN billing_internal_plans_links BIPLL ON (BIPLL.provider_plan_id = BPL._id)";
		$query.= " INNER JOIN billing_internal_plans BIPL ON (BIPLL.internal_plan_id = BIPL._id)";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users BU";
		$query.= " ON (BS.userid = BU._id)";
		$query.= " LEFT JOIN billing_users_opts BUO";
		$query.= " ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.deleted = false)";
		$query.= " WHERE (BUO.value not like '%yopmail.com' OR BUO.value is null)";
		$query.= " AND";
		$query.= " BS.sub_status = 'future'";
		$query.= " AND";
		$query.= " (BS.creation_date AT TIME ZONE 'Europe/Paris') >= '".$date_start_str."'";
		$query.= " AND";
		$query.= " (BS.creation_date AT TIME ZONE 'Europe/Paris') < '".$date_end_str."'";
		$result = pg_query(config::getDbConn(), $query);
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$sub = array();
			$sub['userid'] = $row['userid'];
			$sub['email'] = $row['email'];
			$sub['internal_plan_name'] = $row['internal_plan_name'];
			$sub['provider_name'] = $row['provider_name'];
			$sub['sub_activated_date'] = $row['sub_activated_date'];
			$out[] = $sub;
		}
		// free result
		pg_free_result($result);
		return($out);
	}
	
	public static function getNumberOfExpiredSubscriptions(DateTime $dateStart = NULL, DateTime $dateEnd = NULL, $providerId = NULL) {
		$date_start_str = NULL;
		if(isset($dateStart)) {
			$dateStart->setTimezone(new DateTimeZone(config::$timezone));
			$date_start_str = dbGlobal::toISODate($dateStart);
		}
		$date_end_str = NULL;
		if(isset($dateEnd)) {
			$dateEnd->setTimezone(new DateTimeZone(config::$timezone));
			$date_end_str = dbGlobal::toISODate($dateEnd);
		}
		$params = array();
		$query = "SELECT BP.name as provider_name, count(*) as counter,";
		$query.= " sum(CASE WHEN (sub_expires_date = sub_canceled_date) THEN 1 ELSE 0 END) as expired_cause_pb_counter,";
		$query.= " sum(CASE WHEN (sub_canceled_date IS NULL OR sub_expires_date <> sub_canceled_date) THEN 1 ELSE 0 END) as expired_cause_ended_counter";
		$query.= " FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users BU";
		$query.= " ON (BS.userid = BU._id)";
		$query.= " LEFT JOIN billing_users_opts BUO";
		$query.= " ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.deleted = false)";
		$query.= " WHERE";
		$query.= " (BUO.value not like '%yopmail.com' OR BUO.value is null)";
		if(isset($date_start_str)) {
			$query.= " AND";
			$query.= " BS.sub_expires_date >= '".$date_start_str."'";
		}
		if(isset($date_end_str)) {
			$query.= " AND";
			$query.= " BS.sub_expires_date <= '".$date_end_str."'";
		}
		if(isset($providerId)) {
			$params[] = $providerId;
			$query.= " AND BS.providerid = $".(count($params));
		}
		$query.= " GROUP BY BP._id";
		$result = pg_query_params(config::getDbConn(), $query, $params);
		$total = 0;
		$total_expired_cause_pb = 0;
		$expired_cause_ended = 0;
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$total+= $row['counter'];
			$total_expired_cause_pb+= $row['expired_cause_pb_counter'];
			$expired_cause_ended+= $row['expired_cause_ended_counter'];
			$out['providers'][$row['provider_name']]['total'] = $row['counter'];
			$out['providers'][$row['provider_name']]['expired_cause_pb'] = $row['expired_cause_pb_counter'];
			$out['providers'][$row['provider_name']]['expired_cause_ended'] = $row['expired_cause_ended_counter'];
		}
		// free result
		pg_free_result($result);
		$out['total'] = $total;
		$out['expired_cause_pb'] = $total_expired_cause_pb;
		$out['expired_cause_ended'] = $expired_cause_ended;
		return($out);	
	}
	
	public static function getNumberOfCanceledSubscriptions(DateTime $date) {
		$date->setTimezone(new DateTimeZone(config::$timezone));
		$date_as_str = dbGlobal::toISODate($date);
		$query = "SELECT BP.name as provider_name, count(*) as counter FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users BU";
		$query.= " ON (BS.userid = BU._id)";
		$query.= " LEFT JOIN billing_users_opts BUO";
		$query.= " ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.deleted = false)";
		$query.= " WHERE";
		$query.= " (BUO.value not like '%yopmail.com' OR BUO.value is null)";
		$query.= " AND";
		$query.= " BS.sub_status = 'canceled'";
		$query.= " AND";
		$query.= " date(BS.sub_canceled_date AT TIME ZONE 'Europe/Paris') = date('".$date_as_str."')";
		$query.= " GROUP BY BP._id";
		$result = pg_query(config::getDbConn(), $query);
		$total = 0;
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$total+= $row['counter'];
			$out['providers'][$row['provider_name']]['total'] = $row['counter'];
		}
		// free result
		pg_free_result($result);
		$out['total'] = $total;
		return($out);
	}
	
	public static function getNumberOfFutureSubscriptions(DateTime $date) {
		$date->setTimezone(new DateTimeZone(config::$timezone));
		$date_as_str = dbGlobal::toISODate($date);
		$query = "SELECT BP.name as provider_name, count(*) as counter FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users BU";
		$query.= " ON (BS.userid = BU._id)";
		$query.= " LEFT JOIN billing_users_opts BUO";
		$query.= " ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.deleted = false)";
		$query.= " WHERE";
		$query.= " (BUO.value not like '%yopmail.com' OR BUO.value is null)";
		$query.= " AND";
		$query.= " BS.sub_status = 'future'";
		$query.= " AND";
		$query.= " date(BS.creation_date AT TIME ZONE 'Europe/Paris') = date('".$date_as_str."')";
		$query.= " GROUP BY BP._id";
		$result = pg_query(config::getDbConn(), $query);
		$total = 0;
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$total+= $row['counter'];
			$out['providers'][$row['provider_name']]['total'] = $row['counter'];
		}
		// free result
		pg_free_result($result);
		$out['total'] = $total;
		return($out);
	}

	/**
	 * Get activated coupons between two supplied dates
	 * A coupon is activated when it status is 'redeemed'
	 *
	 * @param DateTime $dateStart
	 * @param Datetime $dateEnd
	 *
	 * @return array
	 */
	public static function getCouponsActivation(DateTime $dateStart, Datetime $dateEnd)
	{
		$dateStart->setTimezone(new DateTimeZone(config::$timezone));
		$date_start_str = dbGlobal::toISODate($dateStart);
		$dateEnd->setTimezone(new DateTimeZone(config::$timezone));
		$date_end_str = dbGlobal::toISODate($dateEnd);

		$query =<<<EOL
		SELECT BICC.coupon_type AS coupon_type,
		BUO.value AS user_email,
		(CASE WHEN BUICO.value IS NULL THEN BUO.value ELSE BUICO.value END) AS recipient_email,
		BICC.name AS coupons_campaign_name,
		BICC.prefix AS coupons_campaign_prefix
		FROM
		billing_users_internal_coupons BUIC
		INNER JOIN billing_users BU ON (BUIC.userid = BU._id)
		INNER JOIN billing_users_opts BUO ON (BU._id = BUO.userid)
		INNER JOIN billing_internal_coupons BIC ON (BUIC.internalcouponsid = BIC._id)
		INNER JOIN billing_internal_coupons_campaigns BICC ON (BIC.internalcouponscampaignsid = BICC._id)
		LEFT JOIN billing_users_internal_coupons_opts BUICO ON (BUIC._id = BUICO.userinternalcouponsid AND BUICO.key = 'recipientEmail' AND BUICO.deleted = false)
		WHERE
		(BUIC.redeemed_date  AT TIME ZONE 'Europe/Paris') BETWEEN '%s' AND '%s'
		AND BUO.key = 'email'
		AND BUO.deleted = false
EOL;
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

	/**
	 * Get generated cashway coupons between two supplied dates
	 * The coupon must belongs to cashway provider and his status is 'pending'
	 *
	 * @param DateTime $dateStart
	 * @param Datetime $dateEnd
	 *
	 * @return array
	 */
	public static function getCouponsCashwayGenerated(DateTime $dateStart, Datetime $dateEnd)
	{
		$dateStart->setTimezone(new DateTimeZone(config::$timezone));
		$date_start_str = dbGlobal::toISODate($dateStart);
		$dateEnd->setTimezone(new DateTimeZone(config::$timezone));
		$date_end_str = dbGlobal::toISODate($dateEnd);

		$query =<<<EOL
		SELECT BICC.coupon_type AS coupon_type,
		BUO.value AS user_email,
		(CASE WHEN BUICO.value IS NULL THEN BUO.value ELSE BUICO.value END) AS recipient_email,
		BICC.name AS coupons_campaign_name,
		BICC.prefix AS coupons_campaign_prefix
		FROM
		billing_users_internal_coupons BUIC
		INNER JOIN billing_users BU ON (BUIC.userid = BU._id)
		INNER JOIN billing_users_opts BUO ON (BU._id = BUO.userid)
		INNER JOIN billing_internal_coupons BIC ON (BUIC.internalcouponsid = BIC._id)
		INNER JOIN billing_internal_coupons_campaigns BICC ON (BIC.internalcouponscampaignsid = BICC._id)
		LEFT JOIN billing_users_internal_coupons_opts BUICO ON (BUIC._id = BUICO.userinternalcouponsid AND BUICO.key = 'recipientEmail' AND BUICO.deleted = false)
		INNER JOIN billing_provider_coupons_campaigns BPCC ON (BICC._id = BPCC.internalcouponscampaignsid)
		INNER JOIN billing_providers BP ON (BPCC.providerid = BP._id)
		WHERE
		BP.name = 'cashway'
		AND (BUIC.creation_date  AT TIME ZONE 'Europe/Paris') BETWEEN '%s' AND '%s'
		AND BUO.key = 'email'
		AND BUO.deleted = false
EOL;

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
	
	public static function getCouponsAfrGenerated(DateTime $dateStart, Datetime $dateEnd, CouponCampaignType $couponCampaignType)
	{
		$dateStart->setTimezone(new DateTimeZone(config::$timezone));
		$date_start_str = dbGlobal::toISODate($dateStart);
		$dateEnd->setTimezone(new DateTimeZone(config::$timezone));
		$date_end_str = dbGlobal::toISODate($dateEnd);
		
		$query =<<<EOL
		SELECT BICC.coupon_type AS coupon_type,
		BUO.value AS user_email,
		(CASE WHEN BUICO.value IS NULL THEN BUO.value ELSE BUICO.value END) AS recipient_email,
		BICC.name AS coupons_campaign_name,
		BICC.prefix AS coupons_campaign_prefix
		FROM
		billing_users_internal_coupons BUIC
		INNER JOIN billing_users BU ON (BUIC.userid = BU._id)
		INNER JOIN billing_users_opts BUO ON (BU._id = BUO.userid)
		INNER JOIN billing_internal_coupons BIC ON (BUIC.internalcouponsid = BIC._id)
		INNER JOIN billing_internal_coupons_campaigns BICC ON (BIC.internalcouponscampaignsid = BICC._id)
		LEFT JOIN billing_users_internal_coupons_opts BUICO ON (BUIC._id = BUICO.userinternalcouponsid AND BUICO.key = 'recipientEmail' AND BUICO.deleted = false)
		INNER JOIN billing_provider_coupons_campaigns BPCC ON (BICC._id = BPCC.internalcouponscampaignsid)
		INNER JOIN billing_providers BP ON (BPCC.providerid = BP._id)
		WHERE
		BP.name = 'afr'
		AND (BUIC.creation_date  AT TIME ZONE 'Europe/Paris') BETWEEN '%s' AND '%s'
		AND BUO.key = 'email'
		AND BUO.deleted = false
		AND BICC.coupon_type = '%s'
EOL;
		$query = sprintf($query, $date_start_str, $date_end_str, $couponCampaignType->getValue());
		
		$result = pg_query(config::getDbConn(), $query);
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = $row;
		}
		// free result
		pg_free_result($result);
		return $out;		
	}
	
	public static function getTransactions(DateTime $dateStart, Datetime $dateEnd, array $transactionTypes, array $transactionStatus) {
		$dateStart->setTimezone(new DateTimeZone(config::$timezone));
		$date_start_str = dbGlobal::toISODate($dateStart);
		$dateEnd->setTimezone(new DateTimeZone(config::$timezone));
		$date_end_str = dbGlobal::toISODate($dateEnd);
		$params = array();
		$i = 1;
		$query = "SELECT BP.name as provider_name,";
		$query.= " BT.transaction_billing_uuid as transaction_billing_uuid,	BT.transaction_provider_uuid as transaction_provider_uuid,";
		$query.= " BT.transaction_type as transaction_type,	BT.transaction_status as transaction_status,";
		$query.= " CAST((amount_in_cents) AS FLOAT)/100 as amount, BT.currency as currency";
		$query.= " FROM billing_transactions BT";
		$query.= " INNER JOIN billing_providers BP ON (BT.providerid = BP._id)";
		$query.= " WHERE (BT.status_changed_date AT TIME ZONE 'Europe/Paris') BETWEEN '".$date_start_str."' AND '".$date_end_str."'";
		$firstLoop = true;
		if(count($transactionTypes) > 0) {
			foreach ($transactionTypes as $transactionTypeEntry) {
				if($firstLoop == true) {
					$firstLoop = false;
					$query.= " AND BT.transaction_type IN ($".$i;
				} else {
					$query.= ", $".$i;
				}
				$params[] = $transactionTypeEntry;
				//done
				$i++;
			}
			$query.= ")";
		}
		$firstLoop = true;
		if(count($transactionStatus) > 0) {
			foreach ($transactionStatus as $transactionStatusEntry) {
				if($firstLoop == true) {
					$firstLoop = false;
					$query.= " AND BT.transaction_status IN ($".$i;
				} else {
					$query.= ", $".$i;
				}
				$params[] = $transactionStatusEntry;
				//done
				$i++;
			}
			$query.= ")";
		}
		$query.= " ORDER BY BT.updated_date ASC";
		$result = pg_query_params(config::getDbConn(), $query, $params);
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = $row;
		}
		// free result
		pg_free_result($result);
		return $out;
	}
	
	public static function getNumberOfTransactions(DateTime $dateStart, Datetime $dateEnd, array $transactionTypes, array $transactionStatus) {
		$dateStart->setTimezone(new DateTimeZone(config::$timezone));
		$date_start_str = dbGlobal::toISODate($dateStart);
		$dateEnd->setTimezone(new DateTimeZone(config::$timezone));
		$date_end_str = dbGlobal::toISODate($dateEnd);
		$params = array();
		$i = 1;
		$query = "SELECT BP.name as provider_name, BT.transaction_type as transaction_type,";
		$query.= " count(*) as counter,CAST(sum(amount_in_cents) AS FLOAT)/100 as amount,";
		$query.= " BT.currency as currency";
		$query.= " FROM billing_transactions BT";
		$query.= " INNER JOIN billing_providers BP ON (BT.providerid = BP._id)";
		$query.= " WHERE (BT.status_changed_date AT TIME ZONE 'Europe/Paris') BETWEEN '".$date_start_str."' AND '".$date_end_str."'";
		$firstLoop = true;
		if(count($transactionTypes) > 0) {
			foreach ($transactionTypes as $transactionTypeEntry) {
				if($firstLoop == true) {
					$firstLoop = false;
					$query.= " AND BT.transaction_type IN ($".$i;
				} else {
					$query.= ", $".$i;
				}
				$params[] = $transactionTypeEntry;
				//done
				$i++;
			}
			$query.= ")";
		}
		$firstLoop = true;
		if(count($transactionStatus) > 0) {
			foreach ($transactionStatus as $transactionStatusEntry) {
				if($firstLoop == true) {
					$firstLoop = false;
					$query.= " AND BT.transaction_status IN ($".$i;
				} else {
					$query.= ", $".$i;
				}
				$params[] = $transactionStatusEntry;
				//done
				$i++;
			}
			$query.= ")";
		}
		$query.= " GROUP BY BP._id, BT.transaction_type, BT.currency";
		$query.= " ORDER BY BP._id, BT.transaction_type, BT.currency";
		$result = pg_query_params(config::getDbConn(), $query, $params);
		$out = array();
		$out['total'] = 0;
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			if(isset($out['total'])) {
				$out['total']+= $row['counter'];
			} else {
				$out['total'] = $row['counter'];
			}
			if(isset($out['currencies'][$row['currency']])) {
				$out['currencies'][$row['currency']]+= $row['amount'];
			} else {
				$out['currencies'][$row['currency']] = $row['amount'];
			}
			if(isset($out['providers'][$row['provider_name']]['total'])) {
				$out['providers'][$row['provider_name']]['total']+= $row['counter'];
			} else {
				$out['providers'][$row['provider_name']]['total'] = $row['counter'];
			}
			if(isset($out['providers'][$row['provider_name']]['currencies'][$row['currency']])) {
				$out['providers'][$row['provider_name']]['currencies'][$row['currency']]+= $row['amount'];
			} else {
				$out['providers'][$row['provider_name']]['currencies'][$row['currency']] = $row['amount'];
			}
			if(isset($out['transaction_types'][$row['transaction_type']]['total'])) {
				$out['transaction_types'][$row['transaction_type']]['total']+= $row['counter'];
			} else {
				$out['transaction_types'][$row['transaction_type']]['total'] = $row['counter'];
			}
			if(isset($out['transaction_types'][$row['transaction_type']]['currencies'][$row['currency']])) {
			 	$out['transaction_types'][$row['transaction_type']]['currencies'][$row['currency']] += $row['amount'];
			} else {
				$out['transaction_types'][$row['transaction_type']]['currencies'][$row['currency']] = $row['amount'];
			}
			if(isset($out['providers'][$row['provider_name']]['transaction_types'][$row['transaction_type']]['total'])) {
				$out['providers'][$row['provider_name']]['transaction_types'][$row['transaction_type']]['total']+= $row['counter'];
			} else {
				$out['providers'][$row['provider_name']]['transaction_types'][$row['transaction_type']]['total'] = $row['counter'];
			}
			if(isset($out['providers'][$row['provider_name']]['transaction_types'][$row['transaction_type']]['currencies'][$row['currency']])) {
				$out['providers'][$row['provider_name']]['transaction_types'][$row['transaction_type']]['currencies'][$row['currency']]+= $row['amount'];				
			} else {
				$out['providers'][$row['provider_name']]['transaction_types'][$row['transaction_type']]['currencies'][$row['currency']] = $row['amount'];
			}
		}
		// free result
		pg_free_result($result);
		return($out);
	}
	
	public static function getNumberOfCouponsGenerated(DateTime $dateStart, DateTime $dateEnd) {
		
		$dateStart->setTimezone(new DateTimeZone(config::$timezone));
		$date_start_str = dbGlobal::toISODate($dateStart);
		$dateEnd->setTimezone(new DateTimeZone(config::$timezone));
		$date_end_str = dbGlobal::toISODate($dateEnd);
		
		$query =<<<EOL
		SELECT BICC.coupon_type AS coupon_type,
		count(*) as counter
		FROM
		billing_users_internal_coupons BUIC
		INNER JOIN billing_internal_coupons BIC ON (BUIC.internalcouponsid = BIC._id)
		INNER JOIN billing_internal_coupons_campaigns BICC ON (BIC.internalcouponscampaignsid = BICC._id)
		WHERE
		(BUIC.creation_date AT TIME ZONE 'Europe/Paris') BETWEEN '%s' AND '%s'
		GROUP BY BICC._id ORDER BY BICC._id
EOL;
		$query = sprintf($query, $date_start_str, $date_end_str);
		
		$result = pg_query(config::getDbConn(), $query);
		$out = array();
		$out['total'] = 0;
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			if(isset($out['total'])) {
				$out['total']+= $row['counter'];
			} else {
				$out['total'] = $row['counter'];
			}
			if(isset($out['coupon_types'][$row['coupon_type']]['total'])) {
				$out['coupon_types'][$row['coupon_type']]['total']+= $row['counter'];
			} else {
				$out['coupon_types'][$row['coupon_type']]['total'] = $row['counter'];
			}
			//TODO : To be added later (removed)
			/*if(isset($out['providers'][$row['provider_name']]['total'])) {
				$out['providers'][$row['provider_name']]['total']+= $row['counter'];
			} else {
				$out['providers'][$row['provider_name']]['total'] = $row['counter'];
			}
			if(isset($out['providers'][$row['provider_name']]['coupon_types'][$row['coupon_type']]['total'])) {
				$out['providers'][$row['provider_name']]['coupon_types'][$row['coupon_type']]['total']+= $row['counter'];
			} else {
				$out['providers'][$row['provider_name']]['coupon_types'][$row['coupon_type']]['total'] = $row['counter'];
			}*/
		}
		// free result
		pg_free_result($result);
		return $out;	
	}
	
	public static function getNumberOfCouponsActivated(DateTime $dateStart, DateTime $dateEnd) {

		$dateStart->setTimezone(new DateTimeZone(config::$timezone));
		$date_start_str = dbGlobal::toISODate($dateStart);
		$dateEnd->setTimezone(new DateTimeZone(config::$timezone));
		$date_end_str = dbGlobal::toISODate($dateEnd);
		
		$query =<<<EOL
		SELECT BICC.coupon_type AS coupon_type,
		count(*) as counter
		FROM
		billing_users_internal_coupons BUIC
		INNER JOIN billing_internal_coupons BIC ON (BUIC.internalcouponsid = BIC._id)
		INNER JOIN billing_internal_coupons_campaigns BICC ON (BIC.internalcouponscampaignsid = BICC._id)
		WHERE
		(BUIC.redeemed_date AT TIME ZONE 'Europe/Paris') BETWEEN '%s' AND '%s'
		GROUP BY BICC._id ORDER BY BICC._id
EOL;
		$query = sprintf($query, $date_start_str, $date_end_str);
		
		$result = pg_query(config::getDbConn(), $query);
		$out = array();
		$out['total'] = 0;
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			if(isset($out['total'])) {
				$out['total']+= $row['counter'];
			} else {
				$out['total'] = $row['counter'];
			}
			if(isset($out['coupon_types'][$row['coupon_type']]['total'])) {
				$out['coupon_types'][$row['coupon_type']]['total']+= $row['counter'];
			} else {
				$out['coupon_types'][$row['coupon_type']]['total'] = $row['counter'];
			}
			//TODO : To be added later (removed)
			/*if(isset($out['providers'][$row['provider_name']]['total'])) {
				$out['providers'][$row['provider_name']]['total']+= $row['counter'];
			} else {
				$out['providers'][$row['provider_name']]['total'] = $row['counter'];
			}
			if(isset($out['providers'][$row['provider_name']]['coupon_types'][$row['coupon_type']]['total'])) {
				$out['providers'][$row['provider_name']]['coupon_types'][$row['coupon_type']]['total']+= $row['counter'];
			} else {
				$out['providers'][$row['provider_name']]['coupon_types'][$row['coupon_type']]['total'] = $row['counter'];
			}*/
		}
		// free result
		pg_free_result($result);
		return $out;		
	}
	
}

?>