<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../utils/utils.php';
require_once __DIR__ . '/../utils/MoneyUtils.php';

use MyCLabs\Enum\Enum;

class dbGlobal {
	
	private static $curenciesForDisplay = array(
			'XOF' => 'FCFA'
	);
	
	public static function toISODate(DateTime $str = NULL)
	{
		if($str == NULL) {
			return(NULL);
		}
		return($str->format(DateTime::ISO8601));
	}
	
	public static function loadSqlResult($connection, $query, $limit = 0, $offset = 0) {
		$params = array();
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$result = pg_query_params($connection, $query, $params);
		$fieldNames = array();
		$i = pg_num_fields($result);
		for($j = 0; $j < $i; $j++) {
			$fieldNames[] = pg_field_name($result, $j);
		}
		$out = array();
		$out['field_names'] = $fieldNames;
		$out['total_counter'] = 0;
		$out['rows'] = array();
		$out['lastId'] = NULL;
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out['total_counter'] = $row['total_counter'];
			$out['rows'][] = $row;
			$out['lastId'] = $row['_id'];
		}
		// free result
		pg_free_result($result);
		return($out);
	}
	
	public static function getCurrencyForDisplay($currency) {
		if(array_key_exists($currency, self::$curenciesForDisplay)) {
			return(self::$curenciesForDisplay[$currency]);
		}
		return($currency);
	}
	
}

class UserDAO {
	
	private static $sfields = <<<EOL
	BU._id, BU.creation_date, BU.providerid, 
	BU.user_billing_uuid, BU.user_reference_uuid, 
	BU.user_provider_uuid, BU.deleted,
	BU.chartmogul_customer_uuid, BU.chartmogul_merge_status, BU.platformid
EOL;
	
	private static function getUserFromRow($row) {
		$out = new User();
		$out->setId($row["_id"]);
		$out->setUserBillingUuid($row["user_billing_uuid"]);
		$out->setCreationDate($row["creation_date"] == NULL ? NULL : new DateTime($row["creation_date"]));
		$out->setProviderId($row["providerid"]);
		$out->setUserReferenceUuid($row["user_reference_uuid"]);
		$out->setUserProviderUuid($row["user_provider_uuid"]);
		$out->setDeleted($row["deleted"] == 't' ? true : false);
		$out->setChartmogulCustomerUuid($row["chartmogul_customer_uuid"]);
		$out->setChartmogulMergeStatus($row["chartmogul_merge_status"]);
		$out->setPlatformId($row["platformid"]);
		return($out);
	}
	
	public static function addUser(User $user) {
		$query = "INSERT INTO billing_users (providerid, user_billing_uuid, user_reference_uuid, user_provider_uuid, platformid)";
		$query.= " VALUES ($1, $2, $3, $4, $5) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, 
				array($user->getProviderId(),
					$user->getUserBillingUuid(),
					$user->getUserReferenceUuid(),
					$user->getUserProviderUuid(),
					$user->getPlatformId()));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getUserById($row[0]));
	}
	
	public static function getUserById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_users BU WHERE BU._id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getUserFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getUsersByUserReferenceUuid($user_reference_uuid, $providerid = NULL, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_users BU WHERE BU.deleted = false AND BU.user_reference_uuid = $1 AND BU.platformid = $2";
		if(isset($providerid)) {
			$query.= " AND BU.providerid = $3";
		}
		$query_params = array($user_reference_uuid, $platformId);
		if(isset($providerid)) {
			array_push($query_params, $providerid);
		}
		$result = pg_query_params(config::getDbConn(), $query, $query_params);
		
		$out = array();	
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getUserFromRow($row));
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getUserByUserProviderUuid($providerid, $user_provider_uuid) {
		$query = "SELECT ".self::$sfields." FROM billing_users BU WHERE BU.deleted = false AND BU.providerid = $1 AND BU.user_provider_uuid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($providerid, $user_provider_uuid));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getUserFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getUserByUserBillingUuid($user_billing_uuid, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_users BU WHERE BU.deleted = false AND BU.user_billing_uuid = $1 AND BU.platformid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($user_billing_uuid, $platformId));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getUserFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getUsers($id = NULL, $limit = 0, $offset = 0) {
		$query = "SELECT ".self::$sfields." FROM billing_users BU WHERE BU.deleted = false";
		if(isset($id)) { $query.= " AND BU._id <= ".$id; }
		$query.= " ORDER BY BU._id DESC";//LAST USERS FIRST
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$result = pg_query_params(config::getDbConn(), $query, array());
		$out = array();
	
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getUserFromRow($row));
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getUsersByEmail($email, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_users BU";
		$query.= " INNER JOIN billing_users_opts BUO ON (BU._id = BUO.userid)";
		$query.= " WHERE BU.deleted = false";
		$query.= " AND BUO.key = 'email'";
		$query.= " AND BUO.deleted = false";
		$query.= " AND BUO.value = $1";
		$query.= " AND BU.platformid = $2";
		
		$result = pg_query_params(config::getDbConn(), $query, array($email, $platformId));
		
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getUserFromRow($row));
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getUsersByChartmogulStatus($limit = 0,
			$offset = 0,
			$afterId = NULL,
			$providerIds_array = NULL,
			$chartmogulStatus_array = NULL,
			$platformId) {
		$params = array();
		$query = "SELECT count(*) OVER() as total_counter, ".self::$sfields." FROM billing_users BU";
		$query.= " WHERE BU.deleted = false AND BU.platformid = $1";
		$params[] = $platformId;
		if(isset($providerIds_array) && count($providerIds_array) > 0) {
			$firstLoop = true;
			$query.= " AND BU.providerid in (";
			foreach($providerIds_array as $providerId) {
				$params[] = $providerId;
				if($firstLoop) {
					$firstLoop = false;
					$query .= "$".(count($params));
				} else {
					$query .= ", $".(count($params));
				}
			}
			$query.= ")";
		}
		if(isset($chartmogulStatus_array) && count($chartmogulStatus_array) > 0) {
			$firstLoop = true;
			$query.= " AND BU.chartmogul_merge_status in (";
			foreach($chartmogulStatus_array as $chartmogulStatus) {
				$params[] = $chartmogulStatus;
				if($firstLoop) {
					$firstLoop = false;
					$query .= "$".(count($params));
				} else {
					$query .= ", $".(count($params));
				}
			}
			$query.= ")";
		}
		if(isset($afterId)) {
			$query.= " AND BU._id > ".$afterId;
		}
		$query.= " ORDER BY BU._id ASC";
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$result = pg_query_params(config::getDbConn(), $query, $params);
		$out = array();
		$out['total_counter'] = 0;
		$out['users'] = array();
		$out['lastId'] = NULL;
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out['total_counter'] = $row['total_counter'];
			$out['users'][] = self::getUserFromRow($row);
			$out['lastId'] = $row['_id'];
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function updateChartmogulStatus(User $user) {
		$query = "UPDATE billing_users SET chartmogul_merge_status = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$user->getChartmogulMergeStatus(),
						$user->getId()));
		// free result
		pg_free_result($result);
		return(self::getUserById($user->getId()));
	}
	
	public static function updateChartmogulCustomerUuid(User $user) {
		$query = "UPDATE billing_users SET chartmogul_customer_uuid = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$user->getChartmogulCustomerUuid(),
						$user->getId()));
		// free result
		pg_free_result($result);
		return(self::getUserById($user->getId()));
	}
	
}

class User implements JsonSerializable {
	
	private $_id;
	private $user_billing_uuid;
	private $creation_date;
	private $providerid;
	private $user_reference_uuid;
	private $user_provider_uuid;
	private $deleted;
	private $chartmogul_customer_uuid;
	private $chartmogul_merge_status;
	private $platformId;

	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function setUserBillingUuid($uuid) {
		$this->user_billing_uuid = $uuid;
	}
	
	public function getUserBillingUuid() {
		return($this->user_billing_uuid);
	}
	
	public function getCreationDate() {
		return($this->creation_date);
	}
	
	public function setCreationDate($date) {
		$this->creation_date = $date;
	}
	
	public function getProviderId() {
		return($this->providerid);
	}
	
	public function setProviderId($providerid) {
		$this->providerid = $providerid;
	}
	
	public function setUserReferenceUuid($uuid) {
		$this->user_reference_uuid = $uuid;
	}
	
	public function getUserReferenceUuid() {
		return($this->user_reference_uuid);	
	}
	
	public function setUserProviderUuid($uuid) {
		$this->user_provider_uuid = $uuid;
	}
	
	public function getUserProviderUuid() {
		return($this->user_provider_uuid);
	}
	
	public function getDeleted() {
		return($this->deleted);
	}
	
	public function setDeleted($bool) {
		$this->deleted = $bool;
	}
	
	public function setChartmogulCustomerUuid($uuid) {
		$this->chartmogul_customer_uuid = $uuid;
	}
	
	public function getChartmogulCustomerUuid() {
		return($this->chartmogul_customer_uuid);
	}
	
	public function setChartmogulMergeStatus($status) {
		$this->chartmogul_merge_status = $status;
	}
	
	public function getChartmogulMergeStatus() {
		return($this->chartmogul_merge_status);
	}
	
	public function setPlatformId($platformId) {
		$this->platformId = $platformId;
	}
	
	public function getPlatformId() {
		return($this->platformId);
	}
	
	public function jsonSerialize() {
		return [
			'userBillingUuid' => $this->user_billing_uuid,
			'userReferenceUuid' => $this->user_reference_uuid,
			'userProviderUuid' => $this->user_provider_uuid,
			'provider' => ((ProviderDAO::getProviderById($this->providerid)->jsonSerialize())),
			'userOpts' => ((UserOptsDAO::getUserOptsByUserId($this->_id)->jsonSerialize()))
		];
	}
	
}

class UserOpts {

	private $userid;
	private $opts = array();
	
	public function setUserId($userid) {
		$this->userid = $userid;
	}
	
	public function getUserId() {
		return($this->userid);
	}
	
	public function setOpt($key, $value) {
		$this->opts[$key] = $value;
	}

	/**
	 * Get value for the given option
	 * If filter is requested (default) the return value will be filtered to avoid bad data (ie. firstNameValue,...)
	 *
	 * @param string $key
	 * @param bool   $filter
	 *
	 * @return string|null
	 */
	public function getOpt($key, $filter = true)
	{
		$badValues = ['firstNameValue', 'lastNameValue'];
		if (array_key_exists($key, $this->opts) && !in_array($this->opts[$key], $badValues)) {
			return $this->opts[$key];
		}

		return null;
	}
	
	public function setOpts($opts) {
		$this->opts = $opts;
	}
	
	public function getOpts() {
		return($this->opts);
	}
	
	public function jsonSerialize() {
		return $this->opts;
	}
}

class UserOptsDAO {
	
	public static function getUserOptsByUserId($userid) {
		$query = "SELECT _id, userid, key, value FROM billing_users_opts WHERE deleted = false AND userid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($userid));
		
		$out = new UserOpts();
		$out->setUserId($userid);
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out->setOpt($row["key"], $row["value"]);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function addUserOpts(UserOpts $user_opts) {
		foreach ($user_opts->getOpts() as $k => $v) {
			if(isset($v) && is_scalar($v)) {
				$query = "INSERT INTO billing_users_opts (userid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
						array($user_opts->getUserId(),
								trim($k),
								trim($v)));
				// free result
				pg_free_result($result);
			}
		}
		return(self::getUserOptsByUserId($user_opts->getUserId()));
	}
	
	public static function updateUserOptsKey($userid, $key, $value) {
		if(is_scalar($value)) {
			$query = "UPDATE billing_users_opts SET value = $3 WHERE userid = $1 AND key = $2 AND deleted = false";
			$result = pg_query_params(config::getDbConn(), $query,
					array($userid, $key, trim($value)));
			// free result
			pg_free_result($result);
		}
	}
	
	public static function deleteUserOptsKey($userid, $key) {
		$query = "UPDATE billing_users_opts SET deleted = true WHERE userid = $1 AND key = $2 AND deleted = false";
		$result = pg_query_params(config::getDbConn(), $query, array($userid, $key));
		// free result
		pg_free_result($result);
	}
	
	public static function addUserOptsKey($userid, $key, $value) {
		if(is_scalar($value)) {
			$query = "INSERT INTO billing_users_opts (userid, key, value)";
			$query.= " VALUES ($1, $2, $3) RETURNING _id";
			$result = pg_query_params(config::getDbConn(), $query,
					array($userid,
							trim($key),
							trim($value)));
			// free result
			pg_free_result($result);
		}
	}
	
	public static function deleteUserOptsByUserId($userid) {
		$query = "UPDATE billing_users_opts SET deleted = true WHERE userid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($userid));
		// free result
		pg_free_result($result);
	}
}

class InternalPlanDAO {
	
	private static $sfields = NULL;
	
	public static function init() {
		InternalPlanDAO::$sfields = "BIP._id, BIP.internal_plan_uuid, BIP.name, BIP.description,".
			" BIP.amount_in_cents, BIP.currency, BIP.cycle, BIP.period_unit, BIP.period_length, BIP.thumbid, BIP.vat_rate,".
			" BIP.trial_enabled, BIP.trial_period_length, BIP.trial_period_unit, BIP.is_visible, BIP.details, BIP.platformid";
	}
	
	private static function getInternalPlanFromRow($row) {
		$out = new InternalPlan();
		$out->setId($row["_id"]);
		$out->setInternalPlanUid($row["internal_plan_uuid"]);
		$out->setName($row["name"]);
		$out->setDescription($row["description"]);
		$out->setAmountInCents($row["amount_in_cents"]);
		$out->setCurrency($row["currency"]);
		$out->setCycle(new PlanCycle($row["cycle"]));
		$out->setPeriodUnit(new PlanPeriodUnit($row["period_unit"]));
		$out->setPeriodLength($row["period_length"]);
		$out->setThumbId($row["thumbid"]);
		$out->setVatRate($row["vat_rate"]);
		$out->setTrialEnabled($row["trial_enabled"] == 't' ? true : false);
		$out->setTrialPeriodLength($row["trial_period_length"]);
		$out->setTrialPeriodUnit($row["trial_period_unit"] == NULL ? NULL : new TrialPeriodUnit($row["trial_period_unit"]));
		$out->setIsVisible($row["is_visible"] == 't' ? true : false);
		$out->setDetails(json_decode($row["details"], true));
		$out->setPlatformId($row["platformid"]);
		return($out);
	}
	
	public static function getInternalPlanById($planid) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans BIP WHERE BIP._id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($planid));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getInternalPlanFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getInternalPlanByUuid($internal_plan_uuid, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans BIP WHERE BIP.internal_plan_uuid = $1 AND BIP.platformid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($internal_plan_uuid, $platformId));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getInternalPlanFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getInternalPlanByName($name, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans BIP WHERE BIP.name = $1 AND BIP.platformid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($name, $platformId));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getInternalPlanFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getInternalPlans($providerId = NULL, $contextId = NULL, $isVisible = NULL, $country = NULL, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans BIP";
		$params = array();
		
		$out = array();
		
		if(isset($providerId)) {
			$query.= " INNER JOIN billing_plans BP ON (BIP._id = BP.internal_plan_id)";
		}
		
		if(isset($contextId)) {
			$query.= " INNER JOIN billing_internal_plans_by_context BIPBC ON (BIPBC.internal_plan_id = BIP._id)";
		}
		
		if(isset($country)) {
			$query.= " INNER JOIN billing_internal_plans_by_country BIPBCY ON (BIPBCY.internal_plan_id = BIP._id)";	
		}
		
		$where = ""; 
		if(isset($providerId)) {
			$params[] = $providerId;
			if(empty($where)) {
				$where.= " WHERE ";
			} else {
				$where.= " AND ";
			}
			$where.= "BP.providerid = $".(count($params));
		}
		
		if(isset($contextId)) {
			$params[] = $contextId;
			if(empty($where)) {
				$where.= " WHERE ";
			} else {
				$where.= " AND ";
			}
			$where.= "BIPBC.context_id = $".(count($params));			
		}
		
		if(isset($country)) {
			$params[] = $country;
			if(empty($where)) {
				$where.= " WHERE ";
			} else {
				$where.= " AND ";
			}
			$where.= "BIPBCY.country = $".(count($params));
		}
		
		if(isset($isVisible)) {
			$params[] = $isVisible;
			if(empty($where)) {
				$where.= " WHERE ";
			} else {
				$where.= " AND ";
			}
			$where.= "BIP.is_visible = $".(count($params));
		}
		$params[] = $platformId;
		if(empty($where)) {
			$where.= " WHERE ";
		} else {
			$where.= " AND ";
		}
		$where.= "BIP.platformid = $".(count($params));
		$query.= $where;
		if(isset($contextId)) {
			$query.= " ORDER BY BIPBC.index ASC";
		}
		
		$result = pg_query_params(config::getDbConn(), $query, $params);
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getInternalPlanFromRow($row));
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function addInternalPlan(InternalPlan $internalPlan) {
		$query = "INSERT INTO billing_internal_plans ";
		$query.= "(internal_plan_uuid, name, description, amount_in_cents, currency, cycle, period_unit, period_length, trial_enabled, trial_period_length, trial_period_unit, vat_rate, platformid)";
		$query.= " VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, 
				array($internalPlan->getInternalPlanUuid(),
					$internalPlan->getName(),
					$internalPlan->getDescription(),
					$internalPlan->getAmountInCents(),
					$internalPlan->getCurrency(),
					$internalPlan->getCycle(),
					$internalPlan->getPeriodUnit(),
					$internalPlan->getPeriodLength(),
					$internalPlan->getTrialEnabled(),
					$internalPlan->getTrialPeriodLength(),
					$internalPlan->getTrialPeriodUnit(),
					$internalPlan->getVatRate(),
					$internalPlan->getPlatformId()
				));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getInternalPlanById($row[0]));
	}
	
	public static function getInternalPlanByProviderPlanId($providerPlanId) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans BIP";
		$query.= " INNER JOIN billing_plans BP ON (BIP._id = BP.internal_plan_id)";
		$query.= " WHERE BP._id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($providerPlanId));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getInternalPlanFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

InternalPlanDAO::init();

class PlanCycle extends Enum implements JsonSerializable {
	
	const once = 'once';
	const auto = 'auto';
	
	public function jsonSerialize() {
		return $this->getValue();
	}
}

class PlanPeriodUnit extends Enum implements JsonSerializable {
	
	const day = 'day';
	const month = 'month';
	const year = 'year';
	
	public function jsonSerialize() {
		return $this->getValue();
	}
	
}

class TrialPeriodUnit extends Enum implements JsonSerializable {
	
	const day = 'day';
	const month = 'month';
	
	public function jsonSerialize() {
		return $this->getValue();
	}
	
}

class CouponCampaignType extends Enum implements JsonSerializable {

	const standard    	= 'standard';
	const sponsorship 	= 'sponsorship';
	const prepaid 		= 'prepaid';
	const promo			= 'promo';

	public function jsonSerialize() {
		return $this->getValue();
	}

}

class CouponTimeframe extends Enum implements JsonSerializable {

	const anytime = 'anytime';
	const onSubCreation = 'onSubCreation';
	const onSubLifetime = 'onSubLifetime';
	
	public function jsonSerialize() {
		return $this->getValue();
	}
	
}

class InternalPlan implements JsonSerializable {
		
	private $_id;
	private $internal_plan_uuid;
	private $name;
	private $description;
	private $amount_in_cents;
	private $currency;
	private $cycle;
	private $periodUnit;
	private $periodLength;
	private $showProviderPlans = true;
	private $vatRate = 20;
	private $thumbId;
	private $trialEnabled;
	private $trialPeriodLength;
	private $trialPeriodUnit;
	private $isVisible;
	private $details;
	private $platformId;

	public function getId() {
		return($this->_id);
	}

	public function setId($id) {
		$this->_id = $id;
	}

	public function getInternalPlanUuid() {
		return($this->internal_plan_uuid);
	}

	public function setInternalPlanUid($internal_plan_uuid) {
		$this->internal_plan_uuid = $internal_plan_uuid;
	}

	public function getName() {
		return($this->name);
	}

	public function setName($name) {
		$this->name = $name;
	}

	public function getDescription() {
		return($this->description);
	}

	public function setDescription($description) {
		$this->description = $description;
	}
	
	public function setAmountInCents($integer) {
		$this->amount_in_cents = $integer;
	}
	
	public function getAmountInCents() {
		return($this->amount_in_cents);
	}
	
	public function setCurrency($currency) {
		$this->currency = strtoupper($currency);
	}
	
	public function getCurrency() {
		return($this->currency);
	}
	
	public function getCurrencyForDisplay() {
		return(dbGlobal::getCurrencyForDisplay($this->currency));
	}
	
	public function setCycle(PlanCycle $cycle) {
		$this->cycle = $cycle;
	}
	
	public function getCycle() {
		return($this->cycle);
	}

	public function setPeriodUnit(PlanPeriodUnit $periodUnit) {
		$this->periodUnit = $periodUnit;
	}
	
	public function getPeriodUnit() {
		return($this->periodUnit);	
	}
	
	public function setPeriodLength($periodLength) {
		$this->periodLength = $periodLength;
	}
	
	public function getPeriodLength() {
		return($this->periodLength);
	}
 	
	public function setShowProviderPlans($bool) {
		$this->showProviderPlans = $bool;
	}
	
	public function getShowProviderPlans() {
		return($this->showProviderPlans);
	}
	
	public function setVatRate($value) {
		$this->vatRate = $value;
	}
	
	public function getVatRate() {
		return($this->vatRate);
	}
	
	public function getAmountInCentsExclTax() {
		return(MoneyUtils::getAmountInCentsExclTax($this->amount_in_cents, $this->vatRate));
	}
	
	public function getAmountExclTax() {
		return(MoneyUtils::getAmountExclTax($this->amount_in_cents, $this->vatRate));
	}
	
	public function getAmount() {
		return(MoneyUtils::getAmount($this->amount_in_cents));
	}
	
	public function setThumbId($thumbId) {
		$this->thumbId = $thumbId;
	}
	
	public function getThumbId() {
		return($this->thumbId);
	}
	
	public function setTrialEnabled($trialEnabled) {
		$this->trialEnabled = $trialEnabled;
	}
	
	public function getTrialEnabled() {
		return($this->trialEnabled);
	}
	
	public function setTrialPeriodLength($trialPeriodLength) {
		$this->trialPeriodLength = $trialPeriodLength;
	}
	
	public function getTrialPeriodLength() {
		return($this->trialPeriodLength);
	}
	
	public function setTrialPeriodUnit(TrialPeriodUnit $trialPeriodUnit = NULL) {
		$this->trialPeriodUnit = $trialPeriodUnit;
	}
	
	public function getTrialPeriodUnit() {
		return($this->trialPeriodUnit);
	}
	
	public function setIsVisible($isVisible) {
		$this->isVisible = $isVisible;
	}
	
	public function getIsVisible() {
		return($this->isVisible);
	}
	
	public function setDetails($details) {
		$this->details =$details;
	}
	
	public function getDetails() {
		return($this->details);
	}
	
	public function setPlatformId($platformId) {
		$this->platformId = $platformId;
	}
	
	public function getPlatformId() {
		return($this->platformId);
	}
	
