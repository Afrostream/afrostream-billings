<?php

require_once __DIR__ . '/../../config/config.php';

use MyCLabs\Enum\Enum;

class dbGlobal {
	
	public static function toISODate(DateTime $str = NULL)
	{
		if($str == NULL) {
			return(NULL);
		}
		return($str->format(DateTime::ISO8601));
	}
	
}

class UserDAO {
	
	private static $sfields = "_id, creation_date, providerid, user_billing_uuid, user_reference_uuid, user_provider_uuid, deleted";
	
	private static function getUserFromRow($row) {
		$out = new User();
		$out->setId($row["_id"]);
		$out->setUserBillingUuid($row["user_billing_uuid"]);
		$out->setCreationDate($row["creation_date"]);
		$out->setProviderId($row["providerid"]);
		$out->setUserReferenceUuid($row["user_reference_uuid"]);
		$out->setUserProviderUuid($row["user_provider_uuid"]);
		$out->setDeleted($row["deleted"]);
		return($out);
	}
	
	public static function addUser(User $user) {
		$query = "INSERT INTO billing_users (providerid, user_billing_uuid, user_reference_uuid, user_provider_uuid)";
		$query.= " VALUES ($1, $2, $3, $4) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, 
				array($user->getProviderId(),
					$user->getUserBillingUuid(),
					$user->getUserReferenceUuid(),
					$user->getUserProviderUuid()));
		$row = pg_fetch_row($result);
		return(self::getUserById($row[0]));
	}
	
	public static function getUserById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_users WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getUserFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getUsersByUserReferenceUuid($user_reference_uuid, $providerid = NULL) {
		$query = "SELECT ".self::$sfields." FROM billing_users WHERE deleted = false AND user_reference_uuid = $1";
		if(isset($providerid)) {
			$query.= " AND providerid = $2";
		}
		$query_params = array($user_reference_uuid);
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
		$query = "SELECT ".self::$sfields." FROM billing_users WHERE deleted = false AND providerid = $1 AND user_provider_uuid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($providerid, $user_provider_uuid));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getUserFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getUserByUserBillingUuid($user_billing_uuid) {
		$query = "SELECT ".self::$sfields." FROM billing_users WHERE deleted = false AND user_billing_uuid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($user_billing_uuid));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getUserFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getUsers($id = NULL, $limit = 0, $offset = 0) {
		$query = "SELECT ".self::$sfields." FROM billing_users WHERE deleted = false";
		if(isset($id)) { $query.= " AND _id <= ".$id; }
		$query.= " ORDER BY _id DESC";//LAST USERS FIRST
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
	
}

class User implements JsonSerializable {
	
	private $_id;
	private $user_billing_uuid;
	private $creation_date;
	private $providerid;
	private $user_reference_uuid;
	private $user_provider_uuid;
	private $deleted;

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
			if(isset($v)) {
				$query = "INSERT INTO billing_users_opts (userid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
						array($user_opts->getUserId(),
								$k,
								$v));
			}
		}
		return(self::getUserOptsByUserId($user_opts->getUserId()));
	}
	
	public static function updateUserOptsKey($userid, $key, $value) {
		$query = "UPDATE billing_users_opts SET value = $3 WHERE userid = $1 AND key = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array($userid, $key, $value));
		return($result);
	}
	
	public static function deleteUserOptsKey($userid, $key) {
		$query = "UPDATE billing_users_opts SET deleted = true WHERE userid = $1 AND key = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array($userid, $key));
		return($result);
	}
	
	public static function addUserOptsKey($userid, $key, $value) {
		$query = "INSERT INTO billing_users_opts (userid, key, value)";
		$query.= " VALUES ($1, $2, $3) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($userid,
						$key,
						$value));
		return($result);
	}
	
	public static function deleteUserOptsByUserId($userid) {
		$query = "UPDATE billing_users_opts SET deleted = true WHERE userid = $1";
		$result = pg_query_params(config::getDbConn(), $query,
				array($userid));
		return($result);
	}
}

class InternalPlanDAO {
	
	private static $sfields = "BIP._id, BIP.internal_plan_uuid, BIP.name, BIP.description, BIP.amount_in_cents, BIP.currency, BIP.cycle, BIP.period_unit, BIP.period_length";
	
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
	
