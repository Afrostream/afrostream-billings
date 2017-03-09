<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../subscriptions/CashwaySubscriptionsHandler.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../../global/requests/DeleteSubscriptionRequest.php';
require_once __DIR__ . '/../../global/webhooks/ProviderWebHooksHandler.php';

class CashwayWebHooksHandler extends ProviderWebHooksHandler {
	
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
				
		//TODO : Merge to be done later
		$cashwaySubscriptionsHandler = new CashwaySubscriptionsHandler($this->provider);
		$subscriptionsHandler = new SubscriptionsHandler();
		switch($data['event']) {
			case 'transaction_paid' :
				config::getLogger()->addInfo('Processing cashway hook notification...event='.$data['event'].'...');
				$userInternalCoupon = BillingUserInternalCouponDAO::getBillingUserInternalCouponByCouponBillingUuid($data['order_id'], $this->provider->getPlatformId());
				if($userInternalCoupon == NULL) {
					$msg = "no user coupon found with coupon_billing_uuid=".$data['order_id'];
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponById($userInternalCoupon->getInternalCouponsId());
				if($internalCoupon == NULL) {
					$msg = "no internal coupon found linked to user coupon with uuid=".$userInternalCoupon->getUuid();
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
				if($userInternalCoupon->getSubId() != NULL) {
					$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($userInternalCoupon->getSubId());
					if($db_subscription == NULL) {
						$msg = "unknown subscription with id=".$userInternalCoupon->getSubId();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					$user = UserDAO::getUserById($db_subscription->getUserId());
					if($user == NULL) {
						$msg = "unknown user with id=".$db_subscription->getUserId();
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
						$msg = "plan with uuid=".$plan->getPlanUuid()." for provider ".$this->provider->getName()." is not linked to an internal plan";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());					
				}
				if($userInternalCoupon->getStatus() == 'pending') {
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
							$db_subscription = $cashwaySubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, $userOpts, $this->provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription_before_update, $update_type, $updateId);
						}
						//userInternalCoupon
						$userInternalCoupon->setStatus("redeemed");
						$userInternalCoupon = BillingUserInternalCouponDAO::updateStatus($userInternalCoupon);
						$userInternalCoupon->setRedeemedDate($now);
						$userInternalCoupon = BillingUserInternalCouponDAO::updateRedeemedDate($userInternalCoupon);
						//internalCoupon
						$internalCoupon->setStatus("redeemed");
						$internalCoupon = BillingInternalCouponDAO::updateStatus($internalCoupon);
						$internalCoupon->setRedeemedDate($now);
						$internalCoupon = BillingInternalCouponDAO::updateRedeemedDate($internalCoupon);
						//COMMIT
						pg_query("COMMIT");
					} catch(Exception $e) {
						pg_query("ROLLBACK");
						throw $e;
					}
				}
				config::getLogger()->addInfo('Processing cashway hook notification...event='.$data['event'].' done successfully');
				break;
			case 'transaction_expired' :
				config::getLogger()->addInfo('Processing cashway hook notification...event='.$data['event'].'...');
				$userInternalCoupon = BillingUserInternalCouponDAO::getBillingUserInternalCouponByCouponBillingUuid($data['order_id'], $this->provider->getPlatformId());
				if($userInternalCoupon == NULL) {
					$msg = "no user coupon found with coupon_billing_uuid=".$data['order_id'];
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponById($userInternalCoupon->getInternalCouponsId());
				if($internalCoupon == NULL) {
					$msg = "no internal coupon found linked to user coupon with uuid=".$userInternalCoupon->getUuid();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($userInternalCoupon->getStatus() == 'waiting' || $userInternalCoupon->getStatus() == 'pending') {
					try {
						$now = new DateTime();
						//START TRANSACTION
						pg_query("BEGIN");
						//userInternalCoupon
						$userInternalCoupon->setStatus("expired");
						$userInternalCoupon = BillingUserInternalCouponDAO::updateStatus($userInternalCoupon);
						$userInternalCoupon->setExpiresDate($now);
						$userInternalCoupon = BillingUserInternalCouponDAO::updateExpiresDate($userInternalCoupon);
						//internalCoupon
						$internalCoupon->setStatus("expired");
						$internalCoupon = BillingInternalCouponDAO::updateStatus($internalCoupon);
						$internalCoupon->setExpiresDate($now);
						$internalCoupon = BillingInternalCouponDAO::updateExpiresDate($internalCoupon);
						//
						$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($userInternalCoupon->getSubId());
						if(isset($db_subscription)) {
							$deleteSubscriptionRequest = new DeleteSubscriptionRequest();
							$deleteSubscriptionRequest->setSubscriptionBillingUuid($db_subscription->getSubscriptionBillingUuid());
							$deleteSubscriptionRequest->setOrigin('hook');
							$subscriptionsHandler->doDeleteSubscription($deleteSubscriptionRequest);
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