	public function jsonSerialize() {
		$return =
			[
				'internalPlanUuid' => $this->internal_plan_uuid,
				'name' => $this->name,
				'description' => $this->description,
				'details' => $this->details,
				'amountInCents' => $this->amount_in_cents,
				'amount' => (string) number_format((float) $this->amount_in_cents / 100, 2, ',', ''),//Forced to French Locale
				'amountInCentsExclTax' => (string) $this->getAmountInCentsExclTax(),
				'amountExclTax' => number_format((float) $this->getAmountExclTax(), 5, ',', ''),//Forced to French Locale
				'vatRate' => ($this->vatRate == NULL) ? NULL : (string) number_format((float) $this->vatRate, 2, ',', ''),//Forced to French Locale
				'currency' => $this->currency,
				'cycle' => $this->cycle,
				'periodUnit' => $this->periodUnit,
				'periodLength' => $this->periodLength,
				'internalPlanOpts' => (InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($this->_id)->jsonSerialize()),
				'thumb' => ThumbDAO::getThumbById($this->thumbId),
				'trialEnabled' => $this->trialEnabled,
				'trialPeriodUnit' => $this->trialPeriodUnit,
				'trialPeriodLength' => $this->trialPeriodLength,
				'isVisible' => $this->isVisible,
				'countries' => InternalPlanCountryDAO::getInternalPlanCountries($this->_id)
		];
		if($this->showProviderPlans) {
			// <-- by providerPlans -->
			$providerPlans = PlanDAO::getPlansByProviderNameFromInternalPlanId($this->_id, true);
			$return['providerPlans'] = $providerPlans;
			// <-- by paymentMethods -->
			$providerPlansByPaymentMethodTypeArray = array();
			foreach ($providerPlans as $key => $value) {
				$providerName = $key;
				$providerPlan = $value;
				$providerPlanPaymentsMethods = BillingProviderPlanPaymentMethodsDAO::getBillingProviderPlanPaymentMethodsByProviderPlanId($providerPlan->getId());
				foreach ($providerPlanPaymentsMethods as $providerPlanPaymentsMethod) {
					$paymentMethod = BillingPaymentMethodDAO::getBillingPaymentMethodById($providerPlanPaymentsMethod->getPaymentMethodId());
					$providerPlansByPaymentMethodTypeArray[$paymentMethod->getPaymentMethodType()][][$providerName] = $providerPlan;
				}
			}
			//sort it
			$allPaymentMethods = BillingPaymentMethodDAO::getBillingPaymentMethods();
			doSortPaymentMethods($providerPlansByPaymentMethodTypeArray, $allPaymentMethods);
			//
			$return['providerPlansByPaymentMethodType'] = $providerPlansByPaymentMethodTypeArray;
		}
		//CurrencyConversions
		if(getEnv('CURRENCY_CONVERSION_ENABLED') == 1) {
			$toCurrencyArray = explode(';', getEnv('CURRENCY_CONVERSION_INTERNALPLAN_CURRENCY_TARGETS'));
			foreach ($toCurrencyArray as $toCurrency) {
				if($toCurrency != $this->currency) {
					$amountInCentsInForeignCurrency = intval(round(MoneyUtils::getLatestRate($this->currency.'/'.$toCurrency) * $this->amount_in_cents));
					$return['currencyConversions'][$toCurrency]['amountInCents'] = (string) ($amountInCentsInForeignCurrency);
					$return['currencyConversions'][$toCurrency]['amount'] = (string) number_format((float) $amountInCentsInForeignCurrency/ 100, 2, '.', '');
					$return['currencyConversions'][$toCurrency]['amountInCentsExclTax'] = (string) MoneyUtils::getAmountInCentsExclTax($amountInCentsInForeignCurrency, $this->vatRate);
					$return['currencyConversions'][$toCurrency]['amountExclTax'] = number_format((float) MoneyUtils::getAmountExclTax($amountInCentsInForeignCurrency, $this->vatRate), 5, '.', '');
				}
			}
			//anyway current currency is added
			$return['currencyConversions'][$this->currency]['amountInCents'] = $this->amount_in_cents;
			$return['currencyConversions'][$this->currency]['amount'] = (string) number_format((float) $this->amount_in_cents / 100, 2, '.', '');
			$return['currencyConversions'][$this->currency]['amountInCentsExclTax'] = (string) $this->getAmountInCentsExclTax();
			$return['currencyConversions'][$this->currency]['amountExclTax'] = number_format((float) $this->getAmountExclTax(), 5, '.', '');
		} else 
			$return['currencyConversions'] = array();//empty
		return($return);
	}

}

class InternalPlanOpts implements JsonSerializable {

	private $internalplanid;
	private $opts = array();

	public function setInternalPlanId($internalplanid) {
		$this->internalplanid = $internalplanid;
	}

	public function getInternalPlanId() {
		return($this->internalplanid);
	}

	public function setOpt($key, $value) {
		$this->opts[$key] = $value;
	}

	public function setOpts($opts) {
		$this->opts = $opts;
	}

	public function getOpts() {
		return($this->opts);
	}
	
	public function jsonSerialize() {
		return($this->opts);
	}

}

class InternalPlanOptsDAO {

	public static function getInternalPlanOptsByInternalPlanId($internalplanid) {
		$query = "SELECT _id, internalplanid, key, value FROM billing_internal_plans_opts WHERE deleted = false AND internalplanid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($internalplanid));
		
		$out = new InternalPlanOpts();
		$out->setInternalPlanId($internalplanid);
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out->setOpt($row["key"], $row["value"]);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function addInternalPlanOpts(InternalPlanOpts $internalplan_opts) {
		foreach ($internalplan_opts->getOpts() as $k => $v) {
			if(isset($v) && is_scalar($v)) {
				$query = "INSERT INTO billing_internal_plans_opts (internalplanid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
						array($internalplan_opts->getInternalPlanId(),
								trim($k),
								trim($v)));
				// free result
				pg_free_result($result);
			}
		}
		return(self::getInternalPlanOptsByInternalPlanId($internalplan_opts->getInternalPlanId()));
	}
	
	public static function updateInternalPlanOptsKey($internalplanid, $key, $value) {
		if(is_scalar($value)) {
			$query = "UPDATE billing_internal_plans_opts SET value = $3 WHERE internalplanid = $1 AND key = $2 AND deleted = false";
			$result = pg_query_params(config::getDbConn(), $query, array($internalplanid, $key, trim($value)));
			// free result
			pg_free_result($result);
		}
	}
	
	public static function deleteInternalPlanOptsKey($internalplanid, $key) {
		$query = "UPDATE billing_internal_plans_opts SET deleted = true WHERE internalplanid = $1 AND key = $2 AND deleted = false";
		$result = pg_query_params(config::getDbConn(), $query, array($internalplanid, $key));
		// free result
		pg_free_result($result);
	}
	
	public static function addInternalPlanOptsKey($internalplanid, $key, $value) {
		if(is_scalar($value)) {
			$query = "INSERT INTO billing_internal_plans_opts (internalplanid, key, value)";
			$query.= " VALUES ($1, $2, $3) RETURNING _id";
			$result = pg_query_params(config::getDbConn(), $query,
					array($internalplanid,
							trim($key),
							trim($value)));
			// free result
			pg_free_result($result);
		}
	}
	
}

class PlanDAO {
	
	private static $sfields = "BP._id, BP.providerid, BP.plan_uuid, BP.name, BP.description, BP.is_visible, BP.internal_plan_id";
	
	private static function getPlanFromRow($row) {
		$out = new Plan();
		$out->setId($row["_id"]);
		$out->setProviderId($row["providerid"]);
		$out->setPlanUid($row["plan_uuid"]);
		$out->setName($row["name"]);
		$out->setDescription($row["description"]);
		$out->setIsVisible($row["is_visible"] == 't' ? true : false);
		$out->setInternalPlanId($row["internal_plan_id"]);
		return($out);
	}
	
	public static function getPlanByUuid($providerId, $plan_uuid) {
		$query = "SELECT ".self::$sfields." FROM billing_plans BP WHERE BP.providerid = $1 AND BP.plan_uuid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($providerId, $plan_uuid));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getPlanFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getPlanById($plan_id) {
		$query = "SELECT ".self::$sfields." FROM billing_plans BP WHERE BP._id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($plan_id));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getPlanFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getPlanByName($providerId, $name) {
		$query = "SELECT ".self::$sfields." FROM billing_plans BP WHERE BP.providerid = $1 AND BP.name = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($providerId, $name));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getPlanFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getPlans($providerId = NULL) {
		$query = "SELECT ".self::$sfields." FROM billing_plans BP";
		$params = array();
	
		$out = array();
		
		if(isset($providerId)) {
			$query.= " WHERE BP.providerid = $1";
			$params[] = $providerId;
		}
		
		$result = pg_query_params(config::getDbConn(), $query, $params);
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getPlanFromRow($row));
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
		
	public static function addPlan(Plan $plan) {
		$query = "INSERT INTO billing_plans (providerid, plan_uuid, name, description, internal_plan_id)";
		$query.= " VALUES ($1, $2, $3, $4, $5) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($plan->getProviderId(),
					$plan->getPlanUuid(),
					$plan->getName(),
					$plan->getDescription(),
					$plan->getInternalPlanId()
				));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getPlanById($row[0]));
	}
	
	public static function getPlanByInternalPlanId($internalPlanId, $providerId) {
		$query = "SELECT ".self::$sfields." FROM billing_plans BP WHERE BP.internal_plan_id = $1 AND BP.providerid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($internalPlanId, $providerId));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getPlanFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getPlansByProviderNameFromInternalPlanId($internalPlanId, $isVisible = NULL) {
		$query = "SELECT P.name as provider_name, ".self::$sfields." FROM billing_plans BP";
		$query.= " INNER JOIN billing_providers P ON (BP.providerid = P._id)";
		$query.= " WHERE BP.internal_plan_id = $1";
		$params = array();
		$params[] = $internalPlanId;
		if(isset($isVisible)) {
			$params[] = $isVisible;
			$query.= " AND BP.is_visible = $2";
		}
		
		$out = array();
		
		$result = pg_query_params(config::getDbConn(), $query, $params);
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[$row['provider_name']] = self::getPlanFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

class Plan implements JsonSerializable {
	
	private $_id;
	private $plan_uuid;
	private $name;
	private $description;
	private $providerid;
	private $isVisible;
	private $internalPlanId;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getPlanUuid() {
		return($this->plan_uuid);
	}
	
	public function setPlanUid($plan_uuid) {
		$this->plan_uuid = $plan_uuid;
	}
	
	public function getName() {
		return($this->name);
	}
	
	public function setName($name) {
		$this->name = $name;
	}
	
	public function getDescription() {
		return($this->description);
	}
	
	public function setDescription($description) {
		$this->description = $description;
	}
	
	public function getProviderId() {
		return($this->providerid);
	}
	
	public function setProviderId($providerid) {
		$this->providerid = $providerid;
	}
	
	public function setIsVisible($isVisible) {
		$this->isVisible = $isVisible;
	}
	
	public function getIsVisible() {
		return($this->isVisible);
	}
	
	public function setInternalPlanId($internalPlanId) {
		$this->internalPlanId = $internalPlanId;
	}
	
	public function getInternalPlanId() {
		return($this->internalPlanId);
	}
	
	public function jsonSerialize() {
		$provider = ProviderDAO::getProviderById($this->providerid);
		$return = [
				'providerPlanUuid' => $this->plan_uuid,
				'name' => $this->name,
				'description' => $this->description,
				'provider' => $provider,
				'paymentMethods' => BillingProviderPlanPaymentMethodsDAO::getBillingProviderPlanPaymentMethodsByProviderPlanId($this->_id),
				'isVisible' => $this->isVisible
		];
		$providersCouponCodeCompatible = ['afr', 'cashway', 'recurly', 'stripe', 'braintree'];
		$return['isCouponCodeCompatible'] = in_array($provider->getName(), $providersCouponCodeCompatible);
		return($return);
	}
	
}

class PlanOpts {

	private $planid;
	private $opts = array();

	public function setPlanId($planid) {
		$this->planid = $planid;
	}

	public function getPlanId() {
		return($this->planid);
	}

	public function setOpt($key, $value) {
		$this->opts[$key] = $value;
	}

	public function setOpts($opts) {
		$this->opts = $opts;
	}

	public function getOpts() {
		return($this->opts);
	}

}

class PlanOptsDAO {

	public static function getPlanOptsByPlanId($planid) {
		$query = "SELECT _id, planid, key, value FROM billing_plans_opts WHERE deleted = false AND planid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($planid));

		$out = new PlanOpts();
		$out->setPlanId($planid);
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out->setOpt($row["key"], $row["value"]);
		}
		// free result
		pg_free_result($result);

		return($out);
	}

}

class ProviderDAO {
	
	private static $providersById = array();
	
	private static $sfields = "_id, name, uuid, platformid, merchantid, serviceid, api_key, api_secret, webhook_key, webhook_secret, config_file, opts";
	
	private static function getProviderFromRow($row) {
		$out = new Provider();
		$out->setId($row["_id"]);
		$out->setName($row["name"]);
		$out->setUuid($row["uuid"]);
		$out->setPlatformId($row["platformid"]);
		$out->setMerchantId($row["merchantid"]);
		$out->setServiceId($row["serviceid"]);
		$out->setApiKey($row["api_key"]);
		$out->setApiSecret($row["api_secret"]);
		$out->setWebhookKey($row["webhook_key"]);
		$out->setWebhookSecret($row["webhook_secret"]);
		$out->setConfigFile(pg_unescape_bytea($row["config_file"]));
		$out->setOpts(json_decode($row["opts"], true));
		//<-- cache -->
		self::$providersById[$out->getId()] = $out;
		//<-- cache -->
		return($out);
	}
	
