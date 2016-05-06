<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../subscriptions/CashwaySubscriptionsHandler.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';

class CashwayWebHooksHandler {
	
	public function __construct() {
	}
	
	public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook') {
		try {
			config::getLogger()->addInfo("processing cashway webHook with id=".$billingsWebHook->getId()."...");
			$this->doProcessNotification($billingsWebHook->getPostData(), $update_type, $billingsWebHook->getId());
			config::getLogger()->addInfo("processing cashway webHook with id=".$billingsWebHook->getId()." done successfully");
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing cashway webHook with id=".$billingsWebHook->getId().", message=".$e->getMessage();
			config::getLogger()->addError("processing cashway webHook with id=".$billingsWebHook->getId()." failed : ". $msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	private function doProcessNotification($post_data, $update_type, $updateId) {
		config::getLogger()->addInfo('Processing cashway hook notification...');
		$data = json_decode($post_data, true);
		$db_subscription_before_update = NULL;
		$db_subscription = NULL;
		//TODO : Merge to be done later
		$cashwaySubscriptionsHandler = new CashwaySubscriptionsHandler();
		$subscriptionsHandler = new SubscriptionsHandler();
		switch($data['event']) {
			case 'transaction_paid' :
				config::getLogger()->addInfo('Processing cashway hook notification...event='.$data['event'].'...');
				
				$provider_name = "cashway";
				
				$provider = ProviderDAO::getProviderByName($provider_name);
				if($provider == NULL) {
					$msg = "unknown provider named : ".$provider_name;
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$coupon = CouponDAO::getCouponByCouponBillingUuid($data['order_id']);
				if($coupon == NULL) {
					$msg = "no coupon found with coupon_billing_uuid=".$data['order_id'];
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);					
				}
				$db_subscription = NULL;
				$user = NULL;
				$userOpts = NULL;
				$internalPlan = NULL;
				$internalPlanOpts = NULL;
				$plan = NULL;
				$planOpts = NULL;
				if($coupon->getSubId() != NULL) {
					$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($coupon->getSubId());
					if($db_subscription == NULL) {
						$msg = "subscription with id=".$coupon->getSubId();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					$user = UserDAO::getUserById($db_subscription->getUserId());
					if($user == NULL) {
						$msg = "user with id=".$db_subscription->getUserId();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
					$plan = PlanDAO::getPlanById($db_subscription->getPlanId());
					if($plan == NULL) {
						$msg = "unknown plan with id : ".$db_subscription->getPlanId();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					$planOpts = PlanOptsDAO::getPlanOptsByPlanId($plan->getId());
					$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($plan->getId()));
					if($internalPlan == NULL) {
						$msg = "plan with uuid=".$plan->getPlanUuid()." for provider ".$provider->getName()." is not linked to an internal plan";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
				}
				if($coupon->getStatus() == 'pending') {
					try {
						$now = new DateTime();
						//START TRANSACTION
						pg_query("BEGIN");
						if($db_subscription != NULL) {
							$db_subscription_before_update = clone $db_subscription;
							$api_subscription = $db_subscription;							
							$api_subscription->setSubStatus('active');
							$api_subscription->setSubActivatedDate($now);
							$api_subscription->setSubPeriodStartedDate($now);
							$db_subscription = $cashwaySubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription_before_update, $update_type, $updateId);		
						}
						$coupon->setStatus("redeemed");
						$coupon = CouponDAO::updateStatus($coupon);
						$coupon->setRedeemedDate($now);
						$coupon = CouponDAO::updateRedeemedDate($coupon);
						//COMMIT
						pg_query("COMMIT");
					} catch(Exception $e) {
						pg_query("ROLLBACK");
						throw $e;
					}
				}
				if(isset($db_subscription)) {
					$subscriptionsHandler->doSendSubscriptionEvent($db_subscription_before_update, $db_subscription);
				}
				config::getLogger()->addInfo('Processing cashway hook notification...event='.$data['event'].' done successfully');
				break;
			case 'transaction_expired' :
				config::getLogger()->addInfo('Processing cashway hook notification...event='.$data['event'].'...');
				$coupon = CouponDAO::getCouponByCouponBillingUuid($data['order_id']);
				if($coupon == NULL) {
					$msg = "no coupon found with coupon_billing_uuid=".$data['order_id'];
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($coupon->getStatus() == 'waiting' || $coupon->getStatus() == 'pending') {
					try {
						//START TRANSACTION
						pg_query("BEGIN");
						$coupon->setStatus("expired");
						$coupon = CouponDAO::updateStatus($coupon);
						$coupon->setExpiresDate(new DateTime());
						$coupon = CouponDAO::updateExpiresDate($coupon);
						//
						$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($coupon->getSubId());
						if(isset($db_subscription)) {
							$subscriptionsHandler->doDeleteSubscriptionByUuid($db_subscription->getSubscriptionBillingUuid(), false);
						}
						//COMMIT
						pg_query("COMMIT");
					} catch(Exception $e) {
						pg_query("ROLLBACK");
						throw $e;
					}
				}
				config::getLogger()->addInfo('Processing cashway hook notification...event='.$data['event'].' done successfully');
				break;
			case 'status_check' :
				//nothing to do
				config::getLogger()->addInfo('Processing cashway hook notification...event='.$data['event'].'...');
				config::getLogger()->addInfo('Processing cashway hook notification...event='.$data['event'].' done successfully');
				break;
			default :
				config::getLogger()->addWarning('event : '.$data['event']. ' is not yet implemented');
				break;	
		}
		config::getLogger()->addInfo('Processing cashway hook notification done successfully');
	}
	
}

?>