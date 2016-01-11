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
		$query = "SELECT _id, creation_date, providerid, user_billing_uuid, user_reference_uuid, user_provider_uuid, deleted FROM billing_users WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new User();
			$out->setId($line["_id"]);
			$out->setUserBillingUuid($line["user_billing_uuid"]);
			$out->setCreationDate($line["creation_date"]);
			$out->setProviderId($line["providerid"]);
			$out->setUserReferenceUuid($line["user_reference_uuid"]);
			$out->setUserProviderUuid($line["user_provider_uuid"]);
			$out->setDeleted($line["deleted"]);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getUsersByUserReferenceUuid($user_reference_uuid, $providerid = NULL) {
		$query = "SELECT _id FROM billing_users WHERE deleted = false AND user_reference_uuid = $1";
		if(isset($providerid)) {
			$query.= " AND providerid = $2";
		}
		$query_params = array($user_reference_uuid);
		if(isset($providerid)) {
			array_push($query_params, $providerid);
		}
		$result = pg_query_params(config::getDbConn(), $query, $query_params);
		
		$out = array();	
		
		while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getUserById($line['_id']));
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getUserByUserProviderUuid($providerid, $user_provider_uuid) {
		$query = "SELECT _id FROM billing_users WHERE deleted = false AND providerid = $1 AND user_provider_uuid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($providerid, $user_provider_uuid));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getUserById($line['_id']);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getUserByUserBillingUuid($user_billing_uuid) {
		$query = "SELECT _id FROM billing_users WHERE deleted = false AND user_billing_uuid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($user_billing_uuid));
	
		$out = null;
	
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getUserById($line['_id']);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getUsers($id = NULL, $limit = 0, $offset = 0) {
		$query = "SELECT _id FROM billing_users WHERE deleted = false";
		if(isset($id)) { $query.= " AND _id <= ".$id; }
		$query.= " ORDER BY _id DESC";//LAST USERS FIRST
		if($limit > 0) { $query.= " LIMIT ".$limit; }
		if($offset > 0) { $query.= " OFFSET ".$offset; }
		$result = pg_query_params(config::getDbConn(), $query, array());
		$out = array();
	
		while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getUserById($line['_id']));
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
		while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out->setOpt($line["key"], $line["value"]);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function addUserOpts(UserOpts $user_opts) {
		foreach ($user_opts->getOpts() as $k => $v) {
			$query = "INSERT INTO billing_users_opts (userid, key, value)";
			$query.= " VALUES ($1, $2, $3) RETURNING _id";
			$result = pg_query_params(config::getDbConn(), $query,
					array($user_opts->getUserId(),
							$k,
							$v));
		}
		return(self::getUserOptsByUserId($user_opts->getUserId()));
	}
	
	public static function deleteUserOptsByUserId($userid) {
		$query = "UPDATE billing_users_opts SET deleted = true WHERE userid = $1";
		$result = pg_query_params(config::getDbConn(), $query,
				array($userid));
		return($result);
	}
}

class InternalPlanDAO {
	
	public static function getInternalPlanById($planid) {
		$query = "SELECT _id, internal_plan_uuid, name, description, amount_in_cents, currency, cycle, period_unit, period_length";
		$query.= " FROM billing_internal_plans WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($planid));
	
		$out = null;
	
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new InternalPlan();
			$out->setId($line["_id"]);
			$out->setInternalPlanUid($line["internal_plan_uuid"]);
			$out->setName($line["name"]);
			$out->setDescription($line["description"]);
			$out->setAmoutInCents($line["amount_in_cents"]);
			$out->setCurrency($line["currency"]);
			$out->setCycle(new PlanCycle($line["cycle"]));
			$out->setPeriodUnit(new PlanPeriodUnit($line["period_unit"]));
			$out->setPeriodLength($line["period_length"]);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getInternalPlanByUuid($internal_plan_uuid) {
		$query = "SELECT _id FROM billing_internal_plans WHERE internal_plan_uuid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($internal_plan_uuid));
	
		$out = null;
	
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getInternalPlanById($line['_id']);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getInternalPlanByName($name) {
		$query = "SELECT _id FROM billing_internal_plans WHERE name = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($name));
	
		$out = null;
	
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getInternalPlanById($line['_id']);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getInternalPlans($providerId = NULL) {
		$query = "SELECT BIP._id as _id FROM billing_internal_plans BIP";
		$params = array();
		
		$out = array();
		
		if(isset($providerId)) {
			$query.= " INNER JOIN billing_internal_plans_links BIPL ON (BIP._id = BIPL.internal_plan_id)";
			$query.= " INNER JOIN billing_plans BP ON (BIPL.provider_plan_id = BP._id)";
			$query.= " WHERE BP.providerid = $1";
			$params[] = $providerId;
		}
		$result = pg_query_params(config::getDbConn(), $query, $params);
		
		while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getInternalPlanById($line['_id']));
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function addInternalPlan(InternalPlan $internalPlan) {
		$query = "INSERT INTO billing_internal_plans (internal_plan_uuid, name, description, amount_in_cents, currency, cycle, period)";
		$query.= " VALUES ($1, $2, $3, $4, $5, $6,$7) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, 
				array($internalPlan->getInternalPlanUuid(),
					$internalPlan->getName(),
					$internalPlan->getDescription(),
					$internalPlan->getAmountInCents(),
					$internalPlan->getCurrency(),
					$internalPlan->getCycle(),
					$internalPlan->getPeriod()));
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
	
	public function setAmoutInCents($integer) {
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
	
	public function jsonSerialize() {
		$return =
			[
				'internalPlanUuid' => $this->internal_plan_uuid,
				'name' => $this->name,
				'description' => $this->description,
				'amount_in_cents' => $this->amount_in_cents,
				'currency' => $this->currency,
				'cycle' => $this->cycle,
				'periodUnit' => $this->periodUnit,
				'periodLength' => $this->periodLength,
				'internalPlanOpts' => (InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($this->_id)->jsonSerialize())
		];
		if($this->showProviderPlans) {
			$return['providerPlans'] = PlanDAO::getPlans(InternalPlanLinksDAO::getProviderPlanIdsFromInternalPlanId($this->_id));
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
		while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out->setOpt($line["key"], $line["value"]);
		}
		// free result
		pg_free_result($result);

		return($out);
	}
	
}

class InternalPlanLinksDAO {
	
	public static function getProviderPlanIdFromInternalPlanId($internalplanid, $providerid) {
		$query = "SELECT BP._id as billing_plan_id FROM billing_plans BP INNER JOIN billing_internal_plans_links BIPL ON (BP._id = BIPL.provider_plan_id)";
		$query.= "WHERE BIPL.internal_plan_id = $1 AND BP.providerid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($internalplanid, $providerid));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = $line["billing_plan_id"];
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getInternalPlanIdFromProviderPlanId($providerplanid) {
		$query = "SELECT internal_plan_id as billing_internal_plan_id FROM billing_internal_plans_links BIPL WHERE BIPL.provider_plan_id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($providerplanid));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = $line["billing_internal_plan_id"];
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
		
		while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, $line["billing_plan_id"]);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

class PlanDAO {
	
	public static function getPlanByUuid($providerId, $plan_uuid) {
		$query = "SELECT _id, providerid, plan_uuid, name, description FROM billing_plans WHERE providerid = $1 AND plan_uuid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($providerId, $plan_uuid));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new Plan();
			$out->setId($line["_id"]);
			$out->setProviderId($line["providerid"]);
			$out->setPlanUid($line["plan_uuid"]);
			$out->setName($line["name"]);
			$out->setDescription($line["description"]);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getPlanById($plan_id) {
		$query = "SELECT _id, providerid, plan_uuid, name, description FROM billing_plans WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($plan_id));
	
		$out = null;
	
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new Plan();
			$out->setId($line["_id"]);
			$out->setProviderId($line["providerid"]);
			$out->setPlanUid($line["plan_uuid"]);
			$out->setName($line["name"]);
			$out->setDescription($line["description"]);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getPlans(array $list_of_billing_plan_ids) {
		if(count($list_of_billing_plan_ids) == 0) return(array());
		$query = "SELECT BP._id as _id FROM billing_plans BP";
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
		
		while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getPlanById($line['_id']));
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
		while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out->setOpt($line["key"], $line["value"]);
		}
		// free result
		pg_free_result($result);

		return($out);
	}

}

class ProviderDAO {
	
	public static function getProviderByName($name) {
		$query = "SELECT _id, name FROM billing_providers WHERE name = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($name));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new Provider();
			$out->setId($line["_id"]);
			$out->setName($line["name"]);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getProviderById($providerid) {
		$query = "SELECT _id, name FROM billing_providers WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($providerid));
	
		$out = null;
	
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new Provider();
			$out->setId($line["_id"]);
			$out->setName($line["name"]);
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
	
	public static function getBillingsSubscriptionById($id) {
		$query = "SELECT _id, subscription_billing_uuid, providerid, userid, planid, creation_date, updated_date, sub_uuid, sub_status,";
		$query.= " sub_activated_date, sub_canceled_date, sub_expires_date, sub_period_started_date, sub_period_ends_date,";
		$query.= " sub_collection_mode, update_type, updateid, deleted";
		$query.= " FROM billing_subscriptions WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
	
		$out = null;
	
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new BillingsSubscription();
			$out->setId($line["_id"]);
			$out->setSubscriptionBillingUuid($line["subscription_billing_uuid"]);
			$out->setProviderId($line["providerid"]);
			$out->setUserId($line["userid"]);
			$out->setPlanId($line["planid"]);
			$out->setCreationDate($line["creation_date"]);
			$out->setUpdatedDate($line["updated_date"]);
			$out->setSubUid($line["sub_uuid"]);
			$out->setSubStatus($line["sub_status"]);
			$out->setSubActivatedDate($line["sub_activated_date"]);
			$out->setSubCanceledDate($line["sub_canceled_date"]);
			$out->setSubExpiresDate($line["sub_expires_date"]);
			$out->setSubCollectionMode($line["sub_collection_mode"]);
			$out->setSubPeriodStartedDate($line["sub_period_started_date"]);
			$out->setSubPeriodEndsDate($line["sub_period_ends_date"]);
			$out->setUpdateType($line["update_type"]);
			$out->setUpdateId($line["updateid"]);
			$out->setDeleted($line["deleted"]);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getBillingsSubscriptionBySubscriptionBillingUuid($subscription_billing_uuid) {
		$query = "SELECT _id FROM billing_subscriptions WHERE deleted = false AND subscription_billing_uuid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($subscription_billing_uuid));
	
		$out = null;
	
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingsSubscriptionById($line['_id']);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}
	
	public static function getBillingsSubscriptionBySubUuid($providerId, $sub_uuid) {
		$query = "SELECT _id FROM billing_subscriptions WHERE deleted = false AND providerid = $1 AND sub_uuid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($providerId, $sub_uuid));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = self::getBillingsSubscriptionById($line['_id']);
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
		$query = "SELECT _id FROM billing_subscriptions WHERE deleted = false AND userid = $1 ORDER BY sub_activated_date DESC";
		$result = pg_query_params(config::getDbConn(), $query, array($userId));
		
		$out = array();
		
		while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			array_push($out, self::getBillingsSubscriptionById($line['_id']));
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
}

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
			'subPeriodEndsDate' => $this->sub_period_ends_date
		];
		$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($this->planid));
		$internalPlan->setShowProviderPlans(false);
		$return['internalPlan'] = $internalPlan->jsonSerialize();
		return($return);
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

	public static function getBillingsWebHookById($id) {
		$query = "SELECT _id, providerid, post_data, processing_status, creation_date FROM billing_webhooks WHERE _id = $1";

		$result = pg_query_params(config::getDbConn(), $query, array($id));

		$out = null;

		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new BillingsWebHook();
			$out->setId($line["_id"]);
			$out->setProviderId($line["providerid"]);
			$out->setPostData($line["post_data"]);
			$out->setProcessingStatus($line["processing_status"]);
			$out->setCreationDate($line["creation_date"]);
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
		$query = "SELECT _id, webhookid, processing_status, started_date, ended_date, message FROM billing_webhook_logs WHERE _id = $1";

		$result = pg_query_params(config::getDbConn(), $query, array($id));

		$out = null;

		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new BillingsWebHookLog();
			$out->setId($line["_id"]);
			$out->setWebHookId($line["webhookid"]);
			$out->setProcessingStatus($line["processing_status"]);
			$out->setStartedDate($line["started_date"]);
			$out->setEndedDate($line["ended_date"]);
			$out->setMessage($line["message"]);
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

?>