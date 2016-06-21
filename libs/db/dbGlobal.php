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
		$out->setDeleted($row["deleted"] == 't' ? true : false);
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
			if(isset($v)) {
				$query = "INSERT INTO billing_users_opts (userid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
						array($user_opts->getUserId(),
								trim($k),
								trim($v)));
			}
		}
		return(self::getUserOptsByUserId($user_opts->getUserId()));
	}
	
	public static function updateUserOptsKey($userid, $key, $value) {
		$query = "UPDATE billing_users_opts SET value = $3 WHERE userid = $1 AND key = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array($userid, $key, trim($value)));
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
						trim($key),
						trim($value)));
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
	
	private static $sfields = NULL;
	
	public static function init() {
		InternalPlanDAO::$sfields = "BIP._id, BIP.internal_plan_uuid, BIP.name, BIP.description,".
			" BIP.amount_in_cents, BIP.currency, BIP.cycle, BIP.period_unit, BIP.period_length, BIP.thumbid, BIP.vat_rate,".
			" BIP.trial_enabled, BIP.trial_period_length, BIP.trial_period_unit, BIP.is_visible";
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
	
	public static function getInternalPlans($providerId = NULL, $contextId = NULL, $isVisible = NULL, $country = NULL) {
		$query = "SELECT ".self::$sfields." FROM billing_internal_plans BIP";
		$params = array();
		
		$out = array();
		
		if(isset($providerId)) {
			$query.= " INNER JOIN billing_internal_plans_links BIPL ON (BIP._id = BIPL.internal_plan_id)";
			$query.= " INNER JOIN billing_plans BP ON (BIPL.provider_plan_id = BP._id)";
		}
		
		if(isset($contextId)) {
			$query.= " INNER JOIN billing_internal_plans_by_context BIPBC ON (BIPBC.internal_plan_id = BIP._id)";
		}
		
		if(isset($country)) {
			$query.= "INNER JOIN billing_internal_plans_by_country BIPBCY ON (BIPBCY.internal_plan_id = BIP._id)";	
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
		
		$query.= $where;
		if(isset($contextId)) {
			$query.= " ORDER BY BIPBC.index ASC";
		}
		//echo $query;
		$result = pg_query_params(config::getDbConn(), $query, $params);
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getInternalPlanFromRow($row));
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function addInternalPlan(InternalPlan $internalPlan) {
		$query = "INSERT INTO billing_internal_plans (internal_plan_uuid, name, description, amount_in_cents, currency, cycle, period_unit, period_length, trial_enabled, trial_period_length, trial_period_unit, vat_rate)";
		$query.= " VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12) RETURNING _id";
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
					$internalPlan->getVatRate()
				));
		$row = pg_fetch_row($result);
		return(self::getInternalPlanById($row[0]));
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

class InternalPlan implements JsonSerializable {
	
	private static $curenciesForDisplay = array(
		'XOF' => 'FCFA'
	);
	
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
	
	public function getCurrencyForDisplay() {
		if(array_key_exists($this->currency, self::$curenciesForDisplay)) {
			return(self::$curenciesForDisplay[$this->currency]);
		}
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
		if($this->vatRate == NULL) {
			return($this->amount_in_cents);
		} else {
			return(intval(round($this->amount_in_cents / (1 + $this->vatRate / 100))));
		}
	}
	
	public function getAmountExclTax() {
		if($this->vatRate == NULL) {
			return($this->amount_in_cents / 100);
		} else {
			return(($this->amount_in_cents / (1 + $this->vatRate / 100)) / 100);
		}
	}
	
	public function getAmount() {
		return((float) ($this->amount_in_cents / 100));
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
	
	public function jsonSerialize() {
		$return =
			[
				'internalPlanUuid' => $this->internal_plan_uuid,
				'name' => $this->name,
				'description' => $this->description,
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
				'isVisible' => $this->isVisible == 't' ? true : false,
				'countries' => InternalPlanCountryDAO::getInternalPlanCountries($this->_id)
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
								trim($k),
								trim($v)));
			}
		}
		return(self::getInternalPlanOptsByInternalPlanId($internalplan_opts->getInternalPlanId()));
	}
	
	public static function updateInternalPlanOptsKey($internalplanid, $key, $value) {
		$query = "UPDATE billing_internal_plans_opts SET value = $3 WHERE internalplanid = $1 AND key = $2";
		$result = pg_query_params(config::getDbConn(), $query,
			array($internalplanid, $key, trim($value)));
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
						trim($key),
						trim($value)));
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
			"BS.update_type, BS.updateid, BS.deleted";
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
		$query.= " sub_period_started_date, sub_period_ends_date,  update_type, updateid, deleted)";
		$query.= " VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14) RETURNING _id";
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
						$subscription->getDeleted()));
		$row = pg_fetch_row($result);
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
		updated_date = CURRENT_TIMESTAMP
		WHERE _id=$7';

		pg_query_params(config::getDbConn(), $query, [
			$subscription->getPlanId(),
			$subscription->getSubStatus(),
			dbGlobal::toISODate($subscription->getSubActivatedDate()),
			dbGlobal::toISODate($subscription->getSubCanceledDate()),
			dbGlobal::toISODate($subscription->getSubPeriodStartedDate()),
			dbGlobal::toISODate($subscription->getSubPeriodEndsDate()),
			$subscription->getId()
		]);

		return self::getBillingsSubscriptionById($subscription->getId());
	}

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
	
	//updateDeleted
	public static function updateDeleted(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, deleted = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$subscription->getDeleted(),
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
	
	public static function getBillingsSubscripionByUserReferenceUuid($userReferenceUuid) {
		$query = "SELECT ".self::$sfields." FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_users BU ON (BS.userid = BU._id)";
		$query.= " WHERE BS.deleted = false AND BU.deleted = false AND BU.user_reference_uuid = $1 ORDER BY BS.sub_activated_date DESC";
		$result = pg_query_params(config::getDbConn(), $query, array($userReferenceUuid));
		
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
	
	public static function getEndingBillingsSubscriptions($limit = 0, $offset = 0, $providerId = NULL, DateTime $sub_period_ends_date, $status_array = array('active'), $cycle_array = NULL, $providerIdsToIgnore_array = NULL) {
		$params = array();
		$query = "SELECT ".self::$sfields." FROM billing_subscriptions BS";
		$query.= " INNER JOIN billing_users BU ON (BS.userid = BU._id)";
		if(isset($cycle_array)) {
			$query.= " INNER JOIN billing_plans BP ON (BS.planid = BP._id)";
			$query.= " INNER JOIN billing_internal_plans_links BIPL ON (BIPL.provider_plan_id = BP._id)";
			$query.= " INNER JOIN billing_internal_plans BIP ON (BIPL.internal_plan_id = BIP._id)";
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
		$sub_period_ends_date_str = dbGlobal::toISODate($sub_period_ends_date);
		$query.= " AND BS.sub_period_ends_date < '".$sub_period_ends_date_str."'";//STRICT
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
			$query.= " AND BU.providerid = $".(count($params));
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
	private $sub_period_started_date;
	private $sub_period_ends_date;
	private $update_type;
	private $updateId;
	private $deleted;
	//
	private $is_active;
	//
	private $billingsSubscriptionOpts = NULL;
	//
	private $in_trial = false;
	private $is_cancelable = true;
	private $is_reactivable = false;

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
	
	public function jsonSerialize() {
		$return = [
			'subscriptionBillingUuid' => $this->subscription_billing_uuid,
			'subscriptionProviderUuid' => $this->sub_uuid,
			'isActive' => $this->is_active,
			'inTrial' => ($this->in_trial) ? 'yes' : 'no',
			'isCancelable' => ($this->is_cancelable) ? 'yes' : 'no',
			'isReactivable' => ($this->is_reactivable) ? 'yes' : 'no',
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
			if(isset($v)) {
				$query = "INSERT INTO billing_subscriptions_opts (subid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
						array($billingsSubscriptionOpts->getSubId(),
								trim($k),
								trim($v)));
			}
		}
		return(self::getBillingsSubscriptionOptsBySubId($billingsSubscriptionOpts->getSubId()));
	}

	public static function updateBillingsSubscriptionOptsKey($subid, $key, $value) {
		$query = "UPDATE billing_subscriptions_opts SET value = $3 WHERE subid = $1 AND key = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array($subid, $key, trim($value)));
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
						trim($key),
						trim($value)));
		return($result);
	}

	public static function deleteBillingsSubscriptionOptBySubId($subid) {
		$query = "UPDATE billing_subscriptions_opts SET deleted = true WHERE subid = $1";
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

	public function getOpt($key)
	{
		if (array_key_exists($key, $this->opts)) {
			return $this->opts[$key];
		}

		return null;
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
		$query = "UPDATE billing_subscriptions_action_logs SET processing_status = $1, ended_date = CURRENT_TIMESTAMP, message = $2, processing_status_code = $3 WHERE _id = $4";
		$result = pg_query_params(config::getDbConn(), $query, 
				array($billingsSubscriptionActionLog->getProcessingStatus(), 
						$billingsSubscriptionActionLog->getMessage(),
						$billingsSubscriptionActionLog->getProcessingStatusCode(),
						$billingsSubscriptionActionLog->getId()));
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
	
	public static function getProcessingLogByDay($providerid = NULL, $processing_type, Datetime $day) {
		$dayStr = dbGlobal::toISODate($day);
		$params = array();
		$query = "SELECT ".self::$sfields." FROM billing_processing_logs WHERE processing_type = $1";
		$params[] = $processing_type;
		if(isset($providerid)) {
			$query.= " AND providerid = $2";
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

class CouponsCampaign implements JsonSerializable {
	
	private $_id;
	private $uuid;
	private $name;
	private $description;
	private $creation_date;
	private $providerid;
	private $providerplanid;
	private $prefix;
	private $generated_code_length;
	private	$total_number;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getUuid() {
		return($this->uuid);
	}
	
	public function setUuid($uuid) {
		$this->uuid = $uuid;
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
	
	public function getCreationDate() {
		return($this->creation_date);
	}
	
	public function setCreationDate($date) {
		$this->creation_date = $date;
	}
	
	public function setProviderId($id) {
		$this->providerid = $id;
	}
	
	public function getProviderId() {
		return($this->providerid);
	}

	public function setProviderPlanId($id) {
		$this->providerplanid = $id;
	}
	
	public function getProviderPlanId() {
		return($this->providerplanid);
	}
	
	public function setPrefix($str) {
		$this->prefix = $str;
	}
	
	public function getPrefix() {
		return($this->prefix);
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
	
	public function jsonSerialize() {
		$return = [
			'couponsCampaignBillingUuid' => $this->uuid,
			'creationDate' => $this->creation_date,
			'name' => $this->name,
			'description' => $this->description,
			'provider' => ((ProviderDAO::getProviderById($this->providerid)->jsonSerialize()))
		];
		$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($this->providerplanid));
		$internalPlan->setShowProviderPlans(false);
		$return['internalPlan'] = $internalPlan->jsonSerialize();
		return($return);
	}
	
}

class CouponsCampaignDAO {
	
	private static $sfields = "_id, coupons_campaigns_uuid, creation_date, name, description, providerid, providerplanid, prefix, generated_code_length, total_number";
	
	private static function getCouponsCampaignFromRow($row) {
		$out = new CouponsCampaign();
		$out->setId($row["_id"]);
		$out->setUuid($row["coupons_campaigns_uuid"]);
		$out->setCreationDate($row["creation_date"]);
		$out->setName($row["name"]);
		$out->setDescription($row["description"]);
		$out->setProviderId($row["providerid"]);
		$out->setProviderPlanId($row["providerplanid"]);
		$out->setPrefix($row["prefix"]);
		$out->setGeneratedCodeLength($row["generated_code_length"]);
		$out->setTotalNumber($row["total_number"]);
		return($out);
	}
	
	public static function getCouponsCampaignById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_coupons_campaigns WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getCouponsCampaignFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);		
	}
	
	public static function getCouponsCampaignByUuid($uuid) {
		$query = "SELECT ".self::$sfields." FROM billing_coupons_campaigns WHERE coupons_campaigns_uuid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($uuid));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getCouponsCampaignFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getCouponsCampaigns($providerId = NULL) {
		$query = "SELECT ".self::$sfields." FROM billing_coupons_campaigns";
		$params = array();
	
		$out = array();
	
		if(isset($providerId)) {
			$query.= " WHERE providerid = $1";
			$params[] = $providerId;
		}
		$result = pg_query_params(config::getDbConn(), $query, $params);
	
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getCouponsCampaignFromRow($row));
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
}

class Coupon implements JsonSerializable {
	
	private $_id;
	private $couponBillingUuid;
	private $couponscampaignid;
	private $providerid;
	private $providerplanid;
	private $code;
	private $status;
	private $creation_date;
	private $updated_date;
	private $redeemed_date;
	private $expires_date;
	private $userid;
	private $subid;

	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function setCouponBillingUuid($uuid) {
		$this->couponBillingUuid = $uuid;
	}
	
	public function getCouponBillingUuid() {
		return($this->couponBillingUuid);
	}
	
	public function getCouponsCampaignId() {
		return($this->couponscampaignid);
	}
	
	public function setCouponsCampaignId($id) {
		$this->couponscampaignid = $id;
	}
	
	public function setProviderId($id) {
		$this->providerid = $id;
	}
	
	public function getProviderId() {
		return($this->providerid);
	}
	
	public function setProviderPlanId($id) {
		$this->providerplanid = $id;
	}
	
	public function getProviderPlanId() {
		return($this->providerplanid);
	}
	
	public function getCode() {
		return($this->code);
	}
	
	public function setCode($str) {
		$this->code = $str;
	}
	
	public function getStatus() {
		return($this->status);
	}
	
	public function setStatus($status) {
		$this->status = $status;
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
	
	public function setRedeemedDate($date) {
		$this->redeemed_date = $date;
	}
	
	public function getRedeemedDate() {
		return($this->redeemed_date);
	}
	
	public function setExpiresDate($date) {
		$this->expires_date = $date;
	}
	
	public function getExpiresDate() {
		return($this->expires_date);
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
	
	public function jsonSerialize() {
		$return = [
				'couponBillingUuid' => $this->couponBillingUuid,
				'code' => $this->code,
				'status' => $this->status,
				'campaign' => CouponsCampaignDAO::getCouponsCampaignById($this->couponscampaignid)->jsonSerialize(),
				'provider' => ProviderDAO::getProviderById($this->providerid)->jsonSerialize()
		];
		$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($this->providerplanid));
		$internalPlan->setShowProviderPlans(false);
		$return['internalPlan'] = $internalPlan->jsonSerialize();
		return($return);
	}
}

class CouponDAO {
	
	private static $sfields = NULL;
	
	public static function init() {
		CouponDAO::$sfields = "_id, coupon_billing_uuid, couponscampaignsid, providerid, providerplanid, code, coupon_status,".
							" creation_date, updated_date, redeemed_date, expires_date,".
							" userid, subid";
	}
	
	private static function getCouponFromRow($row) {
		$out = new Coupon();
		$out->setId($row["_id"]);
		$out->setCouponBillingUuid($row['coupon_billing_uuid']);
		$out->setCouponsCampaignId($row["couponscampaignsid"]);
		$out->setProviderId($row["providerid"]);
		$out->setProviderPlanId($row["providerplanid"]);
		$out->setCode($row["code"]);
		$out->setStatus($row["coupon_status"]);
		$out->setCreationDate($row["creation_date"]);
		$out->setUpdatedDate($row["updated_date"]);
		$out->setRedeemedDate($row["redeemed_date"]);
		$out->setExpiresDate($row["expires_date"]);
		$out->setUserId($row["userid"]);
		$out->setSubId($row["subid"]);
		return($out);
	}
	
	public static function getCouponById($id) {
		$query = "SELECT ".self::$sfields." FROM billing_coupons WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getCouponFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getCouponByCouponBillingUuid($coupon_billing_uuid) {
		$query = "SELECT ".self::$sfields." FROM billing_coupons WHERE coupon_billing_uuid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($coupon_billing_uuid));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getCouponFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getCoupon($providerId, $couponCode, $userId = NULL) {
		$query = "SELECT ".self::$sfields." FROM billing_coupons WHERE providerid = $1 AND lower(code) = lower($2)";						
		if(isset($userId)) {
			$query.= " AND userid = $3";
		}
		$query_params = array($providerId, $couponCode);
		if(isset($userId)) {
			array_push($query_params, $userId);
		}
		
		$result = pg_query_params(config::getDbConn(), $query, $query_params);
		
		$out = null;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getCouponFromRow($row);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function addCoupon(Coupon $coupon) {
		$query = "INSERT INTO billing_coupons (coupon_billing_uuid, couponscampaignsid, providerid, providerplanid, code, expires_date, userid)";
		$query.= " VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$coupon->getCouponBillingUuid(),
						$coupon->getCouponsCampaignId(),
						$coupon->getProviderId(),
						$coupon->getProviderPlanId(),
						$coupon->getCode(),
						dbGlobal::toISODate($coupon->getExpiresDate()),
						$coupon->getUserId()
				));
		$row = pg_fetch_row($result);
		return(self::getCouponById($row[0]));
	}
	
	public static function getCouponsTotalNumberByCouponsCampaignId($couponscampaignsid) {
		$query = "SELECT count(*) as counter FROM billing_coupons WHERE couponscampaignsid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($couponscampaignsid));
		
		$out = 0;
		
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = $row['counter'];
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function updateStatus(Coupon $coupon) {
		$query = "UPDATE billing_coupons SET updated_date = CURRENT_TIMESTAMP, coupon_status = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$coupon->getStatus(),
						$coupon->getId()));
		return(self::getCouponById($coupon->getId()));
	}
	
	public static function updateRedeemedDate(Coupon $coupon) {
		$query = "UPDATE billing_coupons SET updated_date = CURRENT_TIMESTAMP, redeemed_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($coupon->getRedeemedDate()),
						$coupon->getId()));
		return(self::getCouponById($coupon->getId()));
	}
	
	public static function updateExpiresDate(Coupon $coupon) {
		$query = "UPDATE billing_coupons SET updated_date = CURRENT_TIMESTAMP, expires_date = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	dbGlobal::toISODate($coupon->getExpiresDate()),
						$coupon->getId()));
		return(self::getCouponById($coupon->getId()));
	}

	public static function updateSubId(Coupon $coupon) {
		$query = "UPDATE billing_coupons SET updated_date = CURRENT_TIMESTAMP, subid = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$coupon->getSubId(),
						$coupon->getId()));
		return(self::getCouponById($coupon->getId()));		
	}
	
	public static function updateUserId(Coupon $coupon) {
		$query = "UPDATE billing_coupons SET updated_date = CURRENT_TIMESTAMP, userid = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array(	$coupon->getUserId(),
						$coupon->getId()));
		return(self::getCouponById($coupon->getId()));		
	}

	public static function getCouponsByUserId($userid, $couponscampaignsid = NULL) {
		$query = "SELECT ".self::$sfields." FROM billing_coupons WHERE userid = $1";
		$params = array();
		$params[] = $userid;
		if(isset($couponscampaignsid)) {
			$query.= " AND couponscampaignsid= $2";
			$params[] = $couponscampaignsid;
		}
		$result = pg_query_params(config::getDbConn(), $query, $params);
		
		$out = array();
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getCouponFromRow($row));
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