	public static function getProviderById($providerid) {
		$out = NULL;
		if(array_key_exists($providerid, self::$providersById)) {
			$out = self::$providersById[$providerid];
		} else {
			$query = "SELECT ".self::$sfields." FROM billing_providers WHERE _id = $1";
			$result = pg_query_params(config::getDbConn(), $query, array($providerid));
			
			if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
				$out = self::getProviderFromRow($row);
			}
			// free result
			pg_free_result($result);	
		}
		return($out);
	}
	
	public static function getProviders($platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_providers WHERE deleted = false AND platformid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($platformId));
	
		$out = array();
	
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getProviderFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getProviderByUuid($uuid) {
		$out = NULL;
		$query = "SELECT ".self::$sfields." FROM billing_providers WHERE uuid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($uuid));
			
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getProviderFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getProviderByName($name, $platformId) {
		$out = NULL;
		$query = "SELECT ".self::$sfields." FROM billing_providers WHERE deleted = false AND name = $1 AND platformid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($name, $platformId));
			
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getProviderFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getProvidersByName($name) {
		$query = "SELECT ".self::$sfields." FROM billing_providers WHERE deleted = false AND name = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($name));
			
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getProviderFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

class Provider implements JsonSerializable {
	
	private $_id;
	private $name;
	private $uuid;
	private $platformId;
	//
	private $merchantId;
	private $serviceId;
	private $apiKey;
	private $apiSecret;
	private $webhookKey;
	private $webhookSecret;
	//
	private $configFile;
	private $opts;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getName() {
		return($this->name);
	}
	
	public function setName($name) {
		$this->name = $name;
	}
	
	public function setUuid($uuid) {
		$this->uuid = $uuid;
	}
	
	public function getUuid() {
		return($this->uuid);
	}
	
	public function setPlatformId($id) {
		$this->platformId = $id;
	}
	
	public function getPlatformId() {
		return($this->platformId);
	}
	
	public function setMerchantId($merchandId) {
		$this->merchantId = $merchandId;
	}
	
	public function getMerchantId() {
		return($this->merchantId);
	}
	
	public function setServiceId($serviceId) {
		$this->serviceId = $serviceId;
	}
	
	public function getServiceId() {
		return($this->serviceId);
	}
	
	public function setApiKey($apiKey) {
		$this->apiKey = $apiKey;
	}
	
	public function getApiKey() {
		return($this->apiKey);
	}
	
	public function setApiSecret($apiSecret) {
		$this->apiSecret = $apiSecret;
	}
	
	public function getApiSecret() {
		return($this->apiSecret);
	}
	
	public function setWebhookKey($webhookKey) {
		$this->webhookKey = $webhookKey;
	}
	
	public function getWebhookKey() {
		return($this->webhookKey);
	}	
	
	public function setWebhookSecret($webhookSecret) {
		$this->webhookSecret = $webhookSecret;
	}
	
	public function getWebhookSecret() {
		return($this->webhookSecret);
	}
	
	public function setConfigFile($configFile) {
		$this->configFile = $configFile;
	}
	
	public function getConfigFile() {
		return($this->configFile);
	}
	
	public function setOpts($opts) {
		$this->opts = $opts;
	}
	
	public function getOpts() {
		return($this->opts);
	}
	
	public function jsonSerialize() {
		return[
			'providerName' => $this->name,
			'providerBillingUuid' => $this->uuid
		];
	}
}

class BillingsSubscriptionDAO {
	
	private static $sfields = NULL;

	public static function init() {
		BillingsSubscriptionDAO::$sfields = "BS._id, BS.subscription_billing_uuid, BS.providerid, BS.userid, BS.planid, BS.creation_date, BS.updated_date, BS.sub_uuid, BS.sub_status,".
			" BS.sub_activated_date, BS.sub_canceled_date, BS.sub_expires_date, BS.sub_period_started_date, BS.sub_period_ends_date,".
			" BS.update_type, BS.updateid, BS.deleted, BS.billinginfoid, BS.platformid, BS.plan_change_notified, BS.plan_change_processed, BS.plan_change_id";
	}
	
	private static function getBillingsSubscriptionFromRow($row) {
		$out = new BillingsSubscription();
		$out->setId($row["_id"]);
		$out->setSubscriptionBillingUuid($row["subscription_billing_uuid"]);
		$out->setProviderId($row["providerid"]);
		$out->setUserId($row["userid"]);
		$out->setPlanId($row["planid"]);
		$out->setCreationDate($row["creation_date"] == NULL ? NULL : new DateTime($row["creation_date"]));
		$out->setUpdatedDate($row["updated_date"] == NULL ? NULL : new DateTime($row["updated_date"]));
		$out->setSubUid($row["sub_uuid"]);
		$out->setSubStatus($row["sub_status"]);
		$out->setSubActivatedDate($row["sub_activated_date"] == NULL ? NULL : new DateTime($row["sub_activated_date"]));
		$out->setSubCanceledDate($row["sub_canceled_date"] == NULL ? NULL : new DateTime($row["sub_canceled_date"]));
		$out->setSubExpiresDate($row["sub_expires_date"] == NULL ? NULL : new DateTime($row["sub_expires_date"]));
		$out->setSubPeriodStartedDate($row["sub_period_started_date"] == NULL ? NULL : new DateTime($row["sub_period_started_date"]));
		$out->setSubPeriodEndsDate($row["sub_period_ends_date"] == NULL ? NULL : new DateTime($row["sub_period_ends_date"]));
		$out->setUpdateType($row["update_type"]);
		$out->setUpdateId($row["updateid"]);
		$out->setDeleted($row["deleted"] == 't' ? true : false);
		$out->setBillingsSubscriptionOpts(BillingsSubscriptionOptsDAO::getBillingsSubscriptionOptsBySubId($row["_id"]));
		$out->setBillingInfoId($row["billinginfoid"]);
		$out->setPlatformId($row["platformid"]);
		$out->setPlanChangeNotified($row["plan_change_notified"] == 't' ? true : false);
		$out->setPlanChangeProcessed($row["plan_change_processed"] == 't' ? true : false);
		$out->setPlanChangeId($row["plan_change_id"]);
		return($out);
	}
	
	public static function getBillingsSubscriptionById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_subscriptions BS WHERE BS._id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingsSubscriptionFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getBillingsSubscriptionBySubscriptionBillingUuid($subscription_billing_uuid, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_subscriptions BS WHERE";
		$query.= " BS.deleted = false AND BS.subscription_billing_uuid = $1 AND BS.platformid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($subscription_billing_uuid, $platformId));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingsSubscriptionFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getBillingsSubscriptionBySubUuid($providerId, $sub_uuid) {
		$query = "SELECT ".self::$sfields." FROM billing_subscriptions BS WHERE BS.deleted = false AND BS.providerid = $1 AND BS.sub_uuid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($providerId, $sub_uuid));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingsSubscriptionFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function addBillingsSubscription(BillingsSubscription $subscription) {
		$query = "INSERT INTO billing_subscriptions (subscription_billing_uuid, providerid, userid, planid,";
		$query.= " sub_uuid, sub_status, sub_activated_date, sub_canceled_date, sub_expires_date,";
		$query.= " sub_period_started_date, sub_period_ends_date, update_type, updateid, deleted, billinginfoid, platformid)";
		$query.= " VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getSubscriptionBillingUuid(),
						$subscription->getProviderId(),
						$subscription->getUserId(),
						$subscription->getPlanId(),
						$subscription->getSubUid(),
						$subscription->getSubStatus(),
						dbGlobal::toISODate($subscription->getSubActivatedDate()),
						dbGlobal::toISODate($subscription->getSubCanceledDate()),
						dbGlobal::toISODate($subscription->getSubExpiresDate()),
						dbGlobal::toISODate($subscription->getSubPeriodStartedDate()),
						dbGlobal::toISODate($subscription->getSubPeriodEndsDate()),
						$subscription->getUpdateType(),
						$subscription->getUpdateId(),
						$subscription->getDeleted() === true ? 'true' : 'false',
						$subscription->getBillingInfoId(),
						$subscription->getPlatformId()));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($row[0]));
	}

	/**
	 * @param BillingsSubscription $subscription
	 * 
	 * @return BillingsSubscription|null
	 */
	public static function updateBillingsSubscription(BillingsSubscription $subscription)
	{
		$query = 'UPDATE billing_subscriptions 
		SET planid=$1,
		sub_status=$2,
		sub_activated_date=$3,
		sub_canceled_date=$4,
		sub_period_started_date=$5,
		sub_period_ends_date=$6,
		sub_expires_date=$7,
		updated_date = CURRENT_TIMESTAMP
		WHERE _id=$8';

		$result = pg_query_params(config::getDbConn(), $query, [
			$subscription->getPlanId(),
			$subscription->getSubStatus(),
			dbGlobal::toISODate($subscription->getSubActivatedDate()),
			dbGlobal::toISODate($subscription->getSubCanceledDate()),
			dbGlobal::toISODate($subscription->getSubPeriodStartedDate()),
			dbGlobal::toISODate($subscription->getSubPeriodEndsDate()),
			dbGlobal::toISODate($subscription->getSubExpiresDate()),
			$subscription->getId()
		]);
		// free result
		pg_free_result($result);
		return self::getBillingsSubscriptionById($subscription->getId());
	}

	//planid
	public static function updatePlanId(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, planid = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getPlanId(),
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}

	//subStatus
	public static function updateSubStatus(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, sub_status = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getSubStatus(),
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//subActivatedDate
	public static function updateSubActivatedDate(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, sub_activated_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($subscription->getSubActivatedDate()),
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//subCanceledDate
	public static function updateSubCanceledDate(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, sub_canceled_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($subscription->getSubCanceledDate()),
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//subExpiresDate
	public static function updateSubExpiresDate(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, sub_expires_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($subscription->getSubExpiresDate()),
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//subPeriodStardedDate
	public static function updateSubStartedDate(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, sub_period_started_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($subscription->getSubPeriodStartedDate()),
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//subPeriodEndsDate
	public static function updateSubEndsDate(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, sub_period_ends_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($subscription->getSubPeriodEndsDate()),
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	

	//UpdateType
	public static function updateUpdateType(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, update_type = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getUpdateType(),
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//UpdateId
	public static function updateUpdateId(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, updateid = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getUpdateId(),
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//updateDeleted
	public static function updateDeleted(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, deleted = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getDeleted() === true ? 'true' : 'false',
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//UpdateBillingInfoId
	public static function updateBillingInfoId(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, billinginfoid = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getBillingInfoId(),
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//updatePlanChangeNotified
	public static function updatePlanChangeNotified(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, plan_change_notified = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getPlanChangeNotified() === true ? 'true' : 'false',
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//updatePlanChangeProcessed
	public static function updatePlanChangeProcessed(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, plan_change_processed = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getPlanChangeProcessed() === true ? 'true' : 'false',
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//updatePlanChangeId
	public static function updatePlanChangeId(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, plan_change_id = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getPlanChangeId(),
						$subscription->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	public static function getBillingsSubscriptionsByPlanId($planId, $status_array = array('active')) {
		$query = "SELECT ".self::$sfields." FROM billing_subscriptions BS WHERE BS.deleted = false AND BS.planid = $1";
		$params = array();
		$params[] = $planId;
		$query.= " AND BS.sub_status in (";
		$firstLoop = true;
		foreach($status_array as $status) {
			$params[] = $status;
			if($firstLoop) {
				$firstLoop = false;
				$query .= "$".(count($params));
			} else {
				$query .= ", $".(count($params));
			}
		}
		$query.= ")";
		$query.= " ORDER BY BS._id ASC";
		$result = pg_query_params(config::getDbConn(), $query, $params);
	
		$out = array();
	
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getBillingsSubscriptionFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getBillingsSubscriptionsByUserId($userId) {
		$query = "SELECT ".self::$sfields." FROM billing_subscriptions BS WHERE BS.deleted = false AND BS.userid = $1 ORDER BY BS.sub_activated_date DESC";
		$result = pg_query_params(config::getDbConn(), $query, array($userId));
		
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getBillingsSubscriptionFromRow($row));
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getBillingsSubscripionByUserReferenceUuid($userReferenceUuid, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_users BU ON (BS.userid = BU._id)";
		$query.= " WHERE BS.deleted = false AND BU.deleted = false AND BU.user_reference_uuid = $1 AND BU.platformid = $2";
		$query.= " ORDER BY BS.sub_activated_date DESC";
		$result = pg_query_params(config::getDbConn(), $query, array($userReferenceUuid, $platformId));
		
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getBillingsSubscriptionFromRow($row));
		}
		// free result
		pg_free_result($result);
		
		return($out);		
	}
	
	public static function deleteBillingsSubscriptionById($id) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, deleted = true WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		// free result
		pg_free_result($result);
	}
	
	public static function getEndingBillingsSubscriptions($limit = 0,
			$offset = 0,
			$providerId,
			DateTime $sub_period_ends_date = NULL,
			$status_array = array('active'),
			$cycle_array = NULL,
			$providerIdsToIgnore_array = NULL,
			$afterId = NULL) {
		$params = array();
		$query = "SELECT count(*) OVER() as total_counter, ".self::$sfields." FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_users BU ON (BS.userid = BU._id)";
		if(isset($cycle_array)) {
			$query.= " INNER JOIN billing_plans BP ON (BS.planid = BP._id)";
			$query.= " INNER JOIN billing_internal_plans BIP ON (BP.internal_plan_id = BIP._id)";
		}
		$query.= " WHERE BU.deleted = false AND BS.deleted = false";
		$query.= " AND BS.sub_status in (";
		$firstLoop = true;
		foreach($status_array as $status) {
			$params[] = $status;
			if($firstLoop) {
				$firstLoop = false;
				$query .= "$".(count($params));
			} else {
				$query .= ", $".(count($params));
			}
		}
		$query.= ")";
		if(isset($providerId)) {
			$params[] = $providerId;
			$query.= " AND BU.providerid = $".(count($params));
		}
		if(isset($providerIdsToIgnore_array) && count($providerIdsToIgnore_array) > 0) {
			$firstLoop = true;
			$query.= " AND BU.providerid not in (";
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
		if(isset($cycle_array)) {
			$firstLoop = true;
			$query.= " AND BIP.cycle in (";
			foreach($cycle_array as $cycle) {
				$params[] = $cycle;
				if($firstLoop) {
					$firstLoop = false;
					$query .= "$".(count($params));
				} else {
					$query .= ", $".(count($params));
				}
			}
			$query.= ")";	
		}
		if(isset($sub_period_ends_date)) {
			$sub_period_ends_date_str = dbGlobal::toISODate($sub_period_ends_date);
			$query.= " AND BS.sub_period_ends_date < '".$sub_period_ends_date_str."'";//STRICT
		}
		if(isset($afterId)) {
			$query.= " AND BS._id > ".$afterId;
		}
		$query.= " ORDER BY BS._id ASC";
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$result = pg_query_params(config::getDbConn(), $query, $params);
		$out = array();
		$out['total_counter'] = 0;
		$out['subscriptions'] = array();
		$out['lastId'] = NULL;
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out['total_counter'] = $row['total_counter'];
			$out['subscriptions'][] = self::getBillingsSubscriptionFromRow($row);
			$out['lastId'] = $row['_id'];
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getRequestingCanceledBillingsSubscriptions($limit = 0,
			$offset = 0,
			$providerId,
			$status_array = array('requesting_canceled'),
			$afterId = NULL) {
		$params = array();
		$query = "SELECT count(*) OVER() as total_counter, ".self::$sfields." FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_users BU ON (BS.userid = BU._id)";
		$query.= " WHERE BU.deleted = false AND BS.deleted = false";
		$query.= " AND BS.sub_status in (";
		$firstLoop = true;
		foreach($status_array as $status) {
			$params[] = $status;
			if($firstLoop) {
				$firstLoop = false;
				$query .= "$".(count($params));
			} else {
				$query .= ", $".(count($params));
			}
		}
		$query.= ")";
		if(isset($providerId)) {
			$params[] = $providerId;
			$query.= " AND BU.providerid = $".(count($params));
		}
		if(isset($afterId)) {
			$query.= " AND BS._id > ".$afterId;
		}
		$query.= " ORDER BY BS._id ASC";
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$result = pg_query_params(config::getDbConn(), $query, $params);
		$out = array();
		$out['total_counter'] = 0;
		$out['subscriptions'] = array();
		$out['lastId'] = NULL;
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out['total_counter'] = $row['total_counter'];
			$out['subscriptions'][] = self::getBillingsSubscriptionFromRow($row);
			$out['lastId'] = $row['_id'];
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
		
}

BillingsSubscriptionDAO::init();

class BillingsSubscription implements JsonSerializable {
	
	private $_id;
	private $subscription_billing_uuid;
	private $providerid;
	private $userid;
	private $planid;
	private $creation_date;
	private $updated_date;
	private $sub_uuid;
	private $sub_status;
	private $sub_activated_date;
	private $sub_canceled_date;
	private $sub_expires_date;
	private $sub_period_started_date;
	private $sub_period_ends_date;
	private $update_type;
	private $updateId;
	private $deleted = false;
	//
	private $is_active;
	//
	private $billingsSubscriptionOpts = NULL;
	//
	private $in_trial = false;
	private $is_cancelable = false;
	private $is_reactivable = false;
	private $is_expirable = false;
	private $is_plan_change_compatible = false;
	//
	private $billinginfoid;
	private $platformId;
	//
	private $plan_change_notified = false;
	private $plan_change_processed = false;
	private $plan_change_id = NULL;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getSubscriptionBillingUuid() {
		return($this->subscription_billing_uuid);
	}
	
	public function setSubscriptionBillingUuid($uuid) {
		$this->subscription_billing_uuid = $uuid;
	}
	
	public function getProviderId() {
		return($this->providerid);
	}
	
	public function setProviderId($providerid) {
		$this->providerid = $providerid;
	}
	
	public function getUserId() {
		return($this->userid);
	}
	
	public function setUserId($userid) {
		$this->userid = $userid;
	}
	
	public function getPlanId() {
		return($this->planid);
	}
	
	public function setPlanId($planid) {
		$this->planid = $planid;
	}
	
	public function getCreationDate() {
		return($this->creation_date);
	}
	
	public function setCreationDate($date) {
		$this->creation_date = $date;
	}
	
	public function getUpdatedDate() {
		return($this->updated_date);
	}
	
	public function setUpdatedDate($date) {
		$this->updated_date = $date;
	}
	
	public function getSubUid() {
		return($this->sub_uuid);
	}
	
	public function setSubUid($sub_uuid) {
		$this->sub_uuid = $sub_uuid;
	}
	
	public function getSubStatus() {
		return($this->sub_status);
	}
	
	public function setSubStatus($sub_status) {
		$this->sub_status = $sub_status;
	}
	
	public function getSubActivatedDate() {
		return($this->sub_activated_date);
	}
	
	public function setSubActivatedDate($date) {
		$this->sub_activated_date = $date;
	}
	
	public function getSubCanceledDate() {
		return($this->sub_canceled_date);
	}
	
	public function setSubCanceledDate($date) {
		$this->sub_canceled_date = $date;
	}
	
	public function getSubExpiresDate() {
		return($this->sub_expires_date);
	}
	
	public function setSubExpiresDate($date) {
		$this->sub_expires_date = $date;
	}
	
	public function getSubPeriodStartedDate() {
		return($this->sub_period_started_date);
	}
	
	public function setSubPeriodStartedDate($date) {
		$this->sub_period_started_date = $date;
	}
	
	public function getSubPeriodEndsDate() {
		return($this->sub_period_ends_date);
	}
	
	public function setSubPeriodEndsDate($date) {
		$this->sub_period_ends_date = $date;
	}
	
	public function getUpdateType() {
		return($this->update_type);
	}
	
	public function setUpdateType($updateType) {
		$this->update_type = $updateType;
	}
	
	public function getUpdateId() {
		return($this->updateid);
	}
	
	public function setUpdateId($id) {
		$this->updateid = $id;
	}
	
	public function getDeleted() {
		return($this->deleted);
	}
	
	public function setDeleted($bool) {
		$this->deleted = $bool;
	}
	
	public function getIsActive() {
		return($this->is_active);
	}
	
	public function setIsActive($bool) {
		$this->is_active = $bool;
	}
	
	public function getBillingsSubscriptionOpts() {
		return($this->billingsSubscriptionOpts);
	}
	
	public function setBillingsSubscriptionOpts($billingsSubscriptionOpts) {
		$this->billingsSubscriptionOpts = $billingsSubscriptionOpts;
	}

	public function setInTrial($boolean)
	{
		$this->in_trial = (boolean) $boolean;
	}

	public function getInTrial()
	{
		return $this->in_trial;
	}

	public function setIsCancelable($boolean)
	{
		$this->is_cancelable = (boolean) $boolean;
	}

	public function getIsCancelable()
	{
		return $this->is_cancelable;
	}
	
	public function setIsReactivable($boolean)
	{
		$this->is_reactivable = (boolean) $boolean;
	}
	
	public function getIsReactivable()
	{
		return $this->is_reactivable;
	}
	
	public function setBillingInfoId($id) {
		$this->billinginfoid = $id;
	}
	
	public function setIsExpirable($boolean) {
		$this->is_expirable = (boolean) $boolean;
	}
	
	public function getIsExpirable() {
		return($this->is_expirable);
	}
	
	public function getBillingInfoId() {
		return($this->billinginfoid);
	}
	
	public function setPlatformId($platformId) {
		$this->platformId = $platformId;
	}
	
	public function getPlatformId() {
		return($this->platformId);
	}
	
	public function setPlanChangeNotified($bool) {
		$this->plan_change_notified = $bool;
	}
	
	public function getPlanChangeNotified() {
		return($this->plan_change_notified);
	}
	
	public function setPlanChangeProcessed($bool) {
		$this->plan_change_processed = $bool;
	}
	
	public function getPlanChangeProcessed() {
		return($this->plan_change_processed);
	}
	
	public function setPlanChangeId($planid) {
		$this->plan_change_id = $planid;
	}
	
	public function getPlanChangeId() {
		return($this->plan_change_id);
	}
	
	public function setIsPlanChangeCompatible($is_plan_change_compatible) {
		$this->is_plan_change_compatible = $is_plan_change_compatible;
	}
	
	public function getIsPlanChangeCompatible() {
		return($this->is_plan_change_compatible);
	}
	
	public function jsonSerialize() {
		$return = [
			'subscriptionBillingUuid' => $this->subscription_billing_uuid,
			'subscriptionProviderUuid' => $this->sub_uuid,
			'isActive' => $this->is_active,
			'inTrial' => ($this->in_trial) ? 'yes' : 'no',
			'isCancelable' => ($this->is_cancelable) ? 'yes' : 'no',
			'isReactivable' => ($this->is_reactivable) ? 'yes' : 'no',
			'isExpirable' => ($this->is_expirable) ? 'yes' : 'no',
			'isPlanChangeCompatible' => ($this->is_plan_change_compatible) ? 'yes' : 'no',
			'user' =>	((UserDAO::getUserById($this->userid)->jsonSerialize())),
			'provider' => ((ProviderDAO::getProviderById($this->providerid)->jsonSerialize())),
			'creationDate' => dbGlobal::toISODate($this->creation_date),
			'updatedDate' => dbGlobal::toISODate($this->updated_date),
			'subStatus' => $this->sub_status,
			'subActivatedDate' => dbGlobal::toISODate($this->sub_activated_date),
			'subCanceledDate' => dbGlobal::toISODate($this->sub_canceled_date),
			'subExpiresDate' => dbGlobal::toISODate($this->sub_expires_date),
			'subPeriodStartedDate' => dbGlobal::toISODate($this->sub_period_started_date),
			'subPeriodEndsDate' => dbGlobal::toISODate($this->sub_period_ends_date),
			'subOpts' => (BillingsSubscriptionOptsDAO::getBillingsSubscriptionOptsBySubId($this->_id)->jsonSerialize()),
			'billingInfo' => ($this->billinginfoid == NULL) ? NULL : (BillingInfoDAO::getBillingInfoByBillingInfoId($this->billinginfoid)->jsonSerialize())
		];
		$internalPlan = InternalPlanDAO::getInternalPlanByProviderPlanId($this->planid);
		$internalPlan->setShowProviderPlans(false);
		$return['internalPlan'] = $internalPlan->jsonSerialize();
		return($return);
	}
	
}

class BillingsSubscriptionOpts implements JsonSerializable {

	private $subid;
	private $opts = array();

	public function setSubId($subid) {
		$this->subid = $subid;
	}

	public function getSubId() {
		return($this->subid);
	}

	public function setOpt($key, $value) {
		$this->opts[$key] = $value;
	}

	public function setOpts($opts) {
		$this->opts = $opts;
	}

	public function getOpts() {
		return($this->opts);
	}

	public function getOpt($key)
	{
		if (array_key_exists($key, $this->opts)) {
			return $this->opts[$key];
		}

		return null;
	}

	public function jsonSerialize() {
		return($this->opts);
	}

}

class BillingsSubscriptionOptsDAO {

	public static function getBillingsSubscriptionOptsBySubId($subid) {
		$query = "SELECT _id, subid, key, value FROM billing_subscriptions_opts WHERE deleted = false AND subid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($subid));

		$out = new BillingsSubscriptionOpts();
		$out->setSubId($subid);
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out->setOpt($row["key"], $row["value"]);
		}
		// free result
		pg_free_result($result);

		return($out);
	}

	public static function addBillingsSubscriptionOpts(BillingsSubscriptionOpts $billingsSubscriptionOpts) {
		foreach ($billingsSubscriptionOpts->getOpts() as $k => $v) {
			if(isset($v) && is_scalar($v)) {
				$query = "INSERT INTO billing_subscriptions_opts (subid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
						array($billingsSubscriptionOpts->getSubId(),
								trim($k),
								trim($v)));
				// free result
				pg_free_result($result);
			}
		}
		return(self::getBillingsSubscriptionOptsBySubId($billingsSubscriptionOpts->getSubId()));
	}

	public static function updateBillingsSubscriptionOptsKey($subid, $key, $value) {
		if(is_scalar($value)) {
			$query = "UPDATE billing_subscriptions_opts SET value = $3 WHERE subid = $1 AND key = $2 AND deleted = false";
			$result = pg_query_params(config::getDbConn(), $query, array($subid, $key, trim($value)));
			// free result
			pg_free_result($result);
		}
	}

	public static function deleteBillingsSubscriptionOptsKey($subid, $key) {
		$query = "UPDATE billing_subscriptions_opts SET deleted = true WHERE subid = $1 AND key = $2 AND deleted = false";
		$result = pg_query_params(config::getDbConn(), $query, array($userid, $key));
		// free result
		pg_free_result($result);
	}

	public static function addBillingsSubscriptionOptsKey($subid, $key, $value) {
		if(is_scalar($value)) {
			$query = "INSERT INTO billing_subscriptions_opts (subid, key, value)";
			$query.= " VALUES ($1, $2, $3) RETURNING _id";
			$result = pg_query_params(config::getDbConn(), $query,
					array($subid,
							trim($key),
							trim($value)));
			// free result
			pg_free_result($result);
		}
	}

	public static function deleteBillingsSubscriptionOptBySubId($subid) {
		$query = "UPDATE billing_subscriptions_opts SET deleted = true WHERE subid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($subid));
		// free result
		pg_free_result($result);
	}
}

class BillingInfo implements JsonSerializable {
	
	private $_id;
	private $billinginfo_billing_uuid;
	private $creationDate;
	private $updatedDate;
	private $firstName;
	private $lastName;
	private $email;
	private $iban;
	private $countryCode;
	private $billingInfoOpts;
	private $paymentMethod;
	
	public function __construct() {
	}
	
	public static function getInstance(array $billing_info_array) {
		$out = new BillingInfo();
		if(array_key_exists('billingInfoBillingUuid', $billing_info_array)) {
			$out->setBillingInfoBillingUuid($billing_info_array['billingInfoBillingUuid']);
		}
		if(array_key_exists('firstName', $billing_info_array)) {
			$out->setFirstName($billing_info_array['firstName']);
		}
		if(array_key_exists('lastName', $billing_info_array)) {
			$out->setLastName($billing_info_array['lastName']);
		}
		if(array_key_exists('email', $billing_info_array)) {
			$out->setEmail($billing_info_array['email']);
		}
		if(array_key_exists('iban', $billing_info_array)) {
			$out->setIban($billing_info_array['iban']);
		}
		if(array_key_exists('countryCode', $billing_info_array)) {
			$out->setCountryCode($billing_info_array['countryCode']);
		}
		if(array_key_exists('billingInfoOpts', $billing_info_array)) {
			$out->setBillingInfoOpts(BillingInfoOpts::getInstance($billing_info_array['billingInfoOpts']));
		}
		if(array_key_exists('paymentMethod', $billing_info_array)) {
			$out->setPaymentMethod(BillingPaymentMethod::getInstance($billing_info_array['paymentMethod']));
		}
		return($out);
	}

	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getId() {
		return($this->_id);
	}

	public function setBillingInfoBillingUuid($id) {
		$this->billinginfo_billing_uuid = $id;
	}
	
	public function getBillingInfoBillingUuid() {
		return($this->billinginfo_billing_uuid);
	}
	
	public function getCreationDate() {
		return($this->creationDate);
	}
	
	public function setCreationDate(DateTime $date) {
		$this->creationDate = $date;
	}
	
	public function getUpdatedDate() {
		return($this->updatedDate);
	}
	
	public function setUpdatedDate(DateTime $date) {
		$this->updatedDate = $date;
	}
	
	public function setFirstName($str) {
		$this->firstName = $str;
	}
	
	public function getFirstName() {
		return($this->firstName);
	}
	
	public function setLastName($str) {
		$this->lastName = $str;
	}
	
	public function getLastName() {
		return($this->lastName);
	}
	
	public function setEmail($str) {
		$this->email = $str;
	}
	
	public function getEmail() {
		return($this->email);
	}
	
	public function setIban($str) {
		$this->iban = $str;
	}
	
	public function getIban() {
		return($this->iban);
	}
	
	public function setCountryCode($str) {
		$this->countryCode = $str;
	}
	
	public function getCountryCode() {
		return($this->countryCode);
	}
	
	public function setBillingInfoOpts(BillingInfoOpts $billingInfoOpts) {
		$this->billingInfoOpts = $billingInfoOpts;
	}
	
	public function getBillingInfoOpts() {
		return($this->billingInfoOpts);
	}
	
	public function setPaymentMethod(BillingPaymentMethod $paymentMethod = NULL) {
		$this->paymentMethod = $paymentMethod;
	}
	
	public function getPaymentMethod() {
		return($this->paymentMethod);
	}
	
	public function jsonSerialize() {
		$return = array();
		$return['billingInfoBillingUuid'] = $this->billinginfo_billing_uuid;
		$return['creationDate'] = dbGlobal::toISODate($this->creationDate);
		$return['updatedDate'] = dbGlobal::toISODate($this->updatedDate);
		$return['firstName'] = $this->firstName;
		$return['lastName'] = $this->lastName;
		$return['email'] = $this->email;
		$return['iban'] = $this->iban;
		$return['countryCode'] = $this->countryCode;
		$return['billingInfoOpts'] = ($this->billingInfoOpts == NULL) ? NULL : $this->billingInfoOpts;
		$return['paymentMethod'] = ($this->paymentMethod == NULL) ? NULL : $this->paymentMethod;
		return($return);
	}
	
}

class BillingInfoDAO {
	
	private static $sfields = '_id, billinginfo_billing_uuid, creation_date, updated_date, first_name, last_name, email, iban, country_code, payment_method_id';
	
	private static function getBillingInfoFromRow($row) {
		$out = new BillingInfo();
		$out->setId($row['_id']);
		$out->setBillingInfoBillingUuid($row['billinginfo_billing_uuid']);
		$out->setCreationDate($row["creation_date"] == NULL ? NULL : new DateTime($row["creation_date"]));
		$out->setUpdatedDate($row["updated_date"] == NULL ? NULL : new DateTime($row["updated_date"]));
		$out->setFirstName($row['first_name']);
		$out->setLastName($row['last_name']);
		$out->setEmail($row['email']);
		$out->setIban($row['iban']);
		$out->setCountryCode($row['country_code']);
		$out->setPaymentMethod(BillingPaymentMethodDAO::getBillingPaymentMethodById($row['payment_method_id']));
		$out->setBillingInfoOpts(BillingInfoOptsDAO::getBillingInfoOptsByBillingInfoId($row['_id']));
		return($out);
	}
	
	public static function getBillingInfoByBillingInfoId($id) {
		$query = "SELECT ".self::$sfields." FROM billing_billing_infos WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingInfoFromRow($row);
		}
		// free result
		pg_free_result($result);
		return($out);
	}
	
	public static function addBillingInfo(BillingInfo $billingInfo) {
		$query = "INSERT INTO billing_billing_infos (billinginfo_billing_uuid, first_name, last_name, email, iban, country_code, payment_method_id)"; 
		$query.= " VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, array(
				$billingInfo->getBillingInfoBillingUuid(),
				$billingInfo->getFirstName(),
				$billingInfo->getLastName(),
				$billingInfo->getEmail(),
				$billingInfo->getIban(),
				$billingInfo->getCountryCode(),
				($billingInfo->getPaymentMethod() == NULL) ? NULL : $billingInfo->getPaymentMethod()->getId()
		));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		$billinginfoid = $row[0];
		$billingInfoOpts = $billingInfo->getBillingInfoOpts();
		if(isset($billingInfoOpts)) {
			$billingInfoOpts->setBillingInfoId($billinginfoid);
			$billingInfoOpts = BillingInfoOptsDAO::addBillingInfoOpts($billingInfoOpts);
			$billingInfo->setBillingInfoOpts($billingInfoOpts);
		}
		return(self::getBillingInfoByBillingInfoId($billinginfoid));
	}
	
	public static function updateCountryCode(BillingInfo $billingInfo) {
		$query = "UPDATE billing_billing_infos SET updated_date = CURRENT_TIMESTAMP, country_code = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingInfo->getCountryCode(),
						$billingInfo->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingInfoByBillingInfoId($billingInfo->getId()));
	}
	
}

class BillingInfoOpts implements JsonSerializable {

	private $billinginfoid;
	private $opts = array();
	
	public function __construct() {
	}
	
	public static function getInstance(array $billing_info_opts_array) {
		$out = new BillingInfoOpts();
		$out->setOpts($billing_info_opts_array);
		return($out);
	}
	
	public function setBillingInfoId($id) {
		$this->billinginfoid = $id;
	}
	
	public function getBillingInfoId() {
		return($this->billinginfoid);
	}

	public function setOpt($key, $value) {
		$this->opts[$key] = $value;
	}

	public function setOpts($opts) {
		$this->opts = $opts;
	}

	public function getOpts() {
		return($this->opts);
	}

	public function getOpt($key)
	{
		if (array_key_exists($key, $this->opts)) {
			return $this->opts[$key];
		}

		return null;
	}
	
	public function jsonSerialize() {
		return($this->opts);
	}
	
}

class BillingInfoOptsDAO {

	public static function getBillingInfoOptsByBillingInfoId($id) {
		$query = "SELECT _id, billinginfoid, key, value FROM billing_billing_infos_opts WHERE deleted = false AND billinginfoid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
	
		$out = new BillingInfoOpts();
		$out->setBillingInfoId($id);
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out->setOpt($row["key"], $row["value"]);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function addBillingInfoOpts(BillingInfoOpts $billingInfoOpts) {
		foreach ($billingInfoOpts->getOpts() as $k => $v) {
			if(isset($v) && is_scalar($v)) {
				$query = "INSERT INTO billing_billing_infos_opts (billinginfoid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
						array($billingInfoOpts->getBillingInfoId(),
								trim($k),
								trim($v)));
				// free result
				pg_free_result($result);
			}
		}
		return(self::getBillingInfoOptsByBillingInfoId($billingInfoOpts->getBillingInfoId()));
	}
	
}

class BillingsWebHookDAO {
	
	private static $sfields = "_id, providerid, post_data, processing_status, creation_date, platformid";
	
	private static function getBillingsWebHookFromRow($row) {
		$out = new BillingsWebHook();
		$out->setId($row["_id"]);
		$out->setProviderId($row["providerid"]);
		$out->setPostData($row["post_data"]);
		$out->setProcessingStatus($row["processing_status"]);
		$out->setCreationDate($row["creation_date"] == NULL ? NULL : new DateTime($row["creation_date"]));
		$out->setPlatformId($row["platformid"]);
		return($out);
	}
	
	public static function getBillingsWebHookById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_webhooks WHERE _id = $1";
		
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingsWebHookFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}

	public static function addBillingsWebHook($platformid, $providerid, $post_data) {
		$query = "INSERT INTO billing_webhooks (platformid, providerid, post_data) VALUES ($1, $2, $3) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, array($platformid, $providerid, $post_data));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingsWebHookById($row[0]));
	}

	public static function updateProcessingStatusById($id, $status) {
		$query = "UPDATE billing_webhooks SET processing_status = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($status ,$id));
		// free result
		pg_free_result($result);
	}
}

class BillingsWebHook {

	private $_id;
	private $providerid;
	private $post_data;
	private $processing_status;
	private $creation_date;
	private $platformId;

	public function getId() {
		return($this->_id);
	}

	public function setId($id) {
		$this->_id = $id;
	}

	public function getProviderId() {
		return($this->providerid);
	}

	public function setProviderId($providerid) {
		$this->providerid = $providerid;
	}

	public function getPostData() {
		return($this->post_data);
	}

	public function setPostData($post_data) {
		$this->post_data = $post_data;
	}

	public function getProcessingStatus() {
		return($this->processing_status);
	}

	public function setProcessingStatus($status) {
		$this->processing_status = $status;
	}

	public function getCreationDate() {
		return($this->creation_date);
	}

	public function setCreationDate($date) {
		$this->creation_date = $date;
	}
	
	public function setPlatformId($platformId) {
		$this->platformId = $platformId;
	}
	
	public function getPlatformId() {
		return($this->platformId);
	}
	
}

class BillingsWebHookLogDAO {
	
	private static $sfields = "_id, webhookid, processing_status, started_date, ended_date, message";
	
	private static function getBillingsWebHookLogFromRow($row) {
		$out = new BillingsWebHookLog();
		$out->setId($row["_id"]);
		$out->setWebHookId($row["webhookid"]);
		$out->setProcessingStatus($row["processing_status"]);
		$out->setStartedDate($row["started_date"] == NULL ? NULL : new DateTime($row["started_date"]));
		$out->setEndedDate($row["ended_date"] == NULL ? NULL : new DateTime($row["ended_date"]));
		$out->setMessage($row["message"]);
		return($out);
	}

	public static function addBillingsWebHookLog($webhook_id) {
		$query = "INSERT INTO billing_webhook_logs (webhookid) VALUES ($1) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, array($webhook_id));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingsWebHookLogById($row[0]));
	}

	public static function updateBillingsWebHookLogProcessingStatus(BillingsWebHookLog $billingsWebHookLog) {
		$query = "UPDATE billing_webhook_logs SET processing_status = $1, ended_date = CURRENT_TIMESTAMP, message = $2 WHERE _id = $3";
		$result = pg_query_params(config::getDbConn(), $query, array($billingsWebHookLog->getProcessingStatus(), $billingsWebHookLog->getMessage(), $billingsWebHookLog->getId()));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingsWebHookLogById($billingsWebHookLog->getId()));
	}

	public static function getBillingsWebHookLogById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_webhook_logs WHERE _id = $1";
		
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingsWebHookLogFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
}

class BillingsWebHookLog {

	private $_id;
	private $webhookid;
	private $processing_status;
	private $started_date;
	private $ended_date;
	private $message;

	public function getId() {
		return($this->_id);
	}

	public function setId($id) {
		$this->_id = $id;
	}

	public function setWebHookId($id) {
		$this->webhookid = $id;
	}

	public function getWebHookId() {
		return($this->webhookid);
	}

	public function getProcessingStatus() {
		return($this->processing_status);
	}

	public function setProcessingStatus($status) {
		$this->processing_status = $status;
	}

	public function getStartedDate() {
		return($this->started_date);
	}

	public function setStartedDate(DateTime $date) {
		$this->started_date = $date;
	}

	public function getEndedDate() {
		return($this->ended_date);
	}

	public function setEndedDate(DateTime $date = NULL) {
		$this->ended_date = $date;
	}

	public function getMessage() {
		return($this->message);
	}

	public function setMessage($msg) {
		$this->message = $msg;
	}
	
}

class BillingsSubscriptionActionLog {
	
	private $_id;
	private $subid;
	private $processing_status;
	private $action_type;
	private $started_date;
	private $ended_date;
	private $message;
	private $processing_status_code = 0;//DEFAULT
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function setSubId($id) {
		$this->subid = $id;
	}
	
	public function getSubId() {
		return($this->subid);
	}
	
	public function getProcessingStatus() {
		return($this->processing_status);
	}
	
	public function setProcessingStatus($status) {
		$this->processing_status = $status;
	}
	
	public function getActionType() {
		return($this->action_type);
	}
	
	public function setActionType($action_type) {
		$this->action_type = $action_type;
	}
	
	public function getStartedDate() {
		return($this->started_date);
	}
	
	public function setStartedDate(DateTime $date) {
		$this->started_date = $date;
	}
	
	public function getEndedDate() {
		return($this->ended_date);
	}
	
	public function setEndedDate(DateTime $date = NULL) {
		$this->ended_date = $date;
	}
	
	public function getMessage() {
		return($this->message);
	}
	
	public function setMessage($msg) {
		$this->message = $msg;
	}
	
	public function getProcessingStatusCode() {
		return($this->processing_status_code);
	}
	
	public function setProcessingStatusCode($status_code) {
		$this->processing_status_code = $status_code;
	}
	
}

class BillingsSubscriptionActionLogDAO {

	private static $sfields = "_id, subid, processing_status, action_type, started_date, ended_date, message, processing_status_code";

	private static function getBillingsSubscriptionActionLogFromRow($row) {
		$out = new BillingsSubscriptionActionLog();
		$out->setId($row["_id"]);
		$out->setSubId($row["subid"]);
		$out->setProcessingStatus($row["processing_status"]);
		$out->setProcessingStatusCode($row["processing_status_code"]);
		$out->setActionType($row["action_type"]);
		$out->setStartedDate($row["started_date"] == NULL ? NULL : new DateTime($row["started_date"]));
		$out->setEndedDate($row["ended_date"] == NULL ? NULL : new DateTime($row["ended_date"]));
		$out->setMessage($row["message"]);
		return($out);
	}

	public static function addBillingsSubscriptionActionLog($subid, $action_type) {
		$query = "INSERT INTO billing_subscriptions_action_logs (subid, action_type, processing_status) VALUES ($1, $2, $3) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, array($subid, $action_type, "running"));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionActionLogById($row[0]));
	}

	public static function updateBillingsSubscriptionActionLogProcessingStatus(BillingsSubscriptionActionLog $billingsSubscriptionActionLog) {
		$query = "UPDATE billing_subscriptions_action_logs SET processing_status = $1, ended_date = CURRENT_TIMESTAMP, message = $2, processing_status_code = $3 WHERE _id = $4";
		$result = pg_query_params(config::getDbConn(), $query, 
				array($billingsSubscriptionActionLog->getProcessingStatus(), 
						$billingsSubscriptionActionLog->getMessage(),
						$billingsSubscriptionActionLog->getProcessingStatusCode(),
						$billingsSubscriptionActionLog->getId()));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingsSubscriptionActionLogById($billingsSubscriptionActionLog->getId()));
	}
	
	public static function getBillingsSubscriptionActionLogById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_subscriptions_action_logs WHERE _id = $1";

		$result = pg_query_params(config::getDbConn(), $query, array($id));

		$out = null;

		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingsSubscriptionActionLogFromRow($row);
		}
		// free result
		pg_free_result($result);

		return($out);
	}
}

class ProcessingLog {
	
	private $_id;
	private $providerid;
	private $processing_type;
	private $processing_status;
	private $started_date;
	private $ended_date;
	private $message;
	private $platformId;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function setProviderId($id) {
		$this->provider = $id;
	}
	
	public function getProviderId() {
		return($this->providerid);
	}
	
	public function setProcessingType($type) {
		$this->processing_type = $type;
	}
	
	public function getProcessingType() {
		return($this->processing_type);
	}
	
	public function getProcessingStatus() {
		return($this->processing_status);
	}
	
	public function setProcessingStatus($status) {
		$this->processing_status = $status;
	}
	
	public function getStartedDate() {
		return($this->started_date);
	}
	
	public function setStartedDate(DateTime $date) {
		$this->started_date = $date;
	}
	
	public function getEndedDate() {
		return($this->ended_date);
	}
	
	public function setEndedDate(DateTime $date = NULL) {
		$this->ended_date = $date;
	}
	
	public function getMessage() {
		return($this->message);
	}
	
	public function setMessage($msg) {
		$this->message = $msg;
	}
	
	public function setPlatformId($platformId) {
		$this->platformId = $platformId;
	}
	
	public function getPlatformId() {
		return($this->platformId);
	}
	
}

class ProcessingLogDAO {

	private static $sfields = "_id, providerid, processing_type, processing_status, started_date, ended_date, message, platformid";
	
	private static function getProcessingLogFromRow($row) {
		$out = new ProcessingLog();
		$out->setId($row["_id"]);
		$out->setProviderId($row["providerid"]);
		$out->setProcessingType($row["processing_type"]);
		$out->setProcessingStatus($row["processing_status"]);
		$out->setStartedDate($row["started_date"] == NULL ? NULL : new DateTime($row["started_date"]));
		$out->setEndedDate($row["ended_date"] == NULL ? NULL : new DateTime($row["ended_date"]));
		$out->setMessage($row["message"]);
		$out->setPlatformId($row["platformid"]);
		return($out);
	}
	
	public static function addProcessingLog($platformId, $providerid, $processing_type) {
		$query = "INSERT INTO billing_processing_logs (platformid, providerid, processing_type, processing_status) VALUES ($1, $2, $3, $4) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, array($platformId, $providerid, $processing_type, "running"));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getProcessingLogById($row[0]));
	}
	
	public static function updateProcessingLogProcessingStatus(ProcessingLog $processingLog) {
		$query = "UPDATE billing_processing_logs SET processing_status = $1, ended_date = CURRENT_TIMESTAMP, message = $2 WHERE _id = $3";
		$result = pg_query_params(config::getDbConn(), $query, array($processingLog->getProcessingStatus(), $processingLog->getMessage(), $processingLog->getId()));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getProcessingLogById($processingLog->getId()));
	}
	
	public static function getProcessingLogById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_processing_logs WHERE _id = $1";
		
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getProcessingLogFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getProcessingLogByDay($platformId, $providerid = NULL, $processing_type, DateTime $day) {
		$dayStr = dbGlobal::toISODate($day);
		$params = array();
		$query = "SELECT ".self::$sfields." FROM billing_processing_logs WHERE platformid = $1 AND processing_type = $2";
		$params[] = $platformId;
		$params[] = $processing_type;
		if(isset($providerid)) {
			$query.= " AND providerid = $3";
			$params[] = $providerid;
		}
		$query.= " AND date(started_date AT TIME ZONE 'Europe/Paris') = date('".$dayStr."')";
		// /!\ Querying with dbGlobal::toISODate($day) in the pg_query_params DOES NOT WORK !!!
		$result = pg_query_params(config::getDbConn(), $query, $params);
		
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getProcessingLogFromRow($row));
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

class Thumb implements JsonSerializable {
	
	private $_id;
	private $path;
	private $imgix;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getPath() {
		return($this->path);
	}
	
	public function setPath($str) {
		$this->path= $str;
	}
	
	public function getImgix() {
		return($this->imgix);
	}
	
	public function setImgix($str) {
		$this->imgix = $str;
	}
	
	public function jsonSerialize() {
		return[
				'path' => $this->path,
				'imgix' => $this->imgix
		];
	}
	
}

class ThumbDAO {
	
	private static $sfields = "_id, path, imgix";
	
	private static function getThumbFromRow($row) {
		$out = new Thumb();
		$out->setId($row["_id"]);
		$out->setPath($row["path"]);
		$out->setImgix($row["imgix"]);
		return($out);
	}
	
	public static function getThumbById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_thumbs WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getThumbFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

class Context implements JsonSerializable {
	
	private $_id;
	private $context_uuid;
	private $context_country;
	private $name;
	private $description;
	private $platformId;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getContextUuid() {
		return($this->context_uuid);
	}
	
	public function setContextUuid($uuid) {
		$this->context_uuid = $uuid;
	}
	
	public function getContextCountry() {
		return($this->context_country);
	}
	
	public function setContextCountry($contextCountry) {
		$this->context_country = $contextCountry;	
	}
	
	public function getName() {
		return($this->name);
	}
	
	public function setName($name) {
		$this->name = $name;
	}
	
	public function getDescription() {
		return($this->description);
	}
	
	public function setDescription($desc) {
		$this->description = $desc;
	}
	
	public function setPlatformId($platformId) {
		$this->platformId = $platformId;
	}
	
	public function getPlatformId() {
		return($this->platformId);
	}

	public function jsonSerialize() {
		$return = [
				'contextBillingUuid' => $this->context_uuid,
				'contextCountry' => $this->context_country,
				'name' => $this->name,
				'description' => $this->description
		];
		$internalPlans = array();
		$internalPlanContexts = InternalPlanContextDAO::getInternalPlanContexts($this->_id);
		foreach ($internalPlanContexts as $internalPlanContext) {
			array_push($internalPlans, InternalPlanDAO::getInternalPlanById($internalPlanContext->getInternalPlanId()));
		}
		$return['internalPlans'] = $internalPlans;
		return($return);
	}
	
}

class ContextDAO {
	
	private static $sfields = "_id, context_uuid, country, name, description, platformid";
	
	private static function getContextFromRow($row) {
		$out = new Context();
		$out->setId($row["_id"]);
		$out->setContextUuid($row["context_uuid"]);
		$out->setContextCountry($row["country"]);
		$out->setName($row["name"]);
		$out->setDescription($row["description"]);
		$out->setPlatformId($row["platformid"]);
		return($out);
	}
	
	public static function getContextById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_contexts WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getContextFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);	
	}
	
	public static function getContext($contextBillingUuid, $contextCountry, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_contexts WHERE context_uuid = $1 AND country = $2 AND platformid = $3";
		$result = pg_query_params(config::getDbConn(), $query, array($contextBillingUuid, $contextCountry, $platformId));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getContextFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getContexts($contextCountry = NULL, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_contexts";
		$params = array();
		$params[] = $platformId;
		$query.= " WHERE platformid = $1";
		if(isset($contextCountry)) {
			$query.= " AND country = $2";
			$params[] = $contextCountry;
		}
		
		$result = pg_query_params(config::getDbConn(), $query, $params);
	
		$out = array();
	
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getContextFromRow($row));
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function addContext(Context $context) {
		$query = "INSERT INTO billing_contexts (context_uuid, country, name, description)";
		$query.= " VALUES ($1, $2, $3, $4, $5) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($context->getContextUuid(),
						$context->getContextCountry(),
						$context->getName(),
						$context->getDescription(),
						$context->getPlatformId()
				));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getContextById($row[0]));
	}
	
}