	public static function getInternalPlanByUuid($internal_plan_uuid) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans BIP WHERE BIP.internal_plan_uuid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($internal_plan_uuid));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getInternalPlanFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getInternalPlanByName($name) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans BIP WHERE BIP.name = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($name));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getInternalPlanFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getInternalPlans($providerId = NULL) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans BIP";
		$params = array();
		
		$out = array();
		
		if(isset($providerId)) {
			$query.= " INNER JOIN billing_internal_plans_links BIPL ON (BIP._id = BIPL.internal_plan_id)";
			$query.= " INNER JOIN billing_plans BP ON (BIPL.provider_plan_id = BP._id)";
			$query.= " WHERE BP.providerid = $1";
			$params[] = $providerId;
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
		$query = "INSERT INTO billing_internal_plans (internal_plan_uuid, name, description, amount_in_cents, currency, cycle, period_unit, period_length)";
		$query.= " VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, 
				array($internalPlan->getInternalPlanUuid(),
					$internalPlan->getName(),
					$internalPlan->getDescription(),
					$internalPlan->getAmountInCents(),
					$internalPlan->getCurrency(),
					$internalPlan->getCycle(),
					$internalPlan->getPeriodUnit(),
					$internalPlan->getPeriodLength()
				));
		$row = pg_fetch_row($result);
		return(self::getInternalPlanById($row[0]));
	}
	
}

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
		$this->currency = $currency;
	}
	
	public function getCurrency() {
		return($this->currency);
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
		return(intval($this->amount_in_cents * (100 - $this->vatRate) / 100));
	}
	
	public function jsonSerialize() {
		$return =
			[
				'internalPlanUuid' => $this->internal_plan_uuid,
				'name' => $this->name,
				'description' => $this->description,
				'amountInCents' => $this->amount_in_cents,
				'amountInCentsExclTax' => (string) $this->getAmountInCentsExclTax(),
				'vatRate' => (string) $this->vatRate,
				'currency' => $this->currency,
				'cycle' => $this->cycle,
				'periodUnit' => $this->periodUnit,
				'periodLength' => $this->periodLength,
				'internalPlanOpts' => (InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($this->_id)->jsonSerialize())
		];
		if($this->showProviderPlans) {
			$return['providerPlans'] = PlanDAO::getPlansFromList(InternalPlanLinksDAO::getProviderPlanIdsFromInternalPlanId($this->_id));
		}
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
			if(isset($v)) {
				$query = "INSERT INTO billing_internal_plans_opts (internalplanid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
						array($internalplan_opts->getInternalPlanId(),
								$k,
								$v));
			}
		}
		return(self::getInternalPlanOptsByInternalPlanId($internalplan_opts->getInternalPlanId()));
	}
	
	public static function updateInternalPlanOptsKey($internalplanid, $key, $value) {
		$query = "UPDATE billing_internal_plans_opts SET value = $3 WHERE internalplanid = $1 AND key = $2";
		$result = pg_query_params(config::getDbConn(), $query,
			array($internalplanid, $key, $value));
		return($result);
	}
	
	public static function deleteInternalPlanOptsKey($internalplanid, $key) {
		$query = "UPDATE billing_internal_plans_opts SET deleted = true WHERE internalplanid = $1 AND key = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array($internalplanid, $key));
		return($result);
	}
	
	public static function addInternalPlanOptsKey($internalplanid, $key, $value) {
		$query = "INSERT INTO billing_internal_plans_opts (internalplanid, key, value)";
		$query.= " VALUES ($1, $2, $3) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($internalplanid,
						$key,
						$value));
		return($result);
	}
	
}

class InternalPlanLinksDAO {
	