CouponDAO::init();

class Context implements JsonSerializable {
	
	private $_id;
	private $context_uuid;
	private $context_country;
	private $name;
	private $description;
	
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
	
	private static $sfields = "_id, context_uuid, country, name, description";
	
	private static function getContextFromRow($row) {
		$out = new Context();
		$out->setId($row["_id"]);
		$out->setContextUuid($row["context_uuid"]);
		$out->setContextCountry($row["country"]);
		$out->setName($row["name"]);
		$out->setDescription($row["description"]);
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
	
	public static function getContext($contextBillingUuid, $contextCountry) {
		$query = "SELECT ".self::$sfields." FROM billing_contexts WHERE context_uuid = $1 AND country = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($contextBillingUuid, $contextCountry));
	
		$out = null;
	
		if ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getContextFromRow($row);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getContexts($contextCountry = NULL) {
		$query = "SELECT ".self::$sfields." FROM billing_contexts";
		$params = array();
		if(isset($contextCountry)) {
			$query.= " WHERE country = $1";
			$params[] = $contextCountry;
		}
		
		$result = pg_query_params(config::getDbConn(), $query, $params);
	
		$out = array();
	
		while($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getContextFromRow($row));
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function addContext(Context $context) {
		$query = "INSERT INTO billing_contexts (context_uuid, country, name, description)";
		$query.= " VALUES ($1, $2, $3, $4) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($context->getContextUuid(),
						$context->getContextCountry(),
						$context->getName(),
						$context->getDescription()
				));
		$row = pg_fetch_row($result);
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
		return(self::getInternalPlanCountryById($row[0]));
	}
	
	public static function deleteInternalPlanCountryById($id) {
		$query = "DELETE FROM billing_internal_plans_by_country";
		$query.= " WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query,
				array($id));		
		return($result);
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
		
		while($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
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
			config::getLogger()->addError("INDEX IS NULL");
			$index = self::getMaxIndex($internalPlanContext->getContextId()) + 1;
		}
		config::getLogger()->addError("INDEX =".$index);
		$result = pg_query_params(config::getDbConn(), $query,
				array($internalPlanContext->getInternalPlanId(),
						$internalPlanContext->getContextId(),
						$index
				));
		$row = pg_fetch_row($result);
		return(self::getInternalPlanContextById($row[0]));
	}

	public static function deleteInternalPlanContextById($id) {
		$query = "DELETE FROM billing_internal_plans_by_context";
		$query.= " WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query,
				array($id));
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
		config::getLogger()->addInfo(var_export(array($old,
					$new,
					$lower,
					$upper,
					$internalPlanContext->getContextId()), true));
		$query = "UPDATE billing_internal_plans_by_context SET index = $1 WHERE _id = $2";
		$result = pg_query_params(config::getDbConn(), $query,
				array($new,
					$internalPlanContext->getId()
				));
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
		
		while($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getUsersRequestsLogFromRow($row));
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

class UtilsDAO {
	

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

class BillingsCouponsOptsDAO {

	public static function getBillingsCouponsOptsByCouponId($couponId) {
		$query = "SELECT _id, couponid, key, value FROM billing_coupons_opts WHERE deleted = false AND couponid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($couponId));

		$out = new BillingsCouponsOpts();
		$out->setCouponId($couponId);
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out->setOpt($row["key"], $row["value"]);
		}
		// free result
		pg_free_result($result);

		return($out);
	}

	public static function addBillingsCouponsOpts(BillingsCouponsOpts $billingsCouponsOpts) {
		foreach ($billingsCouponsOpts->getOpts() as $k => $v) {
			if(isset($v)) {
				$query = "INSERT INTO billing_coupons_opts (couponid, key, value)";
				$query.= " VALUES ($1, $2, $3) RETURNING _id";
				$result = pg_query_params(config::getDbConn(), $query,
					array($billingsCouponsOpts->getCouponId(),
						trim($k),
						trim($v)));
			}
		}
		return(self::getBillingsCouponsOptsByCouponId($billingsCouponsOpts->getSubId()));
	}

	public static function updateBillingsCouponsOptsKey($couponId, $key, $value) {
		$query = "UPDATE billing_coupons_opts SET value = $3 WHERE couponid = $1 AND key = $2";
		$result = pg_query_params(config::getDbConn(), $query,
			array($couponId, $key, trim($value)));
		return($result);
	}

	public static function deleteBillingsCouponsOptsKey($couponId, $key) {
		$query = "UPDATE billing_coupons_opts SET deleted = true WHERE couponid = $1 AND key = $2";
		$result = pg_query_params(config::getDbConn(), $query,
			array($couponId, $key));
		return($result);
	}

	public static function addBillingsCouponsOptsKey($couponId, $key, $value) {
		$query = "INSERT INTO billing_coupons_opts (couponid, key, value)";
		$query.= " VALUES ($1, $2, $3) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
			array($couponId,
				trim($key),
				trim($value)));
		return($result);
	}

	public static function deleteBillingsCouponsOptsByCouponId($couponId) {
		$query = "UPDATE billing_coupons_opts SET deleted = true WHERE couponid = $1";
		$result = pg_query_params(config::getDbConn(), $query,
			array($couponId));
		return($result);
	}
}