class InternalPlanCountry implements JsonSerializable {
	
	private $_id;
	private $internalPlanId;
	private $country;
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getId() {
		return($this->_id);
	}
	
	public function setInternalPlanId($internalPlanId) {
		$this->internalPlanId = $internalPlanId;
	}
	
	public function getInternalPlanId() {
		return($this->internalPlanId);
	}
	
	public function setCountry($country) {
		$this->country = $country;
	}
	
	public function getCountry() {
		return($this->country);
	}
	
	public function jsonSerialize() {
		$return =
			[
				'country' => $this->country
		];
		return($return);
	}
	
}

class InternalPlanCountryDAO {
	
	private static $sfields = "_id, internal_plan_id, country";
	
	private static function getInternalPlanCountryFromRow($row) {
		$out = new InternalPlanCountry();
		$out->setId($row["_id"]);
		$out->setInternalPlanId($row["internal_plan_id"]);
		$out->setCountry($row["country"]);
		return($out);
	}

	public static function getInternalPlanCountryById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans_by_country WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
	
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getInternalPlanCountryFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getInternalPlanCountry($internalplanid, $country) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans_by_country WHERE internal_plan_id = $1 AND country = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($internalplanid, $country));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getInternalPlanCountryFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function addInternalPlanCountry(InternalPlanCountry $internalPlanCountry) {
		$query = "INSERT INTO billing_internal_plans_by_country (internal_plan_id, country)";
		$query.= " VALUES ($1, $2) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($internalPlanCountry->getInternalPlanId(),
					$internalPlanCountry->getCountry()
				));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getInternalPlanCountryById($row[0]));
	}
	
	public static function deleteInternalPlanCountryById($id) {
		$query = "DELETE FROM billing_internal_plans_by_country";
		$query.= " WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));		
		// free result
		pg_free_result($result);
	}
	
	public static function getInternalPlanCountries($internalplanid) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans_by_country WHERE internal_plan_id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($internalplanid));
		
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getInternalPlanCountryFromRow($row));
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

class InternalPlanContext {

	private $_id;
	private $internalPlanId;
	private $contextId;
	private $index;

	public function setId($id) {
		$this->_id = $id;
	}

	public function getId() {
		return($this->_id);
	}

	public function setInternalPlanId($internalPlanId) {
		$this->internalPlanId = $internalPlanId;
	}

	public function getInternalPlanId() {
		return($this->internalPlanId);
	}

	public function setContextId($id) {
		$this->contextId = $id;
	}

	public function getContextId() {
		return($this->contextId);
	}
	
	public function setIndex($idx) {
		$this->index = $idx;
	}
	
	public function getIndex() {
		return($this->index);
	}
}

class InternalPlanContextDAO {

	private static $sfields = "_id, internal_plan_id, context_id, index";

	private static function getInternalPlanContextFromRow($row) {
		$out = new InternalPlanContext();
		$out->setId($row["_id"]);
		$out->setInternalPlanId($row["internal_plan_id"]);
		$out->setContextId($row["context_id"]);
		$out->setIndex($row["index"]);
		return($out);
	}

	public static function getInternalPlanContextById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans_by_context WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));

		$out = null;

		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getInternalPlanContextFromRow($row);
		}
		// free result
		pg_free_result($result);

		return($out);
	}

	public static function getInternalPlanContext($internalPlanId, $contextId) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans_by_context WHERE internal_plan_id = $1 AND context_id = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($internalPlanId, $contextId));

		$out = null;

		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getInternalPlanContextFromRow($row);
		}
		// free result
		pg_free_result($result);

		return($out);
	}

	public static function getInternalPlanContexts($contextId) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans_by_context WHERE context_id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($contextId));
	
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getInternalPlanContextFromRow($row));
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function addInternalPlanContext(InternalPlanContext $internalPlanContext) {
		$query = "INSERT INTO billing_internal_plans_by_context (internal_plan_id, context_id, index)";
		$query.= " VALUES ($1, $2, $3) RETURNING _id";
		
		$index = $internalPlanContext->getIndex();
		
		if($index == NULL) {
			$index = self::getMaxIndex($internalPlanContext->getContextId()) + 1;
		}
		$result = pg_query_params(config::getDbConn(), $query,
				array($internalPlanContext->getInternalPlanId(),
						$internalPlanContext->getContextId(),
						$index
				));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getInternalPlanContextById($row[0]));
	}

	public static function deleteInternalPlanContextById($id) {
		$query = "DELETE FROM billing_internal_plans_by_context";
		$query.= " WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		// free result
		pg_free_result($result);
		return($result);
	}
	
	public static function getMaxIndex($contextId) {
		$out = 1;
		$query = "SELECT max(index) as max_index FROM billing_internal_plans_by_context WHERE context_id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($contextId));
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = $row['max_index'];
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function updateIndex(InternalPlanContext $internalPlanContext) {
		$currentInternalPlanContext = self::getInternalPlanContextById($internalPlanContext->getId());
		$old = $currentInternalPlanContext->getIndex();
		$new = $internalPlanContext->getIndex();
		$diff = $old - $new;
		$lower = min(array($old, $new));
		$upper = max(array($old, $new));
		$query = "UPDATE billing_internal_plans_by_context SET index = (index + SIGN($1)) WHERE index BETWEEN $2 AND $3 AND context_id = $4";
		$result = pg_query_params(config::getDbConn(), $query, 
				array($diff,
					$lower,
					$upper,
					$internalPlanContext->getContextId()
				));
		// free result
		pg_free_result($result);
		$query = "UPDATE billing_internal_plans_by_context SET index = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array($new,
					$internalPlanContext->getId()
				));
		// free result
		pg_free_result($result);
		return(self::getInternalPlanContextById($internalPlanContext->getId()));	
	}

}

class UsersRequestsLog {
	
	private $id;
	private $userid;
	private $creation_date;
	
	public function setId($id) {
		$this->id = $id;
	}
	
	public function getId() {
		return($this->id);
	}
	
	public function setUserId($userid) {
		$this->userid = $userid;
	}
	
	public function getUserId() {
		return($this->userid);
	}
	
	public function getCreationDate() {
		return($this->creation_date);
	}
	
	public function setCreationDate($date) {
		$this->creation_date = $date;
	}
	
}

class UsersRequestsLogDAO {
	
	private static $sfields = "_id, userid, creation_date";
	
	private static function getUsersRequestsLogFromRow($row) {
		$out = new UsersRequestsLog();
		$out->setId($row["_id"]);
		$out->setUserId($row["userid"]);
		$out->setCreationDate($row["creation_date"] == NULL ? NULL : new DateTime($row["creation_date"]));
		return($out);
	}
	
	public static function getUsersRequestsLogById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_users_requests_logs WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getUsersRequestsLogFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);		
	}
	
	public static function addUsersRequestsLog(UsersRequestsLog $usersRequestsLog) {
		$query = "INSERT INTO billing_users_requests_logs (userid)";
		$query.= " VALUES ($1) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($usersRequestsLog->getUserId()));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getUsersRequestsLogById($row[0]));
	}
	
	//more recent FIRST
	public static function getLastUsersRequestsLogsByUserId($userid, $limit = 10) {
		$query = "SELECT ".self::$sfields." FROM billing_users_requests_logs WHERE userid = $1";
		if($limit > 0) {
			$query.= " ORDER BY _id DESC LIMIT ".$limit;
		}
		$result = pg_query_params(config::getDbConn(), $query, array($userid));
		
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getUsersRequestsLogFromRow($row));
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

class UsersIban
{
	protected $_id;

	protected $userid;

	protected $iban;

	protected $valid;

	protected $createdDate;

	protected $invalidatedDate;

	public function setId($id)
	{
		$this->_id = $id;
	}

	/**
	 * @return integer
	 */
	public function getId()
	{
		return $this->_id;
	}

	/**
	 * @return integer
	 */
	public function getUserid()
	{
		return $this->userid;
	}