	public static function getProviderPlanIdFromInternalPlanId($internalplanid, $providerid) {
		$query = "SELECT BP._id as billing_plan_id FROM billing_plans BP INNER JOIN billing_internal_plans_links BIPL ON (BP._id = BIPL.provider_plan_id)";
		$query.= "WHERE BIPL.internal_plan_id = $1 AND BP.providerid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($internalplanid, $providerid));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = $row["billing_plan_id"];
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getInternalPlanIdFromProviderPlanId($providerplanid) {
		$query = "SELECT internal_plan_id as billing_internal_plan_id FROM billing_internal_plans_links BIPL WHERE BIPL.provider_plan_id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($providerplanid));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = $row["billing_internal_plan_id"];
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function addProviderPlanIdToInternalPlanId($internalplanid, $providerplanid) {
		$query = "INSERT INTO billing_internal_plans_links (internal_plan_id, provider_plan_id)";
		$query.= " VALUES ($1, $2) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($internalplanid,
					$providerplanid));
		$row = pg_fetch_row($result);
		return($row[0]);
	}
	
	public static function getProviderPlanIdsFromInternalPlanId($internalplanid) {
		$query = "SELECT BP._id as billing_plan_id FROM billing_plans BP INNER JOIN billing_internal_plans_links BIPL ON (BP._id = BIPL.provider_plan_id)";
		$query.= " WHERE BIPL.internal_plan_id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($internalplanid));
		
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, $row["billing_plan_id"]);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

class PlanDAO {
	
	private static $sfields = "BP._id, BP.providerid, BP.plan_uuid, BP.name, BP.description";
	
	private static function getPlanFromRow($row) {
		$out = new Plan();
		$out->setId($row["_id"]);
		$out->setProviderId($row["providerid"]);
		$out->setPlanUid($row["plan_uuid"]);
		$out->setName($row["name"]);
		$out->setDescription($row["description"]);
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
	
	public static function getPlansFromList(array $list_of_billing_plan_ids) {
		if(count($list_of_billing_plan_ids) == 0) return(array());
		$query = "SELECT P.name as provider_name, ".self::$sfields." FROM billing_plans BP";
		$query.= " INNER JOIN billing_providers P ON (BP.providerid = P._id)";
		$params = array();
	
		$out = array();
	
		$firstLoop = true;
	
		$i = 1;
		foreach ($list_of_billing_plan_ids as $billing_plan_id) {
			if($firstLoop == true) {
				$firstLoop = false;
				$query.= " WHERE BP._id in ($".$i;
			} else {
				$query.= ", $".$i;
			}
			$params[] = $billing_plan_id;
			//done
			$i++;
		}
		$query.= ")";
	
		$result = pg_query_params(config::getDbConn(), $query, $params);
	
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out[$row['provider_name']] = self::getPlanFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function addPlan(Plan $plan) {
		$query = "INSERT INTO billing_plans (providerid, plan_uuid, name, description)";
		$query.= " VALUES ($1, $2, $3, $4) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($plan->getProviderId(),
					$plan->getPlanUuid(),
					$plan->getName(),
					$plan->getDescription()));
		$row = pg_fetch_row($result);
		return(self::getPlanById($row[0]));
	}
	
}

class Plan implements JsonSerializable {
	
	private $_id;
	private $plan_uuid;
	private $name;
	private $description;
	private $providerid;
	
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
	
