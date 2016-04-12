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
	public static function getNumberOfSubscriptions() {
		$query = "SELECT BP.name as provider_name, count(*) as counter FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users BU";
		$query.= " ON (BS.userid = BU._id)";
		$query.= " LEFT JOIN billing_users_opts BUO";
		$query.= " ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.value not like '%yopmail.com' AND BUO.deleted = 'no')";
		$query.= " GROUP BY BP._id";
		$result = pg_query(config::getDbConn(), $query);
		$total = 0;
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$total+= $row['counter'];
			$out['providers'][$row['provider_name']]['total'] = $row['counter'];
		}
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
	public static function getNumberOfActiveSubscriptions() {
		$query = "SELECT BP.name as provider_name, count(*) as counter FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users BU";
		$query.= " ON (BS.userid = BU._id)";
		$query.= " LEFT JOIN billing_users_opts BUO";
		$query.= " ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.value not like '%yopmail.com' AND BUO.deleted = 'no')";
		$query.= " WHERE";
		$query.= " (CAST(BS.sub_status as varchar) like '%active' AND BP.name = 'recurly')";
		$query.= " OR";
		$query.= " (CAST(BS.sub_status as varchar) like '%active' AND BP.name <> 'recurly' AND sub_period_ends_date > date(CURRENT_TIMESTAMP))";
		$query.= " OR";
		$query.= " (CAST(BS.sub_status as varchar) like '%canceled' AND sub_period_ends_date > date(CURRENT_TIMESTAMP))";
		$query.= " GROUP BY BP._id";
		$result = pg_query(config::getDbConn(), $query);
		$total = 0;
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$total+= $row['counter'];
			$out['providers'][$row['provider_name']]['total'] = $row['counter'];
		}
		$out['total'] = $total;
		return($out);
	}
	
	public static function getNumberOfActivatedSubscriptions(DateTime $date) {
		$date->setTimezone(new DateTimeZone(config::$timezone));
		$date_as_str = dbGlobal::toISODate($date);
		$query = "SELECT BP.name as provider_name, count(*) as counter, count(BSB._id) as counter_returning FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users BU";
		$query.= " ON (BS.userid = BU._id)";
		$query.= " LEFT JOIN billing_users_opts BUO";
		$query.= " ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.value not like '%yopmail.com' AND BUO.deleted = 'no')";
		$query.= " LEFT JOIN billing_users BUB ON (BU.user_reference_uuid = BUB.user_reference_uuid)";
		$query.= " LEFT JOIN  billing_subscriptions BSB ON (BSB.userid = BUB._id AND BSB._id < BS._id )";
		$query.= " WHERE";
		$query.= " BS.sub_status <> 'future'";
		$query.= " AND";
		$query.= " date(BS.sub_activated_date AT TIME ZONE 'Europe/Paris') = date('".$date_as_str."')";
		$query.= " GROUP BY BP._id";
		$result = pg_query(config::getDbConn(), $query);
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
		$out['total'] = $total;
		$out['returning'] = $total_returning;
		$out['new'] = $total_new;
		return($out);
	}
	
	public static function getNumberOfExpiredSubscriptions(DateTime $date = NULL) {
		$date_as_str = NULL;
		if(isset($date)) {
			$date->setTimezone(new DateTimeZone(config::$timezone));
			$date_as_str = dbGlobal::toISODate($date);
		}
		$query = "SELECT BP.name as provider_name, count(*) as counter,";
		$query.= " sum(CASE WHEN (sub_status = 'expired' AND sub_expires_date = sub_canceled_date) THEN 1 ELSE 0 END) as expired_cause_pb_counter,";
		$query.= " sum(CASE WHEN (sub_status = 'expired' AND sub_expires_date <> sub_canceled_date) OR (sub_status = 'canceled') THEN 1 ELSE 0 END) as expired_cause_ended_counter";
		$query.= " FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users BU";
		$query.= " ON (BS.userid = BU._id)";
		$query.= " LEFT JOIN billing_users_opts BUO";
		$query.= " ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.value not like '%yopmail.com' AND BUO.deleted = 'no')";
		$query.= " WHERE";
		$query.= " (BS.sub_status = 'expired'";
		if(isset($date_as_str)) {
			$query.= " AND";
			$query.= " date(BS.sub_expires_date AT TIME ZONE 'Europe/Paris') = date('".$date_as_str."')";
		}
		$query.= " )";
		$query.= " OR";
		$query.= " (BS.sub_status = 'canceled'";
		if(isset($date_as_str)) {
			$query.= " AND";
			$query.= " date(BS.sub_period_ends_date AT TIME ZONE 'Europe/Paris') = date('".$date_as_str."')";
		}
		$query.= " )";
		$query.= " GROUP BY BP._id";
		$result = pg_query(config::getDbConn(), $query);
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
		$query.= " ON (BU._id = BUO.userid AND BUO.key = 'email' AND BUO.value not like '%yopmail.com' AND BUO.deleted = 'no')";
		$query.= " WHERE";
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
		$out['total'] = $total;
		return($out);
	}
	
}

?>