	/**
	 * @param integer $userid
	 *
	 * @return UsersIban
	 */
	public function setUserid($userid)
	{
		$this->userid = $userid;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getIban()
	{
		return $this->iban;
	}

	/**
	 * @param string $iban
	 *
	 * @return UsersIban
	 */
	public function setIban($iban)
	{
		$this->iban = $iban;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getValid()
	{
		return $this->valid;
	}

	/**
	 * @param boolean $valid
	 * @return UsersIban
	 */
	public function setValid($valid)
	{
		$this->valid = (boolean) $valid;

		return $this;
	}

	/**
	 * @return DateTime
	 */
	public function getCreatedDate()
	{
		return $this->createdDate;
	}

	/**
	 * @param string $createdDate
	 *
	 * @return UsersIban
	 */
	public function setCreatedDate($createdDate)
	{
		$this->createdDate = new DateTime($createdDate);

		return $this;
	}

	/**
	 * @return DateTime
	 */
	public function getInvalidatedDate()
	{
		return $this->invalidatedDate;
	}

	/**
	 * @param string  $invalidatedDate
	 *
	 * @return UsersIban
	 */
	public function setInvalidatedDate($invalidatedDate)
	{
		$this->invalidatedDate = (empty($invalidatedDate)) ? null : new DateTime($invalidatedDate);

		return $this;
	}
}

class UsersIbanDao
{
	protected static function getEntityFromRow(array $row)
	{
		$entity  = new UsersIban();
		$entity->setId($row['_id']);
		$entity->setUserid($row['userid']);
		$entity->setIban($row['iban']);
		$entity->setCreatedDate($row['creation_date']);
		$entity->setInvalidatedDate($row['invalidated_date']);
		$entity->setValid($row['valid'] == 't' ? true : false);
		return $entity;
	}

	/**
	 * @param string $iban
	 * @param int    $userid
	 *
	 * @return UsersIban|null
	 */
	public static function getIban($iban, $userid = null)
	{
		$query = 'SELECT * FROM billing_users_iban WHERE iban = $1';
		$params = [$iban];

		if (!is_null($userid)) {
			$query .= ' AND userid= $2';
			$params[] = $userid;
		}

		$query .= ' LIMIT 1';

		$result = pg_query_params(config::getDbConn(), $query, $params);

		$return = [];

		$row = pg_fetch_array($result, null, PGSQL_ASSOC);
		// free result
		pg_free_result($result);

		if ($row) {
			return  self::getEntityFromRow($row);
		}

		return null;
	}

	public static function save(UsersIban $userIban)
	{
		$query = "INSERT INTO billing_users_iban (userid, iban, valid, creation_date)";
		$query .= " VALUES ($1, $2, $3, $4) RETURNING _id";

		$data = [
			$userIban->getUserid(),
			$userIban->getIban(),
			$userIban->getValid(),
			$userIban->getCreatedDate()->format(DateTime::ISO8601)
		];

		$result = pg_query_params(config::getDbConn(), $query, $data);

		$row = pg_fetch_row($result);

		if (empty($row[0])) {
			throw new \Exception('Error while recording iban');
		}

		$userIban->setId($row[0]);

		return $userIban;
	}
}

class BillingsCouponsOpts implements JsonSerializable {

	private $couponid;
	private $opts = array();

	public function __construct(array $opts = null)
	{
		if(!empty($opts)) {
			$this->setOpts($opts);
		}
	}

	public function setCouponId($couponid) {
		$this->couponid = $couponid;
	}

	public function getCouponId() {
		return($this->couponid);
	}

	public function setOpt($key, $value) {
		$this->opts[$key] = $value;
	}

	public function setOpts($opts) {
		$this->opts = $opts;
	}

	public function getOpts() {
		return($this->opts);
	}

	public function getOpt($key)
	{
		if (array_key_exists($key, $this->opts)) {
			return $this->opts[$key];
		}

		return null;
	}

	public function jsonSerialize() {
		return($this->opts);
	}

}

class BillingsTransactionStatus extends Enum implements JsonSerializable {

	const waiting = 'waiting';
	const success = 'success';
	const declined = 'declined';
	const failed = 'failed';
	const canceled = 'canceled';
	const void = 'void';

	public function jsonSerialize() {
		return $this->getValue();
	}
}

class BillingsTransactionType extends Enum implements JsonSerializable {

	const purchase = 'purchase';
	const refund = 'refund';
	const verify = 'verify';

	public function jsonSerialize() {
		return $this->getValue();
	}
}

class BillingsTransaction implements JsonSerializable {
	
	private $_id;
	private $transactionLinkId;
	private $providerid;
	private $userid;
	private $subid;
	private $couponid;
	private $invoiceid;
	private $transactionBillingUuid;
	private $transactionProviderUuid;
	private $creationDate;
	private $updatedDate;
	private $transactionCreationDate;
	private $amountInCents;
	private $currency;
	private $country;
	private $transactionStatus;
	private $transactionType;
	private $invoiceProviderUuid;
	private $message;
	private $updateType;
	private $platformId;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getTransactionLinkId() {
		return($this->transactionLinkId);
	}
	
	public function setTransactionLinkId($id) {
		$this->transactionLinkId = $id;
	}
	
	public function setProviderId($id) {
		$this->providerid = $id;
	}
	
	public function getProviderId() {
		return($this->providerid);
	}
	public function setUserId($id) {
		$this->userid = $id;
	}
	
	public function getUserId() {
		return($this->userid);
	}
	
	public function setSubId($id) {
		$this->subid = $id;
	}
	
	public function getSubId() {
		return($this->subid);
	}
	
	public function setCouponId($id) {
		$this->couponid = $id;
	}
	
	public function getCouponId() {
		return($this->couponid);
	}

	public function setInvoiceId($id) {
		$this->invoiceid = $id;
	}
	
	public function getInvoiceId() {
		return($this->invoiceid);
	}
	
	public function setTransactionBillingUuid($id) {
		$this->transactionBillingUuid = $id;
	}
	
	public function getTransactionBillingUuid() {
		return($this->transactionBillingUuid);
	}
	
	public function setTransactionProviderUuid($id) {
		$this->transactionProviderUuid = $id;
	}
	
	public function getTransactionProviderUuid() {
		return($this->transactionProviderUuid);
	}
	
	public function setCreationDate(DateTime $date) {
		$this->creationDate = $date;
	}
	
	public function getCreationDate() {
		return($this->creationDate);
	}
	
	public function setUpdatedDate(DateTime $date) {
		$this->updatedDate = $date;
	}
	
	public function getUpdatedDate() {
		return($this->updatedDate);
	}
	
	public function setTransactionCreationDate(DateTime $date) {
		$this->transactionCreationDate = $date;
	}
	
	public function getTransactionCreationDate() {
		return($this->transactionCreationDate);
	}
	
	public function setAmountInCents($integer) {
		$this->amountInCents = $integer;
	}
	
	public function getAmountInCents() {
		return($this->amountInCents);
	}
	
	public function setCurrency($str) {
		$this->currency = strtoupper($str);
	}
	
	public function getCurrency() {
		return($this->currency);
	}
	
	public function setCountry($str) {
		$this->country = $str;
	}
	
	public function getCountry() {
		return($this->country);
	}

	public function setTransactionStatus(BillingsTransactionStatus $transactionStatus) {
		$this->transactionStatus = $transactionStatus;
	}
	
	public function getTransactionStatus() {
		return($this->transactionStatus);
	}
	
	public function setTransactionType(BillingsTransactionType $transactionType) {
		$this->transactionType = $transactionType;
	}
	
	public function getTransactionType() {
		return($this->transactionType);
	}
	
	public function setInvoiceProviderUuid($id) {
		$this->invoiceProviderUuid = $id;
	}
	
	public function getInvoiceProviderUuid() {
		return($this->invoiceProviderUuid);
	}

	public function setMessage($str) {
		$this->message = $str;
	}
	
	public function getMessage() {
		return($this->message);
	}
	
	public function getUpdateType() {
		return($this->updateType);
	}
	
	public function setUpdateType($updateType) {
		$this->updateType = $updateType;
	}
	
	public function setPlatformId($platformId) {
		$this->platformId = $platformId;
	}
	
	public function getPlatformId() {
		return($this->platformId);
	}
	
	public function jsonSerialize() {
		$return = [
				'transactionBillingUuid' => $this->transactionBillingUuid,
				'transactionProviderUuid' => $this->transactionProviderUuid,
				'provider' => ((ProviderDAO::getProviderById($this->providerid)->jsonSerialize())),
				'transactionCreationDate' => dbGlobal::toISODate($this->transactionCreationDate),
				'creationDate' => dbGlobal::toISODate($this->creationDate),
				'updatedDate' => dbGlobal::toISODate($this->updatedDate),
				'transactionStatus' => $this->transactionStatus,
				'transactionType' => $this->transactionType,
				'currency' => $this->currency,
				'amountInCents' => $this->amountInCents,
				'amount' => (string) number_format((float) $this->amountInCents / 100, 2, ',', ''),//Forced to French Locale
				'transactionOpts' => (BillingsTransactionOptsDAO::getBillingsTransactionOptByTransactionId($this->_id)->jsonSerialize()),
				'linkedTransactions' => BillingsTransactionDAO::getBillingsTransactionsByTransactionLinkId($this->_id)
		];
		return($return);
	}
}

class BillingsTransactionDAO {

	private static $sfields = <<<EOL
	_id, transactionlinkid, providerid, userid, subid, couponid, invoiceid, 
	transaction_billing_uuid, transaction_provider_uuid,
	creation_date, updated_date, transaction_creation_date, 
	amount_in_cents, currency, country, transaction_status, 
	transaction_type, invoice_provider_uuid, message, update_type, platformid
EOL;

	private static function getBillingsTransactionFromRow($row) {
		$out = new BillingsTransaction();
		$out->setId($row["_id"]);
		$out->setTransactionLinkId($row["transactionlinkid"]);
		$out->setProviderId($row["providerid"]);
		$out->setUserId($row["userid"]);
		$out->setSubId($row["subid"]);
		$out->setCouponId($row["couponid"]);
		$out->setInvoiceId($row["invoiceid"]);
		$out->setTransactionBillingUuid($row["transaction_billing_uuid"]);
		$out->setTransactionProviderUuid($row["transaction_provider_uuid"]);
		$out->setCreationDate($row["creation_date"] == NULL ? NULL : new DateTime($row["creation_date"]));
		$out->setUpdatedDate($row["updated_date"] == NULL ? NULL : new DateTime($row["updated_date"]));
		$out->setTransactionCreationDate($row["transaction_creation_date"] == NULL ? NULL : new DateTime($row["transaction_creation_date"]));
		$out->setAmountInCents($row["amount_in_cents"]);
		$out->setCurrency($row["currency"]);
		$out->setCountry($row["country"]);
		$out->setTransactionStatus($row["transaction_status"] == NULL ? NULL : new BillingsTransactionStatus($row["transaction_status"]));
		$out->setTransactionType($row["transaction_type"] == NULL ? NULL : new BillingsTransactionType($row["transaction_type"]));
		$out->setInvoiceProviderUuid($row["invoice_provider_uuid"]);
		$out->setMessage($row["message"]);
		$out->setUpdateType($row["update_type"]);
		$out->setPlatformId($row["platformid"]);
		return($out);
	}

	public static function getBillingsTransactionById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_transactions WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));

		$out = null;

		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingsTransactionFromRow($row);
		}
		// free result
		pg_free_result($result);

		return($out);
	}

	public static function addBillingsTransaction(BillingsTransaction $billingsTransaction) {
		$query = "INSERT INTO billing_transactions";
		$query.= " (transactionlinkid, providerid, userid, subid, couponid, invoiceid,"; 
		$query.= " transaction_billing_uuid, transaction_provider_uuid,";
		$query.= " transaction_creation_date,"; 
		$query.= " amount_in_cents, currency, country, transaction_status, transaction_type,";
		$query.= " invoice_provider_uuid, message, update_type, status_changed_date, platformid)";
		$query.= " VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, $19) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingsTransaction->getTransactionLinkId(),
						$billingsTransaction->getProviderId(),
						$billingsTransaction->getUserId(),
						$billingsTransaction->getSubId(),
						$billingsTransaction->getCouponId(),
						$billingsTransaction->getInvoiceId(),
						$billingsTransaction->getTransactionBillingUuid(),
						$billingsTransaction->getTransactionProviderUuid(),
						dbGlobal::toISODate($billingsTransaction->getTransactionCreationDate()),
						$billingsTransaction->getAmountInCents(),
						$billingsTransaction->getCurrency(),
						$billingsTransaction->getCountry(),
						$billingsTransaction->getTransactionStatus(),
						$billingsTransaction->getTransactionType(),
						$billingsTransaction->getInvoiceProviderUuid(),
						$billingsTransaction->getMessage(),
						$billingsTransaction->getUpdateType(),
						dbGlobal::toISODate($billingsTransaction->getTransactionCreationDate()),
						$billingsTransaction->getPlatformId()));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingsTransactionById($row[0]));
	}
	
	public static function updateBillingsTransaction(BillingsTransaction $billingsTransaction) {
		$currentBillingsTransaction = self::getBillingsTransactionById($billingsTransaction->getId());
		$status_changed_date_has_to_be_set = false;
		if($currentBillingsTransaction->getTransactionStatus() != $billingsTransaction->getTransactionStatus()) {
			$status_changed_date_has_to_be_set = true;
		}
		$query = "UPDATE billing_transactions";
		$query.= " SET updated_date = CURRENT_TIMESTAMP,";
		if($status_changed_date_has_to_be_set == true) {
			$query.= " status_changed_date = CURRENT_TIMESTAMP,";
		}
		$query.= " transactionlinkid = $1, providerid = $2, userid = $3, subid = $4, couponid = $5, invoiceid = $6,"; 
		$query.= " transaction_billing_uuid = $7, transaction_provider_uuid = $8,";
		$query.= " transaction_creation_date = $9,"; 
		$query.= " amount_in_cents = $10, currency = $11, country = $12, transaction_status = $13, transaction_type = $14, invoice_provider_uuid = $15, message = $16, update_type = $17";
		$query.= " WHERE _id = $18";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingsTransaction->getTransactionLinkId(),
						$billingsTransaction->getProviderId(),
						$billingsTransaction->getUserId(),
						$billingsTransaction->getSubId(),
						$billingsTransaction->getCouponId(),
						$billingsTransaction->getInvoiceId(),
						$billingsTransaction->getTransactionBillingUuid(),
						$billingsTransaction->getTransactionProviderUuid(),
						dbGlobal::toISODate($billingsTransaction->getTransactionCreationDate()),
						$billingsTransaction->getAmountInCents(),
						$billingsTransaction->getCurrency(),
						$billingsTransaction->getCountry(),
						$billingsTransaction->getTransactionStatus(),
						$billingsTransaction->getTransactionType(),
						$billingsTransaction->getInvoiceProviderUuid(),
						$billingsTransaction->getMessage(),
						$billingsTransaction->getUpdateType(),
						$billingsTransaction->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingsTransactionById($billingsTransaction->getId()));		
	}
	
	public static function getBillingsTransactionByTransactionProviderUuid($providerId, $transaction_provider_uuid) {
		$query = "SELECT ".self::$sfields." FROM billing_transactions WHERE providerid = $1 AND transaction_provider_uuid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($providerId, $transaction_provider_uuid));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingsTransactionFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getBillingsTransactionByTransactionBillingUuid($transactionBillingUuid, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_transactions WHERE transaction_billing_uuid = $1 AND platformid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($transactionBillingUuid, $platformId));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingsTransactionFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getBillingsTransactionsByTransactionLinkId($transactionLinkId) {
		$query = "SELECT ".self::$sfields." FROM billing_transactions WHERE transactionlinkid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($transactionLinkId));
		
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getBillingsTransactionFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getBillingsTransactions($limit = 0,
			$offset = 0,
			DateTime $afterTransactionCreationDate = NULL,
			$subId = NULL,
			$transaction_type_array = NULL,
			$order = 'descending',
			$platformId) {
		$params = array();
		$query = "SELECT count(*) OVER() as total_counter, ".self::$sfields." FROM billing_transactions";
		$where = "";
		if(isset($subId)) {
			$params[] = $subId;
			if(empty($where)) {
				$where.= " WHERE ";
			} else {
				$where.= " AND ";
			}
			$where.= "subid = $".(count($params));
		}
		if(isset($afterTransactionCreationDate)) {
			$afterTransactionCreationDateSQL = NULL;
			if($order == 'descending') {
				$afterTransactionCreationDateSQL = "transaction_creation_date < ".dbGlobal::toISODate($afterTransactionCreationDate);
			} else {
				$afterTransactionCreationDateSQL = "transaction_creation_date > ".dbGlobal::toISODate($afterTransactionCreationDate);
			}
			if(empty($where)) {
				$where.= " WHERE ";
			} else {
				$where.= " AND ";
			}
			$where.= $afterTransactionCreationDateSQL;
		}
		if(isset($transaction_type_array) && count($transaction_type_array) > 0) {
			$firstLoop = true;
			if(empty($where)) {
				$where.= " WHERE ";
			} else {
				$where.= " AND ";
			}
			$where.= "transaction_type in (";
			foreach($transaction_type_array as $transaction_type) {
				$params[] = $transaction_type;
				if($firstLoop) {
					$firstLoop = false;
					$where.= "$".(count($params));
				} else {
					$where.= ", $".(count($params));
				}
			}
			$where.= ")";
		}
		//platformId
		$params[] = $platformId;
		if(empty($where)) {
			$where.= " WHERE ";
		} else {
			$where.= " AND ";
		}
		$where.= "platformid = $".(count($params));
		//
		$query.= $where;
		if($order == 'descending') {
			$query.= " ORDER BY transaction_creation_date DESC";
		} else {
			$query.= " ORDER BY transaction_creation_date ASC";
		}
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$result = pg_query_params(config::getDbConn(), $query, $params);
		$out = array();
		$out['total_counter'] = 0;
		$out['transactions'] = array();
		$out['last_transaction_creation_date'] = NULL;
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out['total_counter'] = $row['total_counter'];
			$out['transactions'][] = self::getBillingsTransactionFromRow($row);
			$out['last_transaction_creation_date'] = $row['transaction_creation_date'];
		}
		// free result
		pg_free_result($result);

		return($out);
	}
	
}

class BillingPaymentMethod implements JsonSerializable {
	
	private $_id;
	private $paymentMethodType;
	private $index;
	
	public static function getInstance(array $billing_info_opts_array) {
		if(!array_key_exists('paymentMethodType', $billing_info_opts_array)) {
			throw new Exception("'paymentMethodType' field is missing");
		}
		$paymentMethodType = $billing_info_opts_array['paymentMethodType'];
		$billingPaymentMethod = BillingPaymentMethodDAO::getBillingPaymentMethodByPaymentMethodType($paymentMethodType);
		if($billingPaymentMethod == NULL) {
			throw new Exception("'paymentMethodType' field : value '".$paymentMethodType."' is invalid");
		}
		return($billingPaymentMethod);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getId() {
		return($this->_id);
	}
	
	public function setPaymentMethodType($str) {
		$this->paymentMethodType = $str;
	}
	
	public function getPaymentMethodType() {
		return($this->paymentMethodType);
	}
	
	public function setIndex($idx) {
		$this->index = $idx;
	}
	
	public function getIndex() {
		return($this->index);
	}
	
	public function jsonSerialize() {
		return([
				'paymentMethodType' => $this->paymentMethodType,
				'index' => $this->index
		]);
	}
	
}

class BillingPaymentMethodDAO {
	
	private static $sfields = "_id, payment_method_type, index";
	
	private static function getBillingPaymentMethodFromRow($row) {
		$out = new BillingPaymentMethod();
		$out->setId($row["_id"]);
		$out->setPaymentMethodType($row["payment_method_type"]);
		$out->setIndex($row["index"]);
		return($out);
	}
	
	public static function getBillingPaymentMethodById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_payment_methods WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));

		$out = null;

		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingPaymentMethodFromRow($row);
		}
		// free result
		pg_free_result($result);

		return($out);
	}
	
	public static function getBillingPaymentMethodByPaymentMethodType($paymentMethodType) {
		$query = "SELECT ".self::$sfields." FROM billing_payment_methods WHERE payment_method_type = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($paymentMethodType));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingPaymentMethodFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getBillingPaymentMethods() {
		$query = "SELECT ".self::$sfields." FROM billing_payment_methods";
		$result = pg_query(config::getDbConn(), $query);
	
		$out = array();
	
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getBillingPaymentMethodFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
}

class BillingProviderPlanPaymentMethod implements JsonSerializable {
	
	private $_id;
	private $providerPlanId;
	private $paymentMethodId;

	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getId() {
		return($this->_id);
	}
	
	public function setProviderPlanId($id) {
		$this->providerPlanId = $id;
	}
	
	public function getProviderPlanId() {
		return($this->providerPlanId);
	}
	
	public function setPaymentMethodId($id) {
		$this->paymentMethodId = $id;
	}
	
	public function getPaymentMethodId() {
		return($this->paymentMethodId);
	}
	
	public function jsonSerialize() {
		return(BillingPaymentMethodDAO::getBillingPaymentMethodById($this->paymentMethodId));
	}
}

class BillingProviderPlanPaymentMethodsDAO {
	
	private static $sfields = "_id, provider_plan_id, payment_method_id";
	
	private static function getBillingProviderPlanPaymentMethodFromRow($row) {
		$out = new BillingProviderPlanPaymentMethod();
		$out->setId($row["_id"]);
		$out->setProviderPlanId($row["provider_plan_id"]);
		$out->setPaymentMethodId($row["payment_method_id"]);
		return($out);
	}
	