	public function jsonSerialize() {
		return[
				'providerPlanUuid' => $this->plan_uuid,
				'name' => $this->name,
				'description' => $this->description,
				'provider' => ProviderDAO::getProviderById($this->providerid)->jsonSerialize()
		];
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
	
	private static $sfields = "_id, name";
	
	private static function getProviderFromRow($row) {
		$out = new Provider();
		$out->setId($row["_id"]);
		$out->setName($row["name"]);
		return($out);
	}
	
	public static function getProviderByName($name) {
		$query = "SELECT ".self::$sfields." FROM billing_providers WHERE name = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($name));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getProviderFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getProviderById($providerid) {
		$query = "SELECT ".self::$sfields." FROM billing_providers WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($providerid));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getProviderFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
}

class Provider implements JsonSerializable {
	
	private $_id;
	private $name;
	
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
	
	public function jsonSerialize() {
		return[
			'providerName' => $this->name	
		];
	}
}

class BillingsSubscriptionDAO {
	
	private static $sfields = NULL;

	public static function init() {
		BillingsSubscriptionDAO::$sfields = "BS._id, BS.subscription_billing_uuid, BS.providerid, BS.userid, BS.planid, BS.creation_date, BS.updated_date, BS.sub_uuid, BS.sub_status,".
			" BS.sub_activated_date, BS.sub_canceled_date, BS.sub_expires_date, BS.sub_period_started_date, BS.sub_period_ends_date,".
			" BS.sub_collection_mode, BS.update_type, BS.updateid, BS.deleted";		
	}
	
	private static function getBillingsSubscriptionFromRow($row) {
		$out = new BillingsSubscription();
		$out->setId($row["_id"]);
		$out->setSubscriptionBillingUuid($row["subscription_billing_uuid"]);
		$out->setProviderId($row["providerid"]);
		$out->setUserId($row["userid"]);
		$out->setPlanId($row["planid"]);
		$out->setCreationDate($row["creation_date"]);
		$out->setUpdatedDate($row["updated_date"]);
		$out->setSubUid($row["sub_uuid"]);
		$out->setSubStatus($row["sub_status"]);
		$out->setSubActivatedDate($row["sub_activated_date"]);
		$out->setSubCanceledDate($row["sub_canceled_date"]);
		$out->setSubExpiresDate($row["sub_expires_date"]);
		$out->setSubCollectionMode($row["sub_collection_mode"]);
		$out->setSubPeriodStartedDate($row["sub_period_started_date"]);
		$out->setSubPeriodEndsDate($row["sub_period_ends_date"]);
		$out->setUpdateType($row["update_type"]);
		$out->setUpdateId($row["updateid"]);
		$out->setDeleted($row["deleted"]);
		$out->setBillingsSubscriptionOpts(BillingsSubscriptionOptsDAO::getBillingsSubscriptionOptsBySubId($row["_id"]));
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
	
	public static function getBillingsSubscriptionBySubscriptionBillingUuid($subscription_billing_uuid) {
		$query = "SELECT ".self::$sfields." FROM billing_subscriptions BS WHERE BS.deleted = false AND BS.subscription_billing_uuid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($subscription_billing_uuid));
	
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
		$query.= " sub_period_started_date, sub_period_ends_date, sub_collection_mode, update_type, updateid, deleted)";
		$query.= " VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15) RETURNING _id";
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
						$subscription->getSubCollectionMode(),
						$subscription->getUpdateType(),
						$subscription->getUpdateId(),
						$subscription->getDeleted()));
		$row = pg_fetch_row($result);
		return(self::getBillingsSubscriptionById($row[0]));
	}
	
	/*public static function updateBillingsSubscription(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, planid = $1, sub_status = $2, sub_activated_date = $3, sub_canceled_date = $4,";
		$query.= " sub_expires_date = $5, sub_period_started_date = $6, sub_period_ends_date = $7, sub_collection_mode = $8, update_type = $9, updateid = $10";
		$query.= " WHERE _id = $11";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getPlanId(),
						$subscription->getSubStatus(),
						dbGlobal::toISODate($subscription->getSubActivatedDate()),
						dbGlobal::toISODate($subscription->getSubCanceledDate()),
						dbGlobal::toISODate($subscription->getSubExpiresDate()),
						dbGlobal::toISODate($subscription->getSubPeriodStartedDate()),
						dbGlobal::toISODate($subscription->getSubPeriodEndsDate()),
						$subscription->getSubCollectionMode(),
						$subscription->getUpdateType(),
						$subscription->getUpdateId(),
						$subscription->getId()));
		$row = pg_fetch_row($result);
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}*/
	
	//planid
	public static function updatePlanId(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, planid = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getPlanId(),
						$subscription->getId()));
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//subStatus
	public static function updateSubStatus(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, sub_status = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getSubStatus(),
						$subscription->getId()));
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//subActivatedDate
	public static function updateSubActivatedDate(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, sub_activated_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($subscription->getSubActivatedDate()),
						$subscription->getId()));
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//subCanceledDate
	public static function updateSubCanceledDate(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, sub_canceled_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($subscription->getSubCanceledDate()),
						$subscription->getId()));
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//subExpiresDate
	public static function updateSubExpiresDate(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, sub_expires_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($subscription->getSubExpiresDate()),
						$subscription->getId()));
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//subPeriodStardedDate
	public static function updateSubStartedDate(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, sub_period_started_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($subscription->getSubPeriodStartedDate()),
						$subscription->getId()));
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//subPeriodEndsDate
	public static function updateSubEndsDate(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, sub_period_ends_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($subscription->getSubPeriodEndsDate()),
						$subscription->getId()));
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//subCollectionMode
	public static function updateSubCollectionMode(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, sub_collection_mode = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getSubCollectionMode(),
						$subscription->getId()));
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//UpdateType
	public static function updateUpdateType(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, update_type = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getUpdateType(),
						$subscription->getId()));
		return(self::getBillingsSubscriptionById($subscription->getId()));
	}
	
	//UpdateId
	public static function updateUpdateId(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, updateid = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getUpdateId(),
						$subscription->getId()));
		return(self::getBillingsSubscriptionById($subscription->getId()));
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
	
	public static function deleteBillingsSubscriptionById($id) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, deleted = true WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query,
				array($id));
		return($result);
	}
	
	public static function getEndingBillingsSubscriptions($limit = 0, $offset = 0, $providerId = NULL, DateTime $sub_period_ends_date, $status_array = array('active')) {
		$params = array();
		$query = "SELECT ".self::$sfields." FROM billing_subscriptions BS";
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
			$query.= " AND BU.providerId = $".(count($params));
		}
		$params[] = dbGlobal::toISODate($sub_period_ends_date);
		$query.= " AND BS.sub_period_ends_date < $".(count($params));//STRICT
		$query.= " ORDER BY BU._id DESC";//LAST USERS FIRST
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$result = pg_query_params(config::getDbConn(), $query, $params);
		$out = array();
	
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getBillingsSubscriptionFromRow($row));
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getRequestingCanceledBillingsSubscriptions($limit = 0, $offset = 0, $providerId = NULL, $status_array = array('requesting_canceled')) {
		$params = array();
		$query = "SELECT ".self::$sfields." FROM billing_subscriptions BS";
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
			$query.= " AND BU.providerId = $".(count($params));
		}
		$query.= " ORDER BY BU._id DESC";//LAST USERS FIRST
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$result = pg_query_params(config::getDbConn(), $query, $params);
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getBillingsSubscriptionFromRow($row));
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
	private $sub_collection_mode;
	private $sub_period_started_date;
	private $sub_period_ends_date;
	private $update_type;
	private $updateId;
	private $deleted;
	//
	private $is_active;
	//
	private $billingsSubscriptionOpts = NULL;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function setSubscriptionBillingUuid($uuid) {
		$this->subscription_billing_uuid = $uuid;
	}
	
	public function getSubscriptionBillingUuid() {
		return($this->subscription_billing_uuid);
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
	
	public function getSubCollectionMode() {
		return($this->sub_collection_mode);
	}
	
	public function setSubCollectionMode($str) {
		$this->sub_collection_mode = $str;
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
	
	public function setIsActive($bool) {
		$this->is_active = $bool;
	}
	
	public function getIsActive() {
		return($this->is_active);
	}
	
	public function setBillingsSubscriptionOpts($billingsSubscriptionOpts) {
		$this->billingsSubscriptionOpts = $billingsSubscriptionOpts;
	}
	
	public function getBillingsSubscriptionOpts() {
		return($this->billingsSubscriptionOpts);
	}
	
	public function jsonSerialize() {
		$return = [
			'subscriptionBillingUuid' => $this->subscription_billing_uuid,
			'subscriptionProviderUuid' => $this->sub_uuid,
			'isActive' => $this->is_active,
			'user' =>	((UserDAO::getUserById($this->userid)->jsonSerialize())),
			'provider' => ((ProviderDAO::getProviderById($this->providerid)->jsonSerialize())),
			'creationDate' => $this->creation_date,
			'updatedDate' => $this->updated_date,
			'subStatus' => $this->sub_status,
			'subActivatedDate' => $this->sub_activated_date,
			'subCanceledDate' => $this->sub_canceled_date,
			'subExpiresDate' => $this->sub_expires_date,
			'subPeriodStartedDate' => $this->sub_period_started_date,
			'subPeriodEndsDate' => $this->sub_period_ends_date,
			'subOpts' => (BillingsSubscriptionOptsDAO::getBillingsSubscriptionOptsBySubId($this->_id)->jsonSerialize())
		];
		$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($this->planid));
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
		foreach ($user_opts->getOpts() as $k => $v) {
			if(isset($v)) {
				$query = "INSERT INTO billing_subscriptions_opts (subid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
						array($billingsSubscriptionOpts->getSubId(),
								$k,
								$v));
			}
		}
		return(self::getBillingsSubscriptionOptsBySubId($billingsSubscriptionOpts->getSubId()));
	}

	public static function updateBillingsSubscriptionOptsKey($subid, $key, $value) {
		$query = "UPDATE billing_subscriptions_opts SET value = $3 WHERE subid = $1 AND key = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array($subid, $key, $value));
		return($result);
	}

	public static function deleteBillingsSubscriptionOptsKey($subid, $key) {
		$query = "UPDATE billing_subscriptions_opts SET deleted = true WHERE subid = $1 AND key = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array($userid, $key));
		return($result);
	}

	public static function addBillingsSubscriptionOptsKey($subid, $key, $value) {
		$query = "INSERT INTO billing_subscriptions_opts (subid, key, value)";
		$query.= " VALUES ($1, $2, $3) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($subid,
						$key,
						$value));
		return($result);
	}

	public static function deleteBillingsSubscriptionOptBySubId($subid) {
		$query = "UPDATE billing_users_opts SET deleted = true WHERE subid = $1";
		$result = pg_query_params(config::getDbConn(), $query,
				array($subid));
		return($result);
	}
}

