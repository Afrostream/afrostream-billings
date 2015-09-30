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
	
	public static function getUserById($id) {
		$query = "SELECT _id, email, billing_provider, account_code, active FROM \"Users\" WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new User();
			$out->setId($line["_id"]);
			$out->setEmail($line["email"]);
			$out->setBillingProvider($line["billing_provider"]);
			$out->setAccountCode($line["account_code"]);
			$out->setActive($line["active"]);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
	public static function getUserByAccountCode($account_code) {
		$query = "SELECT _id, email, billing_provider, account_code, active FROM \"Users\" WHERE account_code = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($account_code));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new User();
			$out->setId($line["_id"]);
			$out->setEmail($line["email"]);
			$out->setBillingProvider($line["billing_provider"]);
			$out->setAccountCode($line["account_code"]);
			$out->setActive($line["active"]);
		}
		// free result
		pg_free_result($result);
		
		return($out);
	}
	
}

class User {
	
	private $_id;
	private $email;
	private $billing_provider;
	private $account_code;
	private $active;
	
	public function getId() {
		return($this->_id);
	}
	
	public function setId($id) {
		$this->_id = $id;
	}
	
	public function getEmail() {
		return($this->email);
	}
	
	public function setEmail($email) {
		$this->email = $email;
	}
	
	public function getBillingProvider() {
		return($this->billing_provider);
	}
	
	public function setBillingProvider($billing_provider) {
		$this->billing_provider = $billing_provider;
	}
	
	public function getAccountCode() {
		return($this->account_code);
	}
	
	public function setAccountCode($account_code) {
		$this->account_code = $account_code;
	}
	
	public function getActive() {
		return($this->active);
	}
	
	public function setActive($active) {
		$this->$active = $active;
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

class SubscriptionDAO {
	
	public static function getSubscriptionById($id) {
		$query = "SELECT _id, providerid, userid, planid, creation_date, updated_date, sub_uuid, sub_status,";
		$query.= " sub_activated_date, sub_canceled_date, sub_expires_date, sub_period_started_date, sub_period_ends_date,";
		$query.= " sub_collection_mode, update_type, updateid, deleted";
		$query.= " FROM billing_subscriptions WHERE _id = $1";
		$result = pg_query_params(config::getDbConn(), $query, array($id));
	
		$out = null;
	
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new Subscription();
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
	
	public static function getSubscriptionBySubUuid($providerId, $sub_uuid) {
		$query = "SELECT _id, providerid, userid, planid, creation_date, updated_date, sub_uuid, sub_status,";
		$query.= " sub_activated_date, sub_canceled_date, sub_expires_date, sub_period_started_date, sub_period_ends_date,";
		$query.= " sub_collection_mode, update_type, updateid, deleted";
		$query.= " FROM billing_subscriptions WHERE providerid = $1 AND sub_uuid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($providerId, $sub_uuid));
		
		$out = null;
		
		if ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$out = new Subscription();
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
	
	public static function addSubscription(Subscription $subscription) {
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
		return(self::getSubscriptionById($row[0]));
	}
	
	public static function updateSubscription(Subscription $subscription) {
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
		return(self::getSubscriptionById($row[0]));
	}
	
	public static function getSubscriptionByUserId($providerId, $userId) {
		$query = "SELECT _id, providerid, userid, planid, creation_date, updated_date, sub_uuid, sub_status,";
		$query.= " sub_activated_date, sub_canceled_date, sub_expires_date, sub_period_started_date, sub_period_ends_date,";
		$query.= " sub_collection_mode, update_type, updateid, deleted";
		$query.= " FROM billing_subscriptions WHERE providerid = $1 AND userid = $2";
		$result = pg_query_params(config::getDbConn(), $query, array($providerId, $userId));
		
		$out = array();
		
		while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$val = new Subscription();
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
}

class Subscription {
	
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

?>