	public static function getBillingProviderPlanPaymentMethodsByProviderPlanId($id) {
		$query = "SELECT ".self::$sfields." FROM billing_provider_plans_payment_methods WHERE provider_plan_id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getBillingProviderPlanPaymentMethodFromRow($row));
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

class BillingInternalCouponsCampaign implements JsonSerializable {

	private $_id;
	private $uuid;
	private $creation_date;
	private $name;
	private $description;
	private $prefix;
	private $discount_type;
	private $amount_in_cents;
	private $currency;
	private $percent;
	private $discount_duration;
	private $discount_duration_unit;
	private $discount_duration_length;
	private $generated_mode;
	private $generated_code_length;
	private	$total_number;
	private $coupon_type;
	private $emails_enabled = false;
	private $expires_date;
	private $partnerid;
	private $platformid;
	private $coupon_timeframes;
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getId() {
		return($this->_id);
	}
	
	public function setUuid($uuid) {
		$this->uuid = $uuid;
	}
	
	public function getUuid() {
		return($this->uuid);
	}
	
	public function setCreationDate($date) {
		$this->creation_date = $date;
	}
	
	public function getCreationDate() {
		return($this->creation_date);
	}
	
	public function setName($str) {
		$this->name = $str;
	}
	
	public function getName() {
		return($this->name);
	}
	
	public function setDescription($str) {
		$this->description = $str;
	}
	public function getDescription() {
		return($this->description);
	}
	
	public function setPrefix($str) {
		$this->prefix = $str;
	}
	
	public function getPrefix() {
		return($this->prefix);
	}
	
	public function setDiscountType($str) {
		$this->discount_type = $str;
	}
	public function getDiscountType() {
		return($this->discount_type);
	}
	
	public function setAmountInCents($nb) {
		$this->amount_in_cents = $nb;
	}
	
	public function getAmountInCents() {
		return($this->amount_in_cents);
	}
		
	public function setCurrency($str) {
		$this->currency = $str;
	}
	
	public function getCurrency() {
		return($this->currency);
	}
	
	public function setPercent($nb) {
		$this->percent = $nb;
	}
	
	public function getPercent() {
		return($this->percent);
	}
	
	public function setDiscountDuration($str) {
		$this->discount_duration = $str;
	}
	
	public function getDiscountDuration() {
		return($this->discount_duration);
	}
	
	public function setDiscountDurationUnit($str) {
		$this->discount_duration_unit = $str;
	}
	
	public function getDiscountDurationUnit() {
		return($this->discount_duration_unit);
	}

	public function setDiscountDurationLength($nb) {
		$this->discount_duration_length = $nb;
	}
	
	public function getDiscountDurationLength() {
		return($this->discount_duration_length);
	}
	
	public function setGeneratedMode($str) {
		$this->generated_mode = $str;
	}
	
	public function getGeneratedMode() {
		return($this->generated_mode);
	}
	
	public function setGeneratedCodeLength($length) {
		$this->generated_code_length = $length;
	}
	
	public function getGeneratedCodeLength() {
		return($this->generated_code_length);
	}
	
	public function setTotalNumber($nb) {
		$this->total_number = $nb;
	}
	
	public function getTotalNumber() {
		return($this->total_number);
	}
	
	public function setCouponType(CouponCampaignType $type) {
		$this->coupon_type = $type;
	}
	
	public function getCouponType() {
		return($this->coupon_type);
	}
	
	public function setEmailsEnabled($bool) {
		$this->emails_enabled = $bool;
	}
	
	public function getEmailsEnabled() {
		return($this->emails_enabled);
	}
	
	public function setExpiresDate($date) {
		$this->expires_date = $date;
	}
	
	public function getExpiresDate() {
		return($this->expires_date);
	}
	
	public function setPartnerId($partnerId) {
		$this->partnerid = $partnerId;
	}
	
	public function getPartnerId() {
		return($this->partnerid);
	}
	
	public function setPlatformId($platformId) {
		$this->platformId = $platformId;
	}
	
	public function getPlatformId() {
		return($this->platformId);
	}
	
	public function setCouponTimeframes(array $couponTimeframes) {
		$this->coupon_timeframes = $couponTimeframes;
	}
	
	public function getCouponTimeframes() {
		return($this->coupon_timeframes);
	}
	
	public function jsonSerialize() {
		$providerCouponsCampaigns = BillingProviderCouponsCampaignDAO::getBillingProviderCouponsCampaignsByInternalCouponsCampaignsId($this->_id);
		$providers = array();
		foreach ($providerCouponsCampaigns as $providerCouponsCampaign) {
			$providers[] = ProviderDAO::getProviderById($providerCouponsCampaign->getProviderId());
		}
		$return = [
				//for backward compatibility - to be removed later -
				'couponsCampaignBillingUuid' => $this->uuid,
				'internalCouponsCampaignBillingUuid' => $this->uuid,
				'creationDate' => dbGlobal::toISODate($this->creation_date),
				'name' => $this->name,
				'description' => $this->description,
				'prefix' => $this->prefix,
				'discountType' => $this->discount_type,
				'amountInCents' => $this->amount_in_cents,
				'currency' => $this->currency,
				'percent' => $this->percent,
				'discountDuration' => $this->discount_duration,
				'discountDurationUnit' => $this->discount_duration_unit,
				'discountDurationLength' => $this->discount_duration_length,
				'generatedMode' => $this->generated_mode,
				'generatedCodeLength' => $this->generated_code_length,
				'totalNumber' => $this->total_number,
				'couponsCampaignType' => $this->coupon_type,
				'emailsEnabled' => $this->emails_enabled,
				'expiresDate' => dbGlobal::toISODate($this->expires_date),
				'couponsCampaignTimeframes' => $this->coupon_timeframes,
				'providerCouponsCampaigns' => $providerCouponsCampaigns
		];
		//provider / providers
		if(count($providers) == 1) {
			//for backward compatibility - to be removed later -
			$return['provider'] = $providers[0];
		}
		//anyway
		$return['providers'] = $providers;
		//internalPlan / internalPlans
		$billingInternalCouponsCampaignInternalPlans = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlansByInternalCouponsCampaignsId($this->_id);
		$internalPlans = array();
		foreach ($billingInternalCouponsCampaignInternalPlans as $billingInternalCouponsCampaignInternalPlan) {
			$internalPlan = InternalPlanDAO::getInternalPlanById($billingInternalCouponsCampaignInternalPlan->getInternalPlanId());
			$internalPlan->setShowProviderPlans(false);
			$internalPlans[] = $internalPlan;
		}
		if(count($internalPlans) == 1) {
			//for backward compatibility - to be removed later -
			$return['internalPlan'] = $internalPlans[0];
		}
		//anyway
		$return['internalPlans'] = $internalPlans;
		return($return);
	}
	
}

class BillingInternalCouponsCampaignDAO {
	
	private static $sfields =<<<EOL
		_id, internal_coupons_campaigns_uuid, creation_date, name, description, prefix,
		discount_type, amount_in_cents, currency, percent, discount_duration,
		discount_duration_unit, discount_duration_length, generated_mode, generated_code_length,
 		total_number, coupon_type, emails_enabled, expires_date, partnerid, platformid, array_to_json(coupon_timeframes) as coupon_timeframes
EOL;
	
	private static function getBillingInternalCouponsCampaignFromRow($row) {
		$out = new BillingInternalCouponsCampaign();
		$out->setId($row["_id"]);
		$out->setUuid($row["internal_coupons_campaigns_uuid"]);
		$out->setCreationDate($row["creation_date"] == NULL ? NULL : new DateTime($row["creation_date"]));
		$out->setName($row["name"]);
		$out->setDescription($row["description"]);
		$out->setPrefix($row["prefix"]);
		$out->setDiscountType($row["discount_type"]);
		$out->setAmountInCents($row["amount_in_cents"]);
		$out->setCurrency($row["currency"]);
		$out->setPercent($row["percent"]);
		$out->setDiscountDuration($row["discount_duration"]);
		$out->setDiscountDurationUnit($row["discount_duration_unit"]);
		$out->setDiscountDurationLength($row["discount_duration_length"]);
		$out->setGeneratedMode($row["generated_mode"]);
		$out->setGeneratedCodeLength($row["generated_code_length"]);
		$out->setTotalNumber($row["total_number"]);
		$out->setCouponType(new CouponCampaignType($row['coupon_type']));
		$out->setEmailsEnabled($row["emails_enabled"] == 't' ? true : false);
		$out->setExpiresDate($row["expires_date"] == NULL ? NULL : new DateTime($row["expires_date"]));
		$out->setPartnerId($row["partnerid"]);
		$out->setPlatformId($row["platformid"]);
		$out->setCouponTimeframes(json_decode($row["coupon_timeframes"]));
		return($out);
	}
	
	public static function getBillingInternalCouponsCampaignById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_coupons_campaigns WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingInternalCouponsCampaignFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getBillingInternalCouponsCampaignByUuid($uuid, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_coupons_campaigns WHERE internal_coupons_campaigns_uuid = $1 AND platformid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($uuid, $platformId));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingInternalCouponsCampaignFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getBillingInternalCouponsCampaigns($couponsCampaignType = NULL, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_coupons_campaigns";
		$params = array();
		
		$out = array();
		
		$where = "";
		//platformId
		$params[] = $platformId;
		if(empty($where)) {
			$where.= " WHERE ";
		} else {
			$where.= " AND ";
		}		
		$where.= "platformid = $".(count($params));
		if(isset($couponsCampaignType)) {
			$params[] = $couponsCampaignType;
			if(empty($where)) {
				$where.= " WHERE ";
			} else {
				$where.= " AND ";
			}
			$where.= "coupon_type = $".(count($params));
		}
		
		$query.= $where;
		
		$result = pg_query_params(config::getDbConn(), $query, $params);
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getBillingInternalCouponsCampaignFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
}

class BillingInternalCouponsCampaignInternalPlan implements JsonSerializable {

	private $_id;
	private $internalCouponsCampaignsId;
	private $internalPlanId;
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getId() {
		return($this->_id);
	}
	
	public function setInternalCouponsCampaignsId($id) {
		$this->internalCouponsCampaignsId = $id;
	}
	
	public function getInternalCouponsCampaignsId() {
		return($this->internalCouponsCampaignsId);
	}
	
	public function setInternalPlanId($id) {
		$this->internalPlanId = $id;
	}
	
	public function getInternalPlanId() {
		return($this->internalPlanId);
	}
	
	public function jsonSerialize() {
		return(InternalPlanDAO::getInternalPlanById($this->internalPlanId));
	}
}

class BillingInternalCouponsCampaignInternalPlansDAO {

	private static $sfields = "_id, internalcouponscampaignsid, internalplanid";

	private static function getBillingInternalCouponsCampaignInternalPlanFromRow($row) {
		$out = new BillingInternalCouponsCampaignInternalPlan();
		$out->setId($row["_id"]);
		$out->setInternalCouponsCampaignsId($row["internalcouponscampaignsid"]);
		$out->setInternalPlanId($row["internalplanid"]);
		return($out);
	}

	public static function getBillingInternalCouponsCampaignInternalPlansByInternalCouponsCampaignsId($id) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_coupons_campaigns_internal_plans WHERE internalcouponscampaignsid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));

		$out = array();

		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getBillingInternalCouponsCampaignInternalPlanFromRow($row);
		}
		// free result
		pg_free_result($result);

		return($out);
	}

}

class BillingInternalCouponsCampaignOpts implements JsonSerializable {

	private $internalCouponsCampaignsId;
	private $opts = array();

	public function __construct(array $opts = null)
	{
		if(!empty($opts)) {
			$this->setOpts($opts);
		}
	}

	public function setInternalCouponsCampaignsId($id) {
		$this->internalCouponsCampaignsId = $id;
	}
	
	public function getInternalCouponsCampaignsId() {
		return($this->internalCouponsCampaignsId);
	}

	public function setOpt($key, $value) {
		$this->opts[$key] = $value;
	}

	public function setOpts($opts) {
		$this->opts = $opts;
	}

	public function getOpts() {
		return($this->opts);
	}

	public function getOpt($key)
	{
		if (array_key_exists($key, $this->opts)) {
			return $this->opts[$key];
		}

		return null;
	}

	public function jsonSerialize() {
		return($this->opts);
	}

}

class BillingInternalCouponsCampaignOptsDAO {

	public static function getBillingInternalCouponsCampaignOptByInternalCouponsCampaignId($internalcouponscampaignsid) {
		$query = "SELECT _id, internalcouponscampaignsid, key, value FROM billing_internal_coupons_campaigns_opts WHERE deleted = false AND internalcouponscampaignsid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($internalcouponscampaignsid));

		$out = new BillingInternalCouponsCampaignOpts();
		$out->setInternalCouponsCampaignsId($internalcouponscampaignsid);
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out->setOpt($row["key"], $row["value"]);
		}
		// free result
		pg_free_result($result);

		return($out);
	}

	public static function addBillingInternalCouponsCampaignOpts(BillingInternalCouponsCampaignOpts $billingInternalCouponsCampaignOpts) {
		foreach ($billingInternalCouponsCampaignOpts->getOpts() as $k => $v) {
			if(isset($v) && is_scalar($v)) {
				$query = "INSERT INTO billing_internal_coupons_campaigns_opts (internalcouponscampaignsid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
						array($billingInternalCouponsCampaignOpts->getInternalCouponsCampaignsId(),
								trim($k),
								trim($v)));
				// free result
				pg_free_result($result);
			}
		}
		return(self::getBillingInternalCouponsCampaignOptByInternalCouponsCampaignId($billingInternalCouponsCampaignOpts->getInternalCouponsCampaignsId()));
	}

	public static function updateBillingInternalCouponsCampaignOptsKey($internalcouponscampaignsid, $key, $value) {
		if(is_scalar($value)) {
			$query = "UPDATE billing_internal_coupons_campaigns_opts SET value = $3 WHERE internalcouponscampaignsid = $1 AND key = $2 AND deleted = false";
			$result = pg_query_params(config::getDbConn(), $query, array($internalcouponscampaignsid, $key, trim($value)));
			// free result
			pg_free_result($result);
		}
	}

	public static function deleteBillingInternalCouponsCampaignOptsKey($internalcouponscampaignsid, $key) {
		$query = "UPDATE billing_internal_coupons_campaigns_opts SET deleted = true WHERE internalcouponscampaignsid = $1 AND key = $2 AND deleted = false";
		$result = pg_query_params(config::getDbConn(), $query, array($internalcouponscampaignsid, $key));
		// free result
		pg_free_result($result);
	}

	public static function addBillingInternalCouponsCampaignOptsKey($internalcouponscampaignsid, $key, $value) {
		if(is_scalar($value)) {
			$query = "INSERT INTO billing_internal_coupons_campaigns_opts (internalcouponscampaignsid, key, value)";
			$query.= " VALUES ($1, $2, $3) RETURNING _id";
			$result = pg_query_params(config::getDbConn(), $query,
					array($internalcouponscampaignsid,
							trim($key),
							trim($value)));
			// free result
			pg_free_result($result);
		}
	}

	public static function deleteBillingInternalCouponsCampaignOptsByInternalCouponsCampaignId($internalcouponscampaignsid) {
		$query = "UPDATE billing_internal_coupons_campaigns_opts SET deleted = true WHERE internalcouponscampaignsid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($internalcouponscampaignsid));
		// free result
		pg_free_result($result);
	}

}

class BillingInternalCoupon implements JsonSerializable {
	
	private $_id;
	private $uuid;
	private $internalCouponsCampaignsId;
	private $code;
	private $status = 'waiting';
	private $creationDate;
	private $updatedDate;
	private $redeemedDate;
	private $expiresDate;
	private $soldStatus;
	private $soldDate;
	private $stockDate;
	//
	private $partnersOrdersInternalCouponsCampaignsLinkId;
	private $platformId;
	private $coupon_timeframe;
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getId() {
		return($this->_id);
	}
	
	public function setUuid($uuid) {
		$this->uuid = $uuid;
	}
	
	public function getUuid() {
		return($this->uuid);
	}
	
	public function setInternalCouponsCampaignsId($id) {
		$this->internalCouponsCampaignsId = $id;
	}
	
	public function getInternalCouponsCampaignsId() {
		return($this->internalCouponsCampaignsId);
	}
	
	public function setCode($str) {
		$this->code = $str;
	}
	
	public function getCode() {
		return($this->code);
	}
	
	public function setStatus($str) {
		$this->status = $str;
	}
	
	public function getStatus() {
		return($this->status);
	}
	
	public function setCreationDate($date) {
		$this->creationDate = $date;
	}
	
	public function getCreationDate() {
		return($this->creationDate);
	}
	
	public function setUpdatedDate($date) {
		$this->updatedDate = $date;
	}
	
	public function getUpdatedDate() {
		return($this->updatedDate);
	}
	
	public function setRedeemedDate($date) {
		$this->redeemedDate = $date;
	}
	
	public function getRedeemedDate() {
		return($this->redeemedDate);
	}
	
	public function setExpiresDate($date) {
		$this->expiresDate = $date;
	}
	
	public function getExpiresDate() {
		return($this->expiresDate);
	}
	
	public function setSoldStatus($soldStatus) {
		$this->soldStatus = $soldStatus;
	}
	
	public function getSoldStatus() {
		return($this->soldStatus);
	}
	
	public function setSoldDate($date) {
		$this->soldDate = $date;
	}
	
	public function getSoldDate() {
		return($this->soldDate);
	}
	
	public function setStockDate($date) {
		$this->stockDate = $date;
	}
	
	public function getStockDate() {
		return($this->stockDate);
	}
	
	public function setPartnersOrdersInternalCouponsCampaignsLinkId($id) {
		$this->partnersOrdersInternalCouponsCampaignsLinkId = $id;
	}
	
	public function getPartnersOrdersInternalCouponsCampaignsLinkId() {
		return($this->partnersOrdersInternalCouponsCampaignsLinkId);
	}
	
	public function setPlatformId($platformId) {
		$this->platformId = $platformId;
	}
	
	public function getPlatformId() {
		return($this->platformId);
	}
	
	public function setCouponTimeframe(CouponTimeframe $couponTimeframe = NULL) {
		$this->coupon_timeframe = $couponTimeframe;
	}
	
	public function getCouponTimeframe() {
		return($this->coupon_timeframe);
	}
	
	public function jsonSerialize() {
		$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($this->internalCouponsCampaignsId);
		$return = [
			//for backward compatibility - to be removed later -
			'couponBillingUuid' => $this->uuid,
			'internalCouponBillingUuid' => $this->uuid,
			'code' => $this->code,
			'status' => $this->status,
			'creationDate' => dbGlobal::toISODate($this->creationDate),
			'updatedDate' => dbGlobal::toISODate($this->updatedDate),
			'redeemedDate' => dbGlobal::toISODate($this->redeemedDate),
			'expiresDate' => dbGlobal::toISODate($this->expiresDate),
			//for backward compatibility - to be removed later -
			'campaign' => $internalCouponsCampaign,
			'internalCouponsCampaign' => $internalCouponsCampaign,
			'couponOpts' => BillingInternalCouponOptsDAO::getBillingInternalCouponOptsByInternalCouponId($this->_id),
			'couponTimeframe' => $this->coupon_timeframe
		];
		//internalPlan / internalPlans
		$billingInternalCouponsCampaignInternalPlans = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlansByInternalCouponsCampaignsId($this->internalCouponsCampaignsId);
		$internalPlans = array();
		foreach ($billingInternalCouponsCampaignInternalPlans as $billingInternalCouponsCampaignInternalPlan) {
			$internalPlan = InternalPlanDAO::getInternalPlanById($billingInternalCouponsCampaignInternalPlan->getInternalPlanId());
			$internalPlan->setShowProviderPlans(false);
			$internalPlans[] = $internalPlan;
		}
		if(count($internalPlans) == 1) {
			//for backward compatibility - to be removed later -
			$return['internalPlan'] = $internalPlans[0];
		}
		//anyway (preparing 'future')
		$return['internalPlans'] = $internalPlans;
		return($return);
	}
	
}

class BillingInternalCouponDAO {
	
	private static $sfields =<<<EOL
		_id, internalcouponscampaignsid, coupon_billing_uuid, code, coupon_status, 
		creation_date, updated_date, redeemed_date, expires_date, sold_status, sold_date, stock_date,  
		partnersordersinternalcouponscampaignslinkid, platformid, coupon_timeframe
EOL;

	private static function getBillingInternalCouponFromRow($row) {
		$out = new BillingInternalCoupon();
		$out->setId($row["_id"]);
		$out->setInternalCouponsCampaignsId($row["internalcouponscampaignsid"]);
		$out->setUuid($row["coupon_billing_uuid"]);
		$out->setCode($row["code"]);
		$out->setStatus($row["coupon_status"]);
		$out->setCreationDate($row["creation_date"] == NULL ? NULL : new DateTime($row["creation_date"]));
		$out->setUpdatedDate($row["updated_date"] == NULL ? NULL : new DateTime($row["updated_date"]));
		$out->setRedeemedDate($row["redeemed_date"] == NULL ? NULL : new DateTime($row["redeemed_date"]));
		$out->setExpiresDate($row["expires_date"] == NULL ? NULL : new DateTime($row["expires_date"]));
		$out->setSoldStatus($row["sold_status"]);
		$out->setSoldDate($row["sold_date"] == NULL ? NULL : new DateTime($row["sold_date"]));
		$out->setStockDate($row["stock_date"] == NULL ? NULL : new DateTime($row["stock_date"]));
		$out->setPartnersOrdersInternalCouponsCampaignsLinkId($row["partnersordersinternalcouponscampaignslinkid"]);
		$out->setPlatformId($row["platformid"]);
		$out->setCouponTimeframe($row["coupon_timeframe"] == NULL ? NULL : new CouponTimeframe($row["coupon_timeframe"]));
		return($out);
	}
	
	public static function getBillingInternalCouponById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_coupons WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingInternalCouponFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
		
	}
	
	public static function getBillingInternalCouponByCode($code, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_coupons WHERE upper(code) = upper($1) AND platformid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($code, $platformId));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingInternalCouponFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function addBillingInternalCoupon(BillingInternalCoupon $billingInternalCoupon) {
		$query = "INSERT INTO billing_internal_coupons (platformid, internalcouponscampaignsid, coupon_billing_uuid, code, expires_date)";
		$query.= " VALUES ($1, $2, $3, upper($4), $5) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingInternalCoupon->getPlatformId(),
						$billingInternalCoupon->getInternalCouponsCampaignsId(),
						$billingInternalCoupon->getUuid(),
						$billingInternalCoupon->getCode(),
						dbGlobal::toISODate($billingInternalCoupon->getExpiresDate())
				));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingInternalCouponById($row[0]));
	}
	
	public static function updateStatus(BillingInternalCoupon $billingInternalCoupon) {
		$query = "UPDATE billing_internal_coupons SET updated_date = CURRENT_TIMESTAMP, coupon_status = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingInternalCoupon->getStatus(),
						$billingInternalCoupon->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingInternalCouponById($billingInternalCoupon->getId()));
	}
	
	public static function updateRedeemedDate(BillingInternalCoupon $billingInternalCoupon) {
		$query = "UPDATE billing_internal_coupons SET updated_date = CURRENT_TIMESTAMP, redeemed_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($billingInternalCoupon->getRedeemedDate()),
						$billingInternalCoupon->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingInternalCouponById($billingInternalCoupon->getId()));
	}
	
	public static function updateExpiresDate(BillingInternalCoupon $billingInternalCoupon) {
		$query = "UPDATE billing_internal_coupons SET updated_date = CURRENT_TIMESTAMP, expires_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($billingInternalCoupon->getExpiresDate()),
						$billingInternalCoupon->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingInternalCouponById($billingInternalCoupon->getId()));
	}
	
	public static function getBillingInternalCouponsTotalNumberByInternalCouponsCampaignsId($internalcouponscampaignsid, $partnersordersinternalcouponscampaignslinkid = NULL) {
		$query = "SELECT count(*) as counter FROM billing_internal_coupons BIC";
		$query.= " WHERE BIC.internalcouponscampaignsid = $1";
		$params = array();
		$params[] = $internalcouponscampaignsid;
		
		if(isset($partnersordersinternalcouponscampaignslinkid)) {
			$params[] = $partnersordersinternalcouponscampaignslinkid;
			$query.= " AND partnersordersinternalcouponscampaignslinkid = $2";
		}
		
		$result = pg_query_params(config::getDbConn(), $query, $params);
	
		$out = 0;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = $row['counter'];
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getBillingInternalCouponsByInternalCouponsCampaignsId($internalcouponscampaignsid, $partnersordersinternalcouponscampaignslinkid = NULL) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_coupons BIC";
		$query.= " WHERE BIC.internalcouponscampaignsid = $1";
		$params = array();
		$params[] = $internalcouponscampaignsid;
	
		if(isset($partnersordersinternalcouponscampaignslinkid)) {
			$params[] = $partnersordersinternalcouponscampaignslinkid;
			$query.= " AND partnersordersinternalcouponscampaignslinkid = $2";
		}
		$query.= " ORDER BY BIC._id ASC";
		$result = pg_query_params(config::getDbConn(), $query, $params);
	
		$out = array();
	
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getBillingInternalCouponFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function bookBillingInternalCoupons(BillingPartnerOrderInternalCouponsCampaignLink $billingPartnerOrderInternalCouponsCampaignLink, $tobookCounter) {
		if($tobookCounter > 0) {
			$query = "UPDATE billing_internal_coupons SET updated_date = CURRENT_TIMESTAMP, partnersordersinternalcouponscampaignslinkid = $1 WHERE";
			$query.= " _id IN (";
			$query.= " SELECT _id FROM billing_internal_coupons WHERE internalcouponscampaignsid = $2 AND partnersordersinternalcouponscampaignslinkid IS NULL AND coupon_status = 'waiting'";
			$query.= " ORDER BY _id ASC LIMIT $3";
			$query.= ")";
			$result = pg_query_params(config::getDbConn(), $query,
					array(	$billingPartnerOrderInternalCouponsCampaignLink->getId(),
							$billingPartnerOrderInternalCouponsCampaignLink->getInternalCouponsCampaignsId(),
							$tobookCounter));
			// free result
			pg_free_result($result);
		}
	}
	
	public static function updateSoldStatus(BillingInternalCoupon $billingInternalCoupon) {
		$query = "UPDATE billing_internal_coupons SET updated_date = CURRENT_TIMESTAMP, sold_status = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingInternalCoupon->getSoldStatus(),
						$billingInternalCoupon->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingInternalCouponById($billingInternalCoupon->getId()));
	}
	
	public static function updateSoldDate(BillingInternalCoupon $billingInternalCoupon) {
		$query = "UPDATE billing_internal_coupons SET updated_date = CURRENT_TIMESTAMP, sold_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($billingInternalCoupon->getSoldDate()),
						$billingInternalCoupon->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingInternalCouponById($billingInternalCoupon->getId()));
	}
	
	public static function updateStockDate(BillingInternalCoupon $billingInternalCoupon) {
		$query = "UPDATE billing_internal_coupons SET updated_date = CURRENT_TIMESTAMP, stock_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($billingInternalCoupon->getStockDate()),
						$billingInternalCoupon->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingInternalCouponById($billingInternalCoupon->getId()));
	}
	
	public static function updateCouponTimeframe(BillingInternalCoupon $billingInternalCoupon) {
		$query = "UPDATE billing_internal_coupons SET updated_date = CURRENT_TIMESTAMP, coupon_timeframe = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingInternalCoupon->getCouponTimeframe(),
						$billingInternalCoupon->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingInternalCouponById($billingInternalCoupon->getId()));
	}
	
}

class BillingInternalCouponOpts implements JsonSerializable {
	
	private $internalcouponid;
	private $opts = array();
	
	public function __construct(array $opts = null)
	{
		if(!empty($opts)) {
			$this->setOpts($opts);
		}
	}
	
	public function setInternalCouponId($id) {
		$this->internalcouponid = $id;
	}
	
	public function getInternalCouponId() {
		return($this->internalcouponid);
	}
	
	public function setOpt($key, $value) {
		$this->opts[$key] = $value;
	}
	
	public function setOpts($opts) {
		$this->opts = $opts;
	}
	
	public function getOpts() {
		return($this->opts);
	}
	
	public function getOpt($key)
	{
		if (array_key_exists($key, $this->opts)) {
			return $this->opts[$key];
		}

		return null;
	}
	
	public function jsonSerialize() {
		return($this->opts);
	}
	
}

class BillingInternalCouponOptsDAO {
	
	public static function getBillingInternalCouponOptsByInternalCouponId($internalcouponsid) {
		$query = "SELECT _id, internalcouponsid, key, value FROM billing_internal_coupons_opts WHERE deleted = false AND internalcouponsid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($internalcouponsid));

		$out = new BillingInternalCouponOpts();
		$out->setInternalCouponId($internalcouponsid);
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out->setOpt($row["key"], $row["value"]);
		}
		// free result
		pg_free_result($result);

		return($out);
	}
	
	public static function addBillingInternalCouponOpts(BillingInternalCouponOpts $billingInternalCouponOpts) {
		foreach ($billingInternalCouponOpts->getOpts() as $k => $v) {
			if(is_null($v) || is_scalar($v)) {
				$query = "INSERT INTO billing_internal_coupons_opts (internalcouponsid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
						array($billingInternalCouponOpts->getInternalCouponId(),
								trim($k),
								($v == NULL) ? NULL : trim($v)));
				// free result
				pg_free_result($result);
			}
		}
		return(self::getBillingInternalCouponOptsByInternalCouponId($billingInternalCouponOpts->getInternalCouponId()));
	}

	public static function updateBillingInternalCouponOptsKey($internalcouponsid, $key, $value) {
		if(is_null($value) || is_scalar($value)) {
			$query = "UPDATE billing_internal_coupons_opts SET value = $3 WHERE internalcouponsid = $1 AND key = $2 AND deleted = false";
			$result = pg_query_params(config::getDbConn(), $query, array($internalcouponsid, $key, ($value == NULL) ? NULL : trim($value)));
			// free result
			pg_free_result($result);
		}
	}

	public static function deleteBillingInternalCouponOptsKey($internalcouponsid, $key) {
		$query = "UPDATE billing_internal_coupons_opts SET deleted = true WHERE internalcouponsid = $1 AND key = $2 AND deleted = false";
		$result = pg_query_params(config::getDbConn(), $query, array($internalcouponsid, $key));
		// free result
		pg_free_result($result);
	}

	public static function addBillingInternalCouponsOptsKey($internalcouponsid, $key, $value) {
		if(is_null($value) || is_scalar($value)) {
			$query = "INSERT INTO billing_internal_coupons_opts (internalcouponsid, key, value)";
			$query.= " VALUES ($1, $2, $3) RETURNING _id";
			$result = pg_query_params(config::getDbConn(), $query,
					array($internalcouponsid,
							trim($key),
							($value == NULL) ? NULL : trim($value)));
			// free result
			pg_free_result($result);
		}
	}

	public static function deleteBillingInternalCouponOptsByInternalCouponId($internalcouponsid) {
		$query = "UPDATE billing_internal_coupons_opts SET deleted = true WHERE internalcouponsid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($internalcouponsid));
		// free result
		pg_free_result($result);
	}

}

class BillingUserInternalCoupon implements JsonSerializable {
	
	private $_id;
	private $uuid;
	private $internalcouponsid;
	private $code;
	private $status = 'waiting';
	private $creationDate;
	private $updatedDate;
	private $redeemedDate;
	private $expiresDate;
	private $userId;
	private $subId;
	private $platformId;
	private $coupon_timeframe;
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getId() {
		return($this->_id);
	}
	
	public function setUuid($uuid) {
		$this->uuid = $uuid;
	}
	
	public function getUuid() {
		return($this->uuid);
	}
	
	public function setInternalCouponsId($id) {
		$this->internalcouponsid = $id;
	}
	
	public function getInternalCouponsId() {
		return($this->internalcouponsid);
	}
	
	public function setCode($str) {
		$this->code = $str;
	}
	
	public function getCode() {
		return($this->code);
	}
	
	public function setStatus($str) {
		$this->status = $str;
	}
	
	public function getStatus() {
		return($this->status);
	}
	
	public function setCreationDate($date) {
		$this->creationDate = $date;
	}
	
	public function getCreationDate() {
		return($this->creationDate);
	}
	
	public function setUpdatedDate($date) {
		$this->updatedDate = $date;
	}
	
	public function getUpdatedDate() {
		return($this->updatedDate);
	}
	
	public function setRedeemedDate($date) {
		$this->redeemedDate = $date;
	}
	
	public function getRedeemedDate() {
		return($this->redeemedDate);
	}
	
	public function setExpiresDate($date) {
		$this->expiresDate = $date;
	}
	
	public function getExpiresDate() {
		return($this->expiresDate);
	}
	
	public function setUserId($id) {
		$this->userid = $id;
	}
	
	public function getUserId() {
		return($this->userid);
	}
	
	public function setSubId($id) {
		$this->subid = $id;
	}
	
	public function getSubId() {
		return($this->subid);
	}
	
	public function setPlatformId($platformId) {
		$this->platformId = $platformId;
	}
	
	public function getPlatformId() {
		return($this->platformId);
	}
	
	public function setCouponTimeframe(CouponTimeframe $couponTimeframe = NULL) {
		$this->coupon_timeframe = $couponTimeframe;
	}
	
	public function getCouponTimeframe() {
		return($this->coupon_timeframe);
	}
	
	/*
	 * Same json as an internalCoupon
	 */
	public function jsonSerialize() {
		$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponById($this->internalcouponsid);
		$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($internalCoupon->getInternalCouponsCampaignsId());
		$return = [
			//for backward compatibility - to be removed later -
			'couponBillingUuid' => $this->uuid,
			'internalUserCouponBillingUuid' => $this->uuid,
			'code' => $this->code,
			'status' => $this->status,
			'creationDate' => dbGlobal::toISODate($this->creationDate),
			'updatedDate' => dbGlobal::toISODate($this->updatedDate),
			'redeemedDate' => dbGlobal::toISODate($this->redeemedDate),
			'expiresDate' => dbGlobal::toISODate($this->expiresDate),
			//for backward compatibility - to be removed later -
			'campaign' => $internalCouponsCampaign,
			'internalCouponsCampaign' => $internalCouponsCampaign,
			'couponOpts' => BillingUserInternalCouponOptsDAO::getBillingUserInternalCouponOptsByUserInternalCouponId($this->_id),
			'couponTimeframe' => $this->coupon_timeframe
		];
		//internalPlan / internalPlans
		$billingInternalCouponsCampaignInternalPlans = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlansByInternalCouponsCampaignsId($internalCouponsCampaign->getId());
		$internalPlans = array();
		foreach ($billingInternalCouponsCampaignInternalPlans as $billingInternalCouponsCampaignInternalPlan) {
			$internalPlan = InternalPlanDAO::getInternalPlanById($billingInternalCouponsCampaignInternalPlan->getInternalPlanId());
			$internalPlan->setShowProviderPlans(false);
			$internalPlans[] = $internalPlan;
		}
		if(count($internalPlans) == 1) {
			//for backward compatibility - to be removed later -
			$return['internalPlan'] = $internalPlans[0];
		}
		//anyway (preparing 'future')
		$return['internalPlans'] = $internalPlans;
		return($return);
	}
	
}

class BillingUserInternalCouponDAO {

	private static $sfields =<<<EOL
		BUIC._id, BUIC.internalcouponsid, BUIC.coupon_billing_uuid, BUIC.code, BUIC.coupon_status, 
			BUIC.creation_date, BUIC.updated_date, BUIC.redeemed_date, BUIC.expires_date, BUIC.userid, BUIC.subid, BUIC.platformid, BUIC.coupon_timeframe
EOL;
	
	private static function getBillingUserInternalCouponFromRow($row) {
		$out = new BillingUserInternalCoupon();
		$out->setId($row["_id"]);
		$out->setInternalCouponsId($row["internalcouponsid"]);
		$out->setUuid($row['coupon_billing_uuid']);
		$out->setCode($row["code"]);
		$out->setStatus($row["coupon_status"]);
		$out->setCreationDate($row["creation_date"] == NULL ? NULL : new DateTime($row["creation_date"]));
		$out->setUpdatedDate($row["updated_date"] == NULL ? NULL : new DateTime($row["updated_date"]));
		$out->setRedeemedDate($row["redeemed_date"] == NULL ? NULL : new DateTime($row["redeemed_date"]));
		$out->setExpiresDate($row["expires_date"] == NULL ? NULL : new DateTime($row["expires_date"]));
		$out->setUserId($row["userid"]);
		$out->setSubId($row["subid"]);
		$out->setPlatformId($row["platformid"]);
		$out->setCouponTimeframe($row["coupon_timeframe"] == NULL ? NULL : new CouponTimeframe($row["coupon_timeframe"]));
		return($out);
	}
	
	public static function getBillingUserInternalCouponById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_users_internal_coupons BUIC WHERE BUIC._id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingUserInternalCouponFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getBillingUserInternalCouponByCouponBillingUuid($coupon_billing_uuid, $platformId) {
		$query = "SELECT ".self::$sfields." FROM billing_users_internal_coupons BUIC WHERE BUIC.coupon_billing_uuid = $1 AND BUIC.platformid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($coupon_billing_uuid, $platformId));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingUserInternalCouponFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getBillingUserInternalCouponBySubId($subId) {
		$query = "SELECT ".self::$sfields." FROM billing_users_internal_coupons BUIC WHERE BUIC.subid = $1";
		$query_params = array($subId);
		$result = pg_query_params(config::getDbConn(), $query, $query_params);
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingUserInternalCouponFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);		
	}
	
	public static function addBillingUserInternalCoupon(BillingUserInternalCoupon $billingUserInternalCoupon) {
		$query = "INSERT INTO billing_users_internal_coupons (platformid, internalcouponsid, coupon_billing_uuid, code, userid, expires_date)";
		$query.= " VALUES ($1, $2, $3, upper($4), $5, $6) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingUserInternalCoupon->getPlatformId(),
						$billingUserInternalCoupon->getInternalCouponsId(),
						$billingUserInternalCoupon->getUuid(),
						$billingUserInternalCoupon->getCode(),
						$billingUserInternalCoupon->getUserId(),
						dbGlobal::toISODate($billingUserInternalCoupon->getExpiresDate())
				));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingUserInternalCouponById($row[0]));
	}
	
	public static function getBillingUserInternalCouponsTotalNumberByInternalCouponsCampaignsId($internalcouponscampaignsid) {
		$query = "SELECT count(*) as counter FROM billing_users_internal_coupons BUIC"; 
		$query.= " INNER JOIN billing_internal_coupons BIC ON (BUIC.internalcouponsid = BIC._id)"; 
		$query.= " WHERE BIC.internalcouponscampaignsid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($internalcouponscampaignsid));
		
		$out = 0;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = $row['counter'];
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function updateStatus(BillingUserInternalCoupon $billingUserInternalCoupon) {
		$query = "UPDATE billing_users_internal_coupons SET updated_date = CURRENT_TIMESTAMP, coupon_status = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingUserInternalCoupon->getStatus(),
						$billingUserInternalCoupon->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingUserInternalCouponById($billingUserInternalCoupon->getId()));
	}
	
	public static function updateRedeemedDate(BillingUserInternalCoupon $billingUserInternalCoupon) {
		$query = "UPDATE billing_users_internal_coupons SET updated_date = CURRENT_TIMESTAMP, redeemed_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($billingUserInternalCoupon->getRedeemedDate()),
						$billingUserInternalCoupon->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingUserInternalCouponById($billingUserInternalCoupon->getId()));
	}
	
	public static function updateExpiresDate(BillingUserInternalCoupon $billingUserInternalCoupon) {
		$query = "UPDATE billing_users_internal_coupons SET updated_date = CURRENT_TIMESTAMP, expires_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($billingUserInternalCoupon->getExpiresDate()),
						$billingUserInternalCoupon->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingUserInternalCouponById($billingUserInternalCoupon->getId()));
	}

	public static function updateSubId(BillingUserInternalCoupon $billingUserInternalCoupon) {
		$query = "UPDATE billing_users_internal_coupons SET updated_date = CURRENT_TIMESTAMP, subid = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingUserInternalCoupon->getSubId(),
						$billingUserInternalCoupon->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingUserInternalCouponById($billingUserInternalCoupon->getId()));		
	}
	
	public static function updateUserId(BillingUserInternalCoupon $billingUserInternalCoupon) {
		$query = "UPDATE billing_users_internal_coupons SET updated_date = CURRENT_TIMESTAMP, userid = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingUserInternalCoupon->getUserId(),
						$billingUserInternalCoupon->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingUserInternalCouponById($billingUserInternalCoupon->getId()));		
	}

	public static function getBillingUserInternalCouponsByUserId($userid,
			$internalcouponsid = NULL,
			$internalCouponCampaignType = NULL, 
			$internalcouponscampaignsid = NULL,
			$recipientIsFilled = NULL) {
		$query = "SELECT ".self::$sfields." FROM billing_users_internal_coupons BUIC";
		$query.= " INNER JOIN billing_internal_coupons BIC ON (BUIC.internalcouponsid = BIC._id)";
		$query.= " INNER JOIN billing_internal_coupons_campaigns BICC ON (BIC.internalcouponscampaignsid = BICC._id)";
		$query.= " LEFT JOIN billing_users_internal_coupons_opts BUICO ON (BUICO.userinternalcouponsid = BUIC._id AND BUICO.key = 'recipientEmail' AND BUICO.deleted = false)";
		$query.= " WHERE BUIC.userid = $1";
		$params = array();
		$params[] = $userid;
		if(isset($internalcouponsid)) {
			$params[] = $internalcouponsid;
			$query.= " AND BIC._id= $".(count($params));
		}
		if(isset($internalCouponCampaignType)) {
			$params[] = $internalCouponCampaignType;
			$query.= " AND BICC.coupon_type= $".(count($params));			
		}
		if(isset($internalcouponscampaignsid)) {
			$params[] = $internalcouponscampaignsid;
			$query.= " AND BICC._id= $".(count($params));
		}
		if(isset($recipientIsFilled) && $recipientIsFilled == true) {
			$query.= " AND length(BUICO.value) > 0";
		}
		$query.= " ORDER BY BUIC._id DESC";
		$result = pg_query_params(config::getDbConn(), $query, $params);
		
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getBillingUserInternalCouponFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getBillingUserInternalCouponsByInternalcouponsid($internalcouponsid) {
		$query = "SELECT ".self::$sfields." FROM billing_users_internal_coupons BUIC";
		$query.= " WHERE BUIC.internalcouponsid = $1";
		$query.= " ORDER BY BUIC._id DESC";
		$params = array();
		$params[] = $internalcouponsid;
		
		$result = pg_query_params(config::getDbConn(), $query, $params);
		
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getBillingUserInternalCouponFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getBillingUserInternalCouponsTotalNumberByRecipientEmails($recipientEmail, CouponCampaignType $internalCouponCampaignType = NULL, $platformId) {
		$query = "SELECT count(*) as counter FROM billing_users_internal_coupons BUIC";
		$query.= " INNER JOIN billing_users_internal_coupons_opts BUICO ON (BUIC._id = BUICO.userinternalcouponsid)";
		$query.= " INNER JOIN billing_internal_coupons BIC ON (BUIC.internalcouponsid = BIC._id)";
		$query.= " INNER JOIN billing_internal_coupons_campaigns BICC ON (BIC.internalcouponscampaignsid = BICC._id)";
		$params = array();
		$where = "";
		//platformId
		$params[] = $platformId;
		if(empty($where)) {
			$where.= " WHERE ";
		} else {
			$where.= " AND ";
		}
		$where = "BUIC.platformid = $".(count($params));
		if(isset($internalCouponCampaignType)) {
			$params[] = $internalCouponCampaignType->getValue();
			if(empty($where)) {
				$where.= " WHERE ";
			} else {
				$where.= " AND ";
			}
			$where = "BICC.coupon_type = $".(count($params));
		}
		$params[] = $recipientEmail;
		if(empty($where)) {
			$where.= " WHERE ";
		} else {
			$where.= " AND ";
		}
		$where.= "BUICO.key = 'recipientEmail'";
		$where.= " AND ";
		$where.= "BUICO.deleted = false";
		$where.= " AND ";
		$where.= "BUICO.value = $".(count($params));
		
		$query.= $where;
		
		$result = pg_query_params(config::getDbConn(), $query, $params);
		
		$out = 0;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = $row['counter'];
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function updateCouponTimeframe(BillingUserInternalCoupon $billingUserInternalCoupon) {
		$query = "UPDATE billing_users_internal_coupons SET updated_date = CURRENT_TIMESTAMP, coupon_timeframe = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingUserInternalCoupon->getCouponTimeframe(),
						$billingUserInternalCoupon->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingUserInternalCouponById($billingUserInternalCoupon->getId()));
	}
	
}

class BillingUserInternalCouponOpts implements JsonSerializable {

	private $userinternalcouponid;
	private $opts = array();

	public function __construct(array $opts = null)
	{
		if(!empty($opts)) {
			$this->setOpts($opts);
		}
	}

	public function setUserInternalCouponId($id) {
		$this->userinternalcouponid = $id;
	}

	public function getUserInternalCouponId() {
		return($this->userinternalcouponid);
	}

	public function setOpt($key, $value) {
		$this->opts[$key] = $value;
	}

	public function setOpts($opts) {
		$this->opts = $opts;
	}

	public function getOpts() {
		return($this->opts);
	}

	public function getOpt($key)
	{
		if (array_key_exists($key, $this->opts)) {
			return $this->opts[$key];
		}

		return null;
	}

	public function jsonSerialize() {
		return($this->opts);
	}

}

class BillingUserInternalCouponOptsDAO {

	public static function getBillingUserInternalCouponOptsByUserInternalCouponId($userinternalcouponsid) {
		$query = "SELECT _id, userinternalcouponsid, key, value FROM billing_users_internal_coupons_opts WHERE deleted = false AND userinternalcouponsid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($userinternalcouponsid));

		$out = new BillingUserInternalCouponOpts();
		$out->setUserInternalCouponId($userinternalcouponsid);
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out->setOpt($row["key"], $row["value"]);
		}
		// free result
		pg_free_result($result);

		return($out);
	}

	public static function addBillingUserInternalCouponOpts(BillingUserInternalCouponOpts $billingUserInternalCouponOpts) {
		foreach ($billingUserInternalCouponOpts->getOpts() as $k => $v) {
			if(isset($v) && is_scalar($v)) {
				$query = "INSERT INTO billing_users_internal_coupons_opts (userinternalcouponsid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
						array($billingUserInternalCouponOpts->getUserInternalCouponId(),
								trim($k),
								trim($v)));
				// free result
				pg_free_result($result);
			}
		}
		return(self::getBillingUserInternalCouponOptsByUserInternalCouponId($billingUserInternalCouponOpts->getUserInternalCouponId()));
	}

	public static function updateBillingUserInternalCouponOptsKey($userinternalcouponsid, $key, $value) {
		if(is_scalar($value)) {
			$query = "UPDATE billing_users_internal_coupons_opts SET value = $3 WHERE userinternalcouponsid = $1 AND key = $2 AND deleted = false";
			$result = pg_query_params(config::getDbConn(), $query, array($userinternalcouponsid, $key, trim($value)));
			// free result
			pg_free_result($result);
		}
	}

	public static function deleteBillingUserInternalCouponOptsKey($userinternalcouponsid, $key) {
		$query = "UPDATE billing_users_internal_coupons_opts SET deleted = true WHERE userinternalcouponsid = $1 AND key = $2 AND deleted = false";
		$result = pg_query_params(config::getDbConn(), $query, array($userinternalcouponsid, $key));
		// free result
		pg_free_result($result);
	}

	public static function addBillingUserInternalCouponsOptsKey($userinternalcouponsid, $key, $value) {
		if(is_scalar($value)) {
			$query = "INSERT INTO billing_users_internal_coupons_opts (userinternalcouponsid, key, value)";
			$query.= " VALUES ($1, $2, $3) RETURNING _id";
			$result = pg_query_params(config::getDbConn(), $query,
					array($userinternalcouponsid,
							trim($key),
							trim($value)));
			// free result
			pg_free_result($result);
		}
	}

	public static function deleteBillingUserInternalCouponOptsByUserInternalCouponId($userinternalcouponsid) {
		$query = "UPDATE billing_users_internal_coupons_opts SET deleted = true WHERE userinternalcouponsid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($userinternalcouponsid));
		// free result
		pg_free_result($result);
	}

}

class BillingProviderCouponsCampaign implements JsonSerializable {

	private $_id;
	private $externalUuid;
	private $internalcouponscampaignsid;
	private $providerid;
	private $creation_date;
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getId() {
		return($this->_id);
	}
	
	public function setExternalUuid($uuid) {
		$this->externalUuid = $uuid;
	}
	
	public function getExternalUuid() {
		return($this->externalUuid);
	}
	
	public function setInternalCouponsCampaignsId($id) {
		$this->internalCouponsCampaignsId = $id;
	}
	
	public function getInternalCouponsCampaignsId() {
		return($this->internalCouponsCampaignsId);
	}
	
	public function getProviderId() {
		return($this->providerid);
	}
	
	public function setProviderId($id) {
		$this->providerid = $id;
	}
	
	public function setCreationDate($date) {
		$this->creation_date = $date;
	}
	
	public function getCreationDate() {
		return($this->creation_date);
	}
	
	public function jsonSerialize() {
		$return = [
				'externalUuid' => $this->externalUuid,
				'creationDate' => dbGlobal::toISODate($this->creation_date),
				'provider' => ProviderDAO::getProviderById($this->providerid)
		];
		return($return);
	}

}

class BillingProviderCouponsCampaignDAO {
	
	private static $sfields = "_id, providerid, internalcouponscampaignsid, external_uuid, creation_date";
	
	public static function addBillingProviderCouponsCampaign(BillingProviderCouponsCampaign $billingProviderCouponsCampaign) {
		$query = "INSERT INTO billing_provider_coupons_campaigns (providerid, internalcouponscampaignsid, external_uuid)";
		$query.= " VALUES ($1, $2, $3) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingProviderCouponsCampaign->getProviderId(),
						$billingProviderCouponsCampaign->getInternalCouponsCampaignsId(),
						$billingProviderCouponsCampaign->getExternalUuid()
		));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingProviderCouponsCampaignById($row[0]));
	}
	
	private static function getBillingProviderCouponsCampaignFromRow($row) {
		$out = new BillingProviderCouponsCampaign();
		$out->setId($row["_id"]);
		$out->setProviderId($row["providerid"]);
		$out->setInternalCouponsCampaignsId($row["internalcouponscampaignsid"]);
		$out->setExternalUuid($row["external_uuid"]);
		$out->setCreationDate($row["creation_date"] == NULL ? NULL : new DateTime($row["creation_date"]));
		return($out);
	}
	
	public static function getBillingProviderCouponsCampaignById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_provider_coupons_campaigns WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingProviderCouponsCampaignFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getBillingProviderCouponsCampaignByExternalUuid($uuid) {
		$query = "SELECT ".self::$sfields." FROM billing_provider_coupons_campaigns WHERE external_uuid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($uuid));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingProviderCouponsCampaignFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getBillingProviderCouponsCampaignsByInternalCouponsCampaignsId($id) {
		$query = "SELECT ".self::$sfields." FROM billing_provider_coupons_campaigns WHERE internalcouponscampaignsid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
	
		$out = array();
	
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getBillingProviderCouponsCampaignFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
}
	
class BillingsTransactionOpts implements JsonSerializable {
	
	private $transactionid;
	private $opts = array();

	public function __construct(array $opts = null)
	{
		if(!empty($opts)) {
			$this->setOpts($opts);
		}
	}
	
	public function setTransactionId($id) {
		$this->transactionid = $id;
	}
	
	public function getTransactionId() {
		return($this->transactionid);
	}
	
	public function setOpt($key, $value) {
		$this->opts[$key] = $value;
	}
	
	public function setOpts($opts) {
		$this->opts = $opts;
	}
	
	public function getOpts() {
		return($this->opts);
	}
	
	public function getOpt($key)
	{
		if (array_key_exists($key, $this->opts)) {
			return $this->opts[$key];
		}
	
		return null;
	}
	
	public function jsonSerialize() {
		return($this->opts);
	}
	
}

class BillingsTransactionOptsDAO {
	
	public static function getBillingsTransactionOptByTransactionId($transactionid) {
		$query = "SELECT _id, transactionid, key, value FROM billing_transactions_opts WHERE deleted = false AND transactionid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($transactionid));
	
		$out = new BillingsTransactionOpts();
		$out->setTransactionId($transactionid);
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out->setOpt($row["key"], $row["value"]);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function addBillingsTransactionOpts(BillingsTransactionOpts $billingsTransactionOpts) {
		foreach ($billingsTransactionOpts->getOpts() as $k => $v) {
			if(isset($v) && is_scalar($v)) {
				$query = "INSERT INTO billing_transactions_opts (transactionid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
						array($billingsTransactionOpts->getTransactionId(),
								trim($k),
								trim($v)));
				// free result
				pg_free_result($result);
			}
		}
		return(self::getBillingsTransactionOptByTransactionId($billingsTransactionOpts->getTransactionId()));
	}
	
	public static function updateBillingsTransactionOptsKey($transactionid, $key, $value) {
		if(is_scalar($value)) {
			$query = "UPDATE billing_transactions_opts SET value = $3 WHERE transactionid = $1 AND key = $2 AND deleted = false";
			$result = pg_query_params(config::getDbConn(), $query, array($transactionid, $key, trim($value)));
			// free result
			pg_free_result($result);
		}
	}
	
	public static function deleteBillingsTransactionOptsKey($transactionid, $key) {
		$query = "UPDATE billing_transactions_opts SET deleted = true WHERE transactionid = $1 AND key = $2 AND deleted = false";
		$result = pg_query_params(config::getDbConn(), $query, array($transactionid, $key));
		// free result
		pg_free_result($result);
	}
	
	public static function addBillingsTransactionOptsKey($transactionid, $key, $value) {
		if(is_scalar($value)) {
			$query = "INSERT INTO billing_transactions_opts (transactionid, key, value)";
			$query.= " VALUES ($1, $2, $3) RETURNING _id";
			$result = pg_query_params(config::getDbConn(), $query,
					array($transactionid,
							trim($key),
							trim($value)));
			// free result
			pg_free_result($result);
		}
	}
	
	public static function deleteBillingsTransactionOptsByTransactionId($transactionid) {
		$query = "UPDATE billing_transactions_opts SET deleted = true WHERE transactionid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($transactionid));
		// free result
		pg_free_result($result);
	}
	
}

class BillingPartner implements JsonSerializable {

	private $_id;
	private $name;
	private $platformId;

	public function getId() {
		return($this->_id);
	}

	public function setId($id) {
		$this->_id = $id;
	}

	public function getName() {
		return($this->name);
	}

	public function setName($name) {
		$this->name = $name;
	}
	
	public function setPlatformId($platformId) {
		$this->platformId = $platformId;
	}
	
	public function getPlatformId() {
		return($this->platformId);
	}
	
	public function jsonSerialize() {
		return[
				'partnerName' => $this->name
		];
	}
	
}

class BillingPartnerDAO {

	private static $sfields = "_id, name, platformid";

	private static function getPartnerFromRow($row) {
		$out = new BillingPartner();
		$out->setId($row["_id"]);
		$out->setName($row["name"]);
		$out->setPlatformId($row["platformid"]);
		return($out);
	}

	public static function getPartnerByName($name, $platformId) {
		$out = NULL;
		$query = "SELECT ".self::$sfields." FROM billing_partners WHERE name = $1 AND platformid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($name, $platformId));
				
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getPartnerFromRow($row);
		}
		// free result
		pg_free_result($result);
		return($out);
	}

	public static function getPartnerById($id) {
		$out = NULL;
		$query = "SELECT ".self::$sfields." FROM billing_partners WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
				
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
				$out = self::getPartnerFromRow($row);
		}
		// free result
		pg_free_result($result);
		return($out);
	}
	
	public static function getPartnersByName($name) {
		$query = "SELECT ".self::$sfields." FROM billing_partners WHERE name = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($name));
			
		$out = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getPartnerFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
}

class BillingPartnerOrder implements JsonSerializable {

	private $_id;
	private $partnerOrderBillingUuid;
	private $partnerId;
	private $type;
	private $name;
	private $processingStatus;
	private $creationDate;
	private $updatedDate;

	public function getId() {
		return($this->_id);
	}

	public function setId($id) {
		$this->_id = $id;
	}

	public function setPartnerOrderBillingUuid($uuid) {
		$this->partnerOrderBillingUuid = $uuid;
	}
	
	public function getPartnerOrderBillingUuid() {
		return($this->partnerOrderBillingUuid);
	}
	
	public function setPartnerId($partnerId) {
		$this->partnerId = $partnerId;
	}
	
	public function getPartnerId() {
		return($this->partnerId);
	}
	
	public function setType($type) {
		$this->type = $type;
	}
	
	public function getType() {
		return($this->type);
	}
	
	public function getName() {
		return($this->name);
	}

	public function setName($name) {
		$this->name = $name;
	}
	
	public function setProcessingStatus($processingStatus) {
		$this->processingStatus = $processingStatus;
	}
	
	public function getProcessingStatus() {
		return($this->processingStatus);
	}
	
	public function setCreationDate(DateTime $date) {
		$this->creationDate = $date;
	}
	
	public function getCreationDate() {
		return($this->creationDate);
	}
	
	public function setUpdatedDate(DateTime $date) {
		$this->updatedDate = $date;
	}
	
	public function getUpdatedDate() {
		return($this->updatedDate);
	}
	
	public function jsonSerialize() {
		return[
				'partnerOrderId' => $this->_id,
				'partnerOrderBillingUuid' => $this->partnerOrderBillingUuid,
				'type' => $this->type,
				'name' => $this->name,
				'processingStatus' => $this->processingStatus,
				'creationDate' => dbGlobal::toISODate($this->creationDate),
				'updatedDate' => dbGlobal::toISODate($this->updatedDate),
				'partner' => BillingPartnerDAO::getPartnerById($this->partnerId),
				'internalCouponsCampaignLink' => BillingPartnerOrderInternalCouponsCampaignLinkDAO::getBillingPartnerOrderInternalCouponsCampaignLinksByPartnerOrderId($this->_id)
		];
	}
	
}

class BillingPartnerOrderDAO {
	
	private static $sfields = "_id, partner_order_uuid, partnerid, type, name, processing_status, creation_date, updated_date";
	
	private static function getBillingPartnerOrderFromRow($row) {
		$out = new BillingPartnerOrder();
		$out->setId($row["_id"]);
		$out->setPartnerOrderBillingUuid($row["partner_order_uuid"]);
		$out->setPartnerId($row["partnerid"]);
		$out->setType("type");
		$out->setName($row["name"]);
		$out->setProcessingStatus($row["processing_status"]);
		$out->setCreationDate($row["creation_date"] == NULL ? NULL : new DateTime($row["creation_date"]));
		$out->setUpdatedDate($row["updated_date"] == NULL ? NULL : new DateTime($row["updated_date"]));
		return($out);
	}
	
	public static function getBillingPartnerOrderById($id) {
		$out = NULL;
		$query = "SELECT ".self::$sfields." FROM billing_partners_orders WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingPartnerOrderFromRow($row);
		}
		// free result
		pg_free_result($result);
		return($out);
	}
	
	public static function getBillingPartnerOrderByPartnerOrderUuid($uuid) {
		$out = NULL;
		$query = "SELECT ".self::$sfields." FROM billing_partners_orders WHERE partner_order_uuid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($uuid));
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingPartnerOrderFromRow($row);
		}
		// free result
		pg_free_result($result);
		return($out);
	}
	
	public static function addBillingPartnerOrder(BillingPartnerOrder $billingPartnerOrder) {
		$query = "INSERT INTO billing_partners_orders (partner_order_uuid, partnerid, type, name)";
		$query.= " VALUES ($1, $2, $3, $4) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($billingPartnerOrder->getPartnerOrderBillingUuid(),
						$billingPartnerOrder->getPartnerId(),
						$billingPartnerOrder->getType(),
						$billingPartnerOrder->getName()));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingPartnerOrderById($row[0]));
	}
	
	public static function updateProcessingStatus(BillingPartnerOrder $billingPartnerOrder) {
		$query = "UPDATE billing_partners_orders SET updated_date = CURRENT_TIMESTAMP, processing_status = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingPartnerOrder->getProcessingStatus(),
						$billingPartnerOrder->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingPartnerOrderById($billingPartnerOrder->getId()));
	}
	
}

class BillingPartnerOrderProcessingLog {

	private $_id;
	private $partnerOrderId;
	private $processingStatus;
	private $startedDate;
	private $endedDate;
	private $message;

	public function getId() {
		return($this->_id);
	}

	public function setId($id) {
		$this->_id = $id;
	}

	public function setPartnerOrderId($id) {
		$this->partnerOrderId = $id;
	}

	public function getPartnerOrderId() {
		return($this->partnerOrderId);
	}

	public function getProcessingStatus() {
		return($this->processingStatus);
	}

	public function setProcessingStatus($status) {
		$this->processingStatus = $status;
	}

	public function getStartedDate() {
		return($this->startedDate);
	}

	public function setStartedDate(DateTime $date) {
		$this->startedDate = $date;
	}

	public function getEndedDate() {
		return($this->endedDate);
	}

	public function setEndedDate(DateTime $date = NULL) {
		$this->endedDate = $date;
	}

	public function getMessage() {
		return($this->message);
	}

	public function setMessage($msg) {
		$this->message = $msg;
	}

}

class BillingPartnerOrderProcessingLogDAO {

	private static $sfields = "_id, partnerorderid, processing_status, started_date, ended_date, message";

	private static function getBillingPartnerOrderProcessingLogFromRow($row) {
		$out = new BillingPartnerOrderProcessingLog();
		$out->setId($row["_id"]);
		$out->setPartnerOrderId($row["partnerorderid"]);
		$out->setProcessingStatus($row["processing_status"]);
		$out->setStartedDate($row["started_date"] == NULL ? NULL : new DateTime($row["started_date"]));
		$out->setEndedDate($row["ended_date"] == NULL ? NULL : new DateTime($row["ended_date"]));
		$out->setMessage($row["message"]);
		return($out);
	}

	public static function addBillingPartnerOrderProcessingLog($partnerorderid) {
		$query = "INSERT INTO billing_partners_orders_processing_logs (partnerorderid) VALUES ($1) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, array($partnerorderid));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingPartnerOrderProcessingLogById($row[0]));
	}

	public static function updateBillingPartnerOrderProcessingLogProcessingStatus(BillingPartnerOrderProcessingLog $billingPartnerOrderProcessingLog) {
		$query = "UPDATE billing_partners_orders_processing_logs SET processing_status = $1, ended_date = CURRENT_TIMESTAMP, message = $2 WHERE _id = $3";
		$result = pg_query_params(config::getDbConn(), $query,
				array($billingPartnerOrderProcessingLog->getProcessingStatus(),
						$billingPartnerOrderProcessingLog->getMessage(),
						$billingPartnerOrderProcessingLog->getId()));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingPartnerOrderProcessingLogById($billingPartnerOrderProcessingLog->getId()));
	}

	public static function getBillingPartnerOrderProcessingLogById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_partners_orders_processing_logs WHERE _id = $1";

		$result = pg_query_params(config::getDbConn(), $query, array($id));

		$out = null;

		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingPartnerOrderProcessingLogFromRow($row);
		}
		// free result
		pg_free_result($result);

		return($out);
	}
	
}

class BillingPartnerOrderInternalCouponsCampaignLink implements JsonSerializable {
	
	private $_id;
	private $partnerOrderId;
	private $internalCouponsCampaignsId;
	private $wishedCounter;
	private $bookedCounter;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function setPartnerOrderId($id) {
		$this->partnerOrderId = $id;
	}
	
	public function getPartnerOrderId() {
		return($this->partnerOrderId);
	}
	
	public function setInternalCouponsCampaignsId($id) {
		$this->internalCouponsCampaignsId = $id;
	}
	
	public function getInternalCouponsCampaignsId() {
		return($this->internalCouponsCampaignsId);
	}
	
	public function setWishedCounter($counter) {
		$this->wishedCounter = $counter;
	}
	
	public function getWishedCounter() {
		return($this->wishedCounter);
	}
	
	public function setBookedCounter($counter) {
		$this->bookedCounter = $counter;
	}
	
	public function getBookedCounter() {
		return($this->bookedCounter);
	}
	
	public function jsonSerialize() {
		return[
				'internalCouponsCampaign' => BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($this->internalCouponsCampaignsId),
				'whishedCounter' => $this->wishedCounter,
				'bookedCounter' => $this->bookedCounter,
				'internalCoupons' => BillingInternalCouponDAO::getBillingInternalCouponsByInternalCouponsCampaignsId($this->internalCouponsCampaignsId, $this->_id)
		];
	}
	
	
}

class BillingPartnerOrderInternalCouponsCampaignLinkDAO {
	
	private static $sfields = "_id, partnerorderid, internalcouponscampaignsid, wished_counter, booked_counter";
	
	private static function getBillingPartnerOrderInternalCouponsCampaignLinkFromRow($row) {
		$out = new BillingPartnerOrderInternalCouponsCampaignLink();
		$out->setId($row["_id"]);
		$out->setPartnerOrderId($row["partnerorderid"]);
		$out->setInternalCouponsCampaignsId($row["internalcouponscampaignsid"]);
		$out->setWishedCounter($row["wished_counter"]);
		$out->setBookedCounter($row["booked_counter"]);
		return($out);
	}
	
	public static function getBillingPartnerOrderInternalCouponsCampaignLinkById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_partners_orders_internal_coupons_campaigns_links WHERE _id = $1";
	
		$result = pg_query_params(config::getDbConn(), $query, array($id));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingPartnerOrderInternalCouponsCampaignLinkFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getBillingPartnerOrderInternalCouponsCampaignLinksByPartnerOrderId($partnerorderid) {
		$query = "SELECT ".self::$sfields." FROM billing_partners_orders_internal_coupons_campaigns_links WHERE partnerorderid = $1";
	
		$result = pg_query_params(config::getDbConn(), $query, array($partnerorderid));
	
		$out = array();
	
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getBillingPartnerOrderInternalCouponsCampaignLinkFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function addBillingPartnerOrderInternalCouponsCampaignLink(BillingPartnerOrderInternalCouponsCampaignLink $billingPartnerOrderInternalCouponsCampaignLink) {
		$query = "INSERT INTO billing_partners_orders_internal_coupons_campaigns_links (partnerorderid, internalcouponscampaignsid, wished_counter)";
		$query.= " VALUES ($1, $2, $3) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($billingPartnerOrderInternalCouponsCampaignLink->getPartnerOrderId(),
						$billingPartnerOrderInternalCouponsCampaignLink->getInternalCouponsCampaignsId(),
						$billingPartnerOrderInternalCouponsCampaignLink->getWishedCounter()));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingPartnerOrderInternalCouponsCampaignLinkById($row[0]));
	}
	
	public static function updateBookedCounter(BillingPartnerOrderInternalCouponsCampaignLink $billingPartnerOrderInternalCouponsCampaignLink) {
		$query = "UPDATE billing_partners_orders_internal_coupons_campaigns_links SET booked_counter = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$billingPartnerOrderInternalCouponsCampaignLink->getBookedCounter(),
						$billingPartnerOrderInternalCouponsCampaignLink->getId()));
		// free result
		pg_free_result($result);
		return(self::getBillingPartnerOrderInternalCouponsCampaignLinkById($billingPartnerOrderInternalCouponsCampaignLink->getId()));
	}
	
}

class BillingInternalCouponActionLog {

	private $_id;
	private $internalcouponid;
	private $processing_status;
	private $action_type;
	private $started_date;
	private $ended_date;
	private $message;
	private $processing_status_code = 0;//DEFAULT

	public function getId() {
		return($this->_id);
	}

	public function setId($id) {
		$this->_id = $id;
	}

	public function setInternalCouponId($id) {
		$this->internalcouponid = $id;
	}

	public function getInternalCouponId() {
		return($this->internalcouponid);
	}

	public function getProcessingStatus() {
		return($this->processing_status);
	}

	public function setProcessingStatus($status) {
		$this->processing_status = $status;
	}

	public function getActionType() {
		return($this->action_type);
	}

	public function setActionType($action_type) {
		$this->action_type = $action_type;
	}

	public function getStartedDate() {
		return($this->started_date);
	}

	public function setStartedDate(DateTime $date) {
		$this->started_date = $date;
	}

	public function getEndedDate() {
		return($this->ended_date);
	}

	public function setEndedDate(DateTime $date = NULL) {
		$this->ended_date = $date;
	}

	public function getMessage() {
		return($this->message);
	}

	public function setMessage($msg) {
		$this->message = $msg;
	}

	public function getProcessingStatusCode() {
		return($this->processing_status_code);
	}

	public function setProcessingStatusCode($status_code) {
		$this->processing_status_code = $status_code;
	}

}

class BillingInternalCouponActionLogDAO {

	private static $sfields = "_id, internalcouponsid, processing_status, action_type, started_date, ended_date, message, processing_status_code";

	private static function getBillingInternalCouponActionLogFromRow($row) {
		$out = new BillingInternalCouponActionLog();
		$out->setId($row["_id"]);
		$out->setInternalCouponId($row["internalcouponsid"]);
		$out->setProcessingStatus($row["processing_status"]);
		$out->setProcessingStatusCode($row["processing_status_code"]);
		$out->setActionType($row["action_type"]);
		$out->setStartedDate($row["started_date"] == NULL ? NULL : new DateTime($row["started_date"]));
		$out->setEndedDate($row["ended_date"] == NULL ? NULL : new DateTime($row["ended_date"]));
		$out->setMessage($row["message"]);
		return($out);
	}

	public static function addBillingInternalCouponActionLog($internalcouponsid, $action_type) {
		$query = "INSERT INTO billing_internal_coupons_action_logs (internalcouponsid, action_type, processing_status) VALUES ($1, $2, $3) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, array($internalcouponsid, $action_type, "running"));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingInternalCouponActionLogById($row[0]));
	}

	public static function updateBillingInternalCouponActionLogProcessingStatus(BillingInternalCouponActionLog $billingInternalCouponActionLog) {
		$query = "UPDATE billing_internal_coupons_action_logs SET processing_status = $1, ended_date = CURRENT_TIMESTAMP, message = $2, processing_status_code = $3 WHERE _id = $4";
		$result = pg_query_params(config::getDbConn(), $query,
				array($billingInternalCouponActionLog->getProcessingStatus(),
						$billingInternalCouponActionLog->getMessage(),
						$billingInternalCouponActionLog->getProcessingStatusCode(),
						$billingInternalCouponActionLog->getId()));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingInternalCouponActionLogById($billingInternalCouponActionLog->getId()));
	}

	public static function getBillingInternalCouponActionLogById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_coupons_action_logs WHERE _id = $1";

		$result = pg_query_params(config::getDbConn(), $query, array($id));

		$out = null;

		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingInternalCouponActionLogFromRow($row);
		}
		// free result
		pg_free_result($result);

		return($out);
	}
	
}

class BillingMailTemplate {
	
	private $_id;
	private $templateBillingUuid;
	private $templatePartnerUuid;
	private $templateName;
	private $creationDate;
	private $updatedDate;
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getId() {
		return($this->_id);
	}
	
	public function setTemplateBillingUuid($uuid) {
		$this->templateBillingUuid = $uuid;
	}
	
	public function getTemplateBillingUuid() {
		return($this->templateBillingUuid);
	}
	
	public function setTemplatePartnerUuid($uuid) {
		$this->templatePartnerUuid = $uuid;
	}
	
	public function getTemplatePartnerUuid() {
		return($this->templatePartnerUuid);
	}
	
	public function setTemplateName($str) {
		$this->templateName = $str;
	}
	
	public function getTemplateName() {
		return($this->templateName);
	}
	
	public function setCreationDate(DateTime $date) {
		$this->creationDate = $date;
	}
	
	public function getCreationDate() {
		return($this->creationDate);
	}
	
	public function setUpdatedDate(DateTime $date) {
		$this->updatedDate = $date;
	}
	
	public function getUpdatedDate() {
		return($this->updatedDate);
	}
	
}

class BillingMailTemplateDAO {
	
	private static $sfields = "_id, template_billing_uuid, template_partner_uuid, template_name, creation_date, updated_date";
	
	private static function getBillingMailTemplateFromRow($row) {
		$out = new BillingMailTemplate();
		$out->setId($row["_id"]);
		$out->setTemplateBillingUuid($row["template_billing_uuid"]);
		$out->setTemplatePartnerUuid($row["template_partner_uuid"]);
		$out->setTemplateName($row["template_name"]);
		$out->setCreationDate($row["creation_date"] == NULL ? NULL : new DateTime($row["creation_date"]));
		$out->setUpdatedDate($row["updated_date"] == NULL ? NULL : new DateTime($row["updated_date"]));
		return($out);
	}
	
	public static function getBillingMailTemplateById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_mail_templates WHERE _id = $1";

		$result = pg_query_params(config::getDbConn(), $query, array($id));

		$out = null;

		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingMailTemplateFromRow($row);
		}
		// free result
		pg_free_result($result);

		return($out);
	}

	public static function getBillingMailTemplateByTemplatePartnerUuid($templatePartnerUuid) {
		$query = "SELECT ".self::$sfields." FROM billing_mail_templates WHERE template_partner_uuid = $1";
	
		$result = pg_query_params(config::getDbConn(), $query, array($templatePartnerUuid));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingMailTemplateFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getBillingMailTemplateByTemplateName($templateName) {
		$query = "SELECT ".self::$sfields." FROM billing_mail_templates WHERE template_name = $1";

		$result = pg_query_params(config::getDbConn(), $query, array($templateName));

		$out = null;

		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingMailTemplateFromRow($row);
		}
		// free result
		pg_free_result($result);

		return($out);
	}
	
	public static function addBillingMailTemplate(BillingMailTemplate $billingMailTemplate) {
		$query = "INSERT INTO billing_mail_templates (template_billing_uuid, template_partner_uuid, template_name)";
		$query.= " VALUES ($1, $2, $3) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($billingMailTemplate->getTemplateBillingUuid(),
						$billingMailTemplate->getTemplatePartnerUuid(),
						$billingMailTemplate->getTemplateName()));
		$row = pg_fetch_row($result);
		// free result
		pg_free_result($result);
		return(self::getBillingMailTemplateById($row[0]));
	}
	
}

class BillingPlatform {
	
	private $_id;
	private $uuid;
	private $name;
	private $referenceUuid;
	private $creationDate;
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getId() {
		return($this->_id);
	}
	
	public function setUuid($uuid) {
		$this->uuid = $uuid;
	}
	
	public function getUuid() {
		return($this->uuid);
	}
	
	public function setName($str) {
		$this->name = $str;
	}
	
	public function getName() {
		return($this->name);
	}
	
	public function setReferenceUuid($referenceUuid) {
		$this->referenceUuid = $referenceUuid;
	}
	
	public function getReferenceUuid() {
		return($this->referenceUuid);
	}
	
	public function setCreationDate(DateTime $date) {
		$this->creationDate = $date;
	}
	
	public function getCreationDate() {
		return($this->creationDate);
	}
	
}

class BillingPlatformDAO {
	
	private static $sfields = "_id, platform_billing_uuid, platform_reference_uuid, name, creation_date";
	
	private static function getBillingPlatformFromRow($row) {
		$out = new BillingPlatform();
		$out->setId($row["_id"]);
		$out->setUuid($row["platform_billing_uuid"]);
		$out->setReferenceUuid($row["platform_reference_uuid"]);
		$out->setName($row["name"]);
		$out->setCreationDate($row["creation_date"] == NULL ? NULL : new DateTime($row["creation_date"]));
		return($out);
	}
	
	public static function getPlatformById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_platforms WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingPlatformFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getPlatforms() {
		$query = "SELECT ".self::$sfields." FROM billing_platforms";
		$result = pg_query(config::getDbConn(), $query);
	
		$out = array();
	
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[] = self::getBillingPlatformFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
}