class BillingInfoOpts {

	private $opts = array();

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

class BillingsWebHookDAO {
	
	private static $sfields = "_id, providerid, post_data, processing_status, creation_date";
	
	private static function getBillingsWebHookFromRow($row) {
		$out = new BillingsWebHook();
		$out->setId($row["_id"]);
		$out->setProviderId($row["providerid"]);
		$out->setPostData($row["post_data"]);
		$out->setProcessingStatus($row["processing_status"]);
		$out->setCreationDate($row["creation_date"]);
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

	public static function addBillingsWebHook($providerid, $post_data) {
		$query = "INSERT INTO billing_webhooks (providerid, post_data) VALUES ($1, $2) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, array($providerid, $post_data));
		$row = pg_fetch_row($result);
		return(self::getBillingsWebHookById($row[0]));
	}

	public static function updateProcessingStatusById($id, $status) {
		$query = "UPDATE billing_webhooks SET processing_status = $1 WHERE _id = $2";
		pg_query_params(config::getDbConn(), $query, array($status ,$id));
	}
}

class BillingsWebHook {

	private $_id;
	private $providerid;
	private $post_data;
	private $processing_status;
	private $creation_date;

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

}

class BillingsWebHookLogDAO {
	
	private static $sfields = "_id, webhookid, processing_status, started_date, ended_date, message";
	
	private static function getBillingsWebHookLogFromRow($row) {
		$out = new BillingsWebHookLog();
		$out->setId($row["_id"]);
		$out->setWebHookId($row["webhookid"]);
		$out->setProcessingStatus($row["processing_status"]);
		$out->setStartedDate($row["started_date"]);
		$out->setEndedDate($row["ended_date"]);
		$out->setMessage($row["message"]);
		return($out);
	}

	public static function addBillingsWebHookLog($webhook_id) {
		$query = "INSERT INTO billing_webhook_logs (webhookid) VALUES ($1) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, array($webhook_id));
		$row = pg_fetch_row($result);
		return(self::getBillingsWebHookLogById($row[0]));
	}

	public static function updateBillingsWebHookLogProcessingStatus(BillingsWebHookLog $billingsWebHookLog) {
		$query = "UPDATE billing_webhook_logs SET processing_status = $1, ended_date = CURRENT_TIMESTAMP, message = $2 WHERE _id = $3";
		$result = pg_query_params(config::getDbConn(), $query, array($billingsWebHookLog->getProcessingStatus(), $billingsWebHookLog->getMessage(), $billingsWebHookLog->getId()));
		$row = pg_fetch_row($result);
		return(self::getBillingsWebHookLogById($row[0]));
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

	public function setStartedDate($date) {
		$this->started_date = $date;
	}

	public function getEndedDate() {
		return($this->ended_date);
	}

	public function setEndedDate($date) {
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
	
	public function setStartedDate($date) {
		$this->started_date = $date;
	}
	
	public function getEndedDate() {
		return($this->ended_date);
	}
	
	public function setEndedDate($date) {
		$this->ended_date = $date;
	}
	
	public function getMessage() {
		return($this->message);
	}
	
	public function setMessage($msg) {
		$this->message = $msg;
	}
	
}

class BillingsSubscriptionActionLogDAO {

	private static $sfields = "_id, subid, processing_status, action_type, started_date, ended_date, message";

	private static function getBillingsSubscriptionActionLogFromRow($row) {
		$out = new BillingsSubscriptionActionLog();
		$out->setId($row["_id"]);
		$out->setSubId($row["subid"]);
		$out->setProcessingStatus($row["processing_status"]);
		$out->setActionType($row["action_type"]);
		$out->setStartedDate($row["started_date"]);
		$out->setEndedDate($row["ended_date"]);
		$out->setMessage($row["message"]);
		return($out);
	}

	public static function addBillingsSubscriptionActionLog($subid, $action_type) {
		$query = "INSERT INTO billing_subscriptions_action_logs (subid, action_type, processing_status) VALUES ($1, $2, $3) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, array($subid, $action_type, "running"));
		$row = pg_fetch_row($result);
		return(self::getBillingsSubscriptionActionLogById($row[0]));
	}

	public static function updateBillingsSubscriptionActionLogProcessingStatus(BillingsSubscriptionActionLog $billingsSubscriptionActionLog) {
		$query = "UPDATE billing_subscriptions_action_logs SET processing_status = $1, ended_date = CURRENT_TIMESTAMP, message = $2 WHERE _id = $3";
		$result = pg_query_params(config::getDbConn(), $query, array($billingsSubscriptionActionLog->getProcessingStatus(), $billingsSubscriptionActionLog->getMessage(), $billingsSubscriptionActionLog->getId()));
		$row = pg_fetch_row($result);
		return(self::getBillingsSubscriptionActionLogById($row[0]));
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
	
	public function setStartedDate($date) {
		$this->started_date = $date;
	}
	
	public function getEndedDate() {
		return($this->ended_date);
	}
	
	public function setEndedDate($date) {
		$this->ended_date = $date;
	}
	
	public function getMessage() {
		return($this->message);
	}
	
	public function setMessage($msg) {
		$this->message = $msg;
	}
	
}

class ProcessingLogDAO {

	private static $sfields = "_id, providerid, processing_type, processing_status, started_date, ended_date, message";
	
	private static function getProcessingLogFromRow($row) {
		$out = new ProcessingLog();
		$out->setId($row["_id"]);
		$out->setProviderId($row["providerid"]);
		$out->setProcessingType($row["processing_type"]);
		$out->setProcessingStatus($row["processing_status"]);
		$out->setStartedDate($row["started_date"]);
		$out->setEndedDate($row["ended_date"]);
		$out->setMessage($row["message"]);
		return($out);
	}
	
	public static function addProcessingLog($providerid, $processing_type) {
		$query = "INSERT INTO billing_processing_logs (providerid, processing_type, processing_status) VALUES ($1, $2, $3) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, array($providerid, $processing_type, "running"));
		$row = pg_fetch_row($result);
		return(self::getProcessingLogById($row[0]));
	}
	
	public static function updateProcessingLogProcessingStatus(ProcessingLog $processingLog) {
		$query = "UPDATE billing_processing_logs SET processing_status = $1, ended_date = CURRENT_TIMESTAMP, message = $2 WHERE _id = $3";
		$result = pg_query_params(config::getDbConn(), $query, array($processingLog->getProcessingStatus(), $processingLog->getMessage(), $processingLog->getId()));
		$row = pg_fetch_row($result);
		return(self::getProcessingLogById($row[0]));
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
	
	public static function getProcessingLogByDay($providerid, $processing_type, Datetime $day) {
		$query = "SELECT ".self::$sfields." FROM billing_processing_logs WHERE providerid = $1 AND processing_type = $2 AND date(started_date) = date($3)";
		
		$result = pg_query_params(config::getDbConn(), $query, array($providerid, $processing_type, dbGlobal::toISODate($day)));
		
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getProcessingLogFromRow($row));
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}


class UtilsDAO {
	

}

?>