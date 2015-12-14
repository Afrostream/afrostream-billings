<?php

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
		$query = "INSERT INTO billing_users (providerid, user_reference_uuid, user_provider_uuid)";
		$query.= " VALUES ($1, $2, $3) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query, 
				array($user->getProviderId(),
					$user->getUserReferenceUuid(),
					$user->getUserProviderUuid()));
		$row = pg_fetch_row($result);
		return(self::getUserById($row[0]));
	}
	
	public static function getUserById($id) {
		$query = "SELECT _id, providerid, user_reference_uuid, user_provider_uuid FROM billing_users WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new User();
			$out->setId($line["_id"]);
			$out->setProviderId($line["providerid"]);
			$out->setUserReferenceUuid($line["user_reference_uuid"]);
			$out->setUserProviderUuid($line["user_provider_uuid"]);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getUsersByUserReferenceId($user_reference_uuid) {
		$query = "SELECT _id, providerid, user_reference_uuid, user_provider_uuid FROM billing_users WHERE user_reference_uuid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($user_reference_uuid));
	
		$array = array();
	
		while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new User();
			$out->setId($line["_id"]);
			$out->setProviderId($line["providerid"]);
			$out->setUserReferenceUuid($line["user_reference_uuid"]);
			$out->setUserProviderUuid($line["user_provider_uuid"]);
			array_push($array, $out);
		}
		// free result
		pg_free_result($result);
	
		return($array);
	}
}

class User {
	
	private $_id;
	private $providerid;
	private $user_reference_uuid;
	private $user_provider_uuid;
	
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
	
}

class UserOptsDAO {
	
	public static function getUserOptsByUserid($userid) {
		$query = "SELECT _id, userid, key, value FROM billing_users_opts WHERE userid = $1";
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
	
	public static function addUserOpts($user_opts) {
		foreach ($user_opts->getOpts() as $k => $v) {
			$query = "INSERT INTO billing_users_opts (userid, key, value)";
			$query.= " VALUES ($1, $2, $3) RETURNING _id";
			$result = pg_query_params(config::getDbConn(), $query,
					array($user_opts->getUserId(),
							$k,
							$v));
		}
		return(self::getUserOptsByUserid($user_opts->getUserId()));
	}
}

class InternalPlanDAO {
	
	public static function getInternalPlanByUuid($internal_plan_uuid) {
		$query = "SELECT _id, internal_plan_uuid, name, description FROM billing_internal_plans WHERE internal_plan_uuid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($internal_plan_uuid));
	
		$out = null;
	
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new InternalPlan();
			$out->setId($line["_id"]);
			$out->setInternalPlanUid($line["internal_plan_uuid"]);
			$out->setName($line["name"]);
			$out->setDescription($line["description"]);
		}
		// free result
		pg_free_result($result);
	
		return($out);
	}	
}

class InternalPlan {

	private $_id;
	private $internal_plan_uuid;
	private $name;
	private $description;

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

}

class InternalPlanOpts {

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

}

class InternalPlanOptsDAO {

	public static function getInternalPlanOptsByInternalPlanId($internalplanid) {
		$query = "SELECT _id, internalplanid, key, value FROM billing_internal_plans_opts WHERE internalplanid = $1";
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
	
	public static function getInternalPlanLink($internalplanid, $providerid) {
		$query = "SELECT BP._id as billing_plan_id FROM billing_plans BP INNER JOIN billing_internal_plans_links BPL ON (BP._id = BPL.provider_plan_id)";
		$query.= "WHERE BPL.internal_plan_id = $1 AND BP.providerid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($internalplanid, $providerid));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = $line["billing_plan_id"];
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
}

class Plan {
	
	private $_id;
	private $plan_uuid;
	private $name;
	private $description;
	private $providerId;
	
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
		return($this->providerId);
	}
	
	public function setProviderId($providerId) {
		$this->providerId = $providerId;
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
		$query = "SELECT _id, planid, key, value FROM billing_plans_opts WHERE planid = $1";
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

class Provider {
	
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
	
}

class BillingsSubscriptionDAO {
	
	public static function getBillingsSubscriptionById($id) {
		$query = "SELECT _id, providerid, userid, planid, creation_date, updated_date, sub_uuid, sub_status,";
		$query.= " sub_activated_date, sub_canceled_date, sub_expires_date, sub_period_started_date, sub_period_ends_date,";
		$query.= " sub_collection_mode, update_type, updateid, deleted";
		$query.= " FROM billing_subscriptions WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
	
		$out = null;
	
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new BillingsSubscription();
			$out->setId($line["_id"]);
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
	
	public static function getBillingsSubscriptionBySubUuid($providerId, $sub_uuid) {
		$query = "SELECT _id, providerid, userid, planid, creation_date, updated_date, sub_uuid, sub_status,";
		$query.= " sub_activated_date, sub_canceled_date, sub_expires_date, sub_period_started_date, sub_period_ends_date,";
		$query.= " sub_collection_mode, update_type, updateid, deleted";
		$query.= " FROM billing_subscriptions WHERE providerid = $1 AND sub_uuid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($providerId, $sub_uuid));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new BillingsSubscription();
			$out->setId($line["_id"]);
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
	
	public static function addBillingsSubscription(BillingsSubscription $subscription) {
		$query = "INSERT INTO billing_subscriptions (providerid, userid, planid, creation_date,";
		$query.= " updated_date, sub_uuid, sub_status, sub_activated_date, sub_canceled_date, sub_expires_date,";
		$query.= " sub_period_started_date, sub_period_ends_date, sub_collection_mode, update_type, updateid, deleted)";
		$query.= " VALUES ($1, $2, $3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14) RETURNING _id";
		$result = pg_query_params(config::getDbConn(), $query,
				array($subscription->getProviderId(),
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
	
	public static function updateBillingsSubscription(BillingsSubscription $subscription) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, planid = $1, sub_status = $2, sub_activated_date = $3, sub_canceled_date = $4,";
		$query.= " sub_expires_date = $5, sub_period_started_date = $6, sub_period_ends_date = $7, sub_collection_mode = $8, update_type = $9, updateid = $10";
		$query.= " WHERE _id = $11";
		$result = pg_query_params(config::getDbConn(), $query,
				array($subscription->getPlanId(),
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
	}
	
	public static function getBillingsSubscriptionByUserId($userId) {
		$query = "SELECT _id, providerid, userid, planid, creation_date, updated_date, sub_uuid, sub_status,";
		$query.= " sub_activated_date, sub_canceled_date, sub_expires_date, sub_period_started_date, sub_period_ends_date,";
		$query.= " sub_collection_mode, update_type, updateid, deleted";
		$query.= " FROM billing_subscriptions WHERE userid = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($userId));
		
		$out = array();
		
		while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$val = new BillingsSubscription();
			$val->setId($line["_id"]);
			$val->setProviderId($line["providerid"]);
			$val->setUserId($line["userid"]);
			$val->setPlanId($line["planid"]);
			$val->setCreationDate($line["creation_date"]);
			$val->setUpdatedDate($line["updated_date"]);
			$val->setSubUid($line["sub_uuid"]);
			$val->setSubStatus($line["sub_status"]);
			$val->setSubActivatedDate($line["sub_activated_date"]);
			$val->setSubCanceledDate($line["sub_canceled_date"]);
			$val->setSubExpiresDate($line["sub_expires_date"]);
			$val->setSubCollectionMode($line["sub_collection_mode"]);
			$val->setSubPeriodStartedDate($line["sub_period_started_date"]);
			$val->setSubPeriodEndsDate($line["sub_period_ends_date"]);
			$val->setUpdateType($line["update_type"]);
			$val->setUpdateId($line["updateid"]);
			$val->setDeleted($line["deleted"]);
			array_push($out, $val);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function deleteBillingsSubscriptionById($id) {
		$query = "UPDATE billing_subscriptions SET updated_date = CURRENT_TIMESTAMP, deleted = true WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query,
				array($id));
		$row = pg_fetch_row($result);
		return(self::getBillingsSubscriptionById($row[0]));
	}
}

class BillingsSubscription {
	
	private $_id;
	private $providerId;
	private $userId;
	private $planId;
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
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getProviderId() {
		return($this->providerId);
	}
	
	public function setProviderId($providerId) {
		$this->providerId = $providerId;
	}
	
	public function getUserId() {
		return($this->userId);
	}
	
	public function setUserId($userId) {
		$this->userId = $userId;
	}
	
	public function getPlanId() {
		return($this->planId);
	}
	
	public function setPlanId($planId) {
		$this->planId = $planId;
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
		return($this->updateId);
	}
	
	public function setUpdateId($id) {
		$this->updateId = $id;
	}
	
	public function getDeleted() {
		return($this->deleted);
	}
	
	public function setDeleted($bool) {
		$this->deleted = $bool;
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