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
		$query.= " INNER JOIN billing_users_opts BUO";
		$query.= " ON (BS.userid = BUO.userid AND BUO.key = 'email' AND BUO.value not like '%yopmail.com' AND BUO.deleted = 'no')";
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
		$query = "SELECT BP.name as provider_name, count(*) as counter FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users_opts BUO";
		$query.= " ON (BS.userid = BUO.userid AND BUO.key = 'email' AND BUO.value not like '%yopmail.com' AND BUO.deleted = 'no')";
		$query.= " WHERE";
		$query.= " BS.sub_status <> 'future'";
		$query.= " AND";
		$query.= " date(BS.sub_activated_date AT TIME ZONE 'Europe/Paris') = date('".$date_as_str."')";
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
	
	public static function getNumberOfExpiredSubscriptions(DateTime $date) {
		$date->setTimezone(new DateTimeZone(config::$timezone));
		$date_as_str = dbGlobal::toISODate($date);
		$query = "SELECT BP.name as provider_name, count(*) as counter,"; 
		$query.= " sum(CASE WHEN sub_expires_date = sub_canceled_date THEN 1 ELSE 0 END) as expired_cause_pb_counter,";
		$query.= " sum(CASE WHEN sub_expires_date <> sub_canceled_date THEN 1 ELSE 0 END) as expired_cause_ended_counter";
		$query.= " FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_providers BP";
		$query.= " ON (BS.providerid = BP._id)";
		$query.= " INNER JOIN billing_users_opts BUO";
		$query.= " ON (BS.userid = BUO.userid AND BUO.key = 'email' AND BUO.value not like '%yopmail.com' AND BUO.deleted = 'no')";
		$query.= " WHERE";
		$query.= " BS.sub_status = 'expired'";
		$query.= " AND";
		$query.= " date(BS.sub_expires_date AT TIME ZONE 'Europe/Paris') = date('".$date_as_str."')";
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
		$query.= " INNER JOIN billing_users_opts BUO";
		$query.= " ON (BS.userid = BUO.userid AND BUO.key = 'email' AND BUO.value not like '%yopmail.com' AND BUO.deleted = 'no')";
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