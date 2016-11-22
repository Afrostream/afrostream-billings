<?php

class BillingStatsDataDAO {
	
	private static $sfields = "_id, date, providerid, subs_total, subs_new, subs_expired";
	
	private static function getBillingStatsDataFromRow($row) {
		$out = new BillingStatsData();
		$out->setId($row["_id"]);
		$out->setDate($row["date"] == NULL ? NULL : new DateTime($row["date"]));
		$out->setProviderId($row["providerid"]);
		$out->setSubsTotal($row["subs_total"]);
		$out->setSubsNew($row["subs_new"]);
		$out->setSubsExpired($row["subs_expired"]);
		return($out);
	}
	
	public static function getBillingStatsDataById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_stats_daily WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingStatsDataFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getBillingStatsData($providerid, DateTime $date) {
		$query = "SELECT ".self::$sfields." FROM billing_stats_daily WHERE providerid = $1 AND date = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($providerid, dbGlobal::toISODate($date)));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingStatsDataFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function addBillingStatsData(BillingStatsData $billingStatsData) {
		$query = "INSERT INTO billing_stats_daily (providerid, date, subs_total, subs_new, subs_expired)";
		$query.= " VALUES ($1, $2, $3, $4, $5) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($billingStatsData->getProviderId(),
						dbGlobal::toISODate($billingStatsData->getDate()),
						$billingStatsData->getSubsTotal(),
						$billingStatsData->getSubsNew(),
						$billingStatsData->getSubsExpired()));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingStatsDataById($row[0]));
	}
	
	public static function updateBillingStatsData(BillingStatsData $billingStatsData) {
		$query = "UPDATE billing_stats_daily SET subs_total = $2, subs_new = $3, subs_expired = $4 WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query,
				array($billingStatsData->getId(),
						$billingStatsData->getSubsTotal(),
						$billingStatsData->getSubsNew(),
						$billingStatsData->getSubsExpired()
				));
		// free result
		pg_free_result($result);
		return(self::getBillingStatsDataById($billingStatsData->getId()));		
	}
}

?>