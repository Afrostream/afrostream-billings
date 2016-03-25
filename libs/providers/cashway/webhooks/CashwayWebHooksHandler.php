<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../subscriptions/CashwaySubscriptionsHandler.php';

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
		switch($data['event']) {
			case 'transaction_paid' :
				config::getLogger()->addInfo('Processing cashway hook notification...event='.$data['event'].'...');
				$coupon = CouponDAO::getCouponByCouponBillingUuid($data['order_id']);
				if($coupon == NULL) {
					$msg = "no coupon found with coupon_billing_uuid=".$data['order_id'];
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);					
				}
				$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById($coupon->getSubId());
				if($db_subscription == NULL) {
					//Exception
				}
				$provider = ProviderDAO::getProviderById($db_subscription->getProviderId());
				if($provider == NULL) {
					//exception
				}
				$user = UserDAO::getUserById($db_subscription->getUserId());
				if($user == NULL) {
					//exception
				}
				$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
				$cashwaySubscriptionsHandler = new CashwaySubscriptionsHandler();
				$db_subscription_before_update = clone $db_subscription;
				$api_subscription = $db_subscription;
				if($coupon->getStatus() == 'waiting') {					
					try {
						$now = new DateTime();
						//START TRANSACTION
						pg_query("BEGIN");
						$api_subscription->setSubStatus('active');
						$api_subscription->setSubPeriodStartedDate($now);
						$db_subscription = $cashwaySubscriptionsHandler->updateDbSubscriptionFromApiSubscription($user, $userOpts, $provider, $internalPlan, $internalPlanOpts, $plan, $planOpts, $api_subscription, $db_subscription_before_update, $update_type, $updateId);
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
				if($coupon->getStatus() == 'waiting') {
					//TODO : may expire the subscription
					try {
						//START TRANSACTION
						pg_query("BEGIN");
						$coupon->setStatus("expired");
						$coupon = CouponDAO::updateStatus($coupon);
						$coupon->setExpiresDate(new DateTime());
						$coupon = CouponDAO::updateExpiresDate($coupon);
						//COMMIT
						pg_query("COMMIT");
					} catch(Exception $e) {
						pg_query("ROLLBACK");
						throw $e;
					}
				}
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