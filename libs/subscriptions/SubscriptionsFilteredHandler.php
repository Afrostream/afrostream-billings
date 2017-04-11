<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/SubscriptionsHandler.php';
require_once __DIR__ . '/../usersInternalCoupons/UsersInternalCouponsHandler.php';

class SubscriptionsFilteredHandler extends SubscriptionsHandler {
	
	public function __construct() {
		parent::__construct();
	}
	
	public function doGetOrCreateSubscription(GetOrCreateSubscriptionRequest $getOrCreateSubscriptionRequest) {
		$user = UserDAO::getUserByUserBillingUuid($getOrCreateSubscriptionRequest->getUserBillingUuid(), $getOrCreateSubscriptionRequest->getPlatform()->getId());
		if($user == NULL) {
			$msg = "unknown userBillingUuid : ".$getOrCreateSubscriptionRequest->getUserBillingUuid();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$getSubscriptionsRequest = new GetSubscriptionsRequest();
		$getSubscriptionsRequest->setOrigin($getOrCreateSubscriptionRequest->getOrigin());
		$getSubscriptionsRequest->setClientId($getOrCreateSubscriptionRequest->getClientId());
		$getSubscriptionsRequest->setPlatform($getOrCreateSubscriptionRequest->getPlatform());
		$getSubscriptionsRequest->setUserReferenceUuid($user->getUserReferenceUuid());
		$subscriptions = parent::doGetUserSubscriptionsByUserReferenceUuid($getSubscriptionsRequest);
		if(count($subscriptions) > 0) {
			//HACK / FIX : Remove check because of CASHWAY
			/*if($this->haveSubscriptionsWithStatus($subscriptions, 'future')) {
				$msg = "you already have a future subscription";
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_FUTURE_ALREADY_EXISTS);				
			}*/
			$lastSubscription = $subscriptions[0];
			if($lastSubscription->getIsActive() == 'yes') {
				//NC : CAN BLOCK NOW  (WAS DO NOT BLOCK UNTIL WE REALLY KNOW THAT SUBSCRIPTIONS AUTO-RENEW OR NOT (VERY SOON !!!))
				$internalPlan = InternalPlanDAO::getInternalPlanByProviderPlanId($lastSubscription->getPlanId());
				if($internalPlan->getCycle() == PlanCycle::auto) {
					$msg = "you already have an active subscription that's auto renew, you can't take a new subscription";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_AUTO_ALREADY_EXISTS);
				} else {
					$msg = "you already have an active subscription, you can't take a new subscription";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::SUBS_ALREADY_EXISTS);					
				}
			}
			$now = new DateTime();
			$lastDate = NULL;
			if($lastSubscription->getSubStatus() == 'expired') {
				$lastDate = $lastSubscription->getSubExpiresDate();
			} else {
				$lastDate = $lastSubscription->getSubPeriodEndsDate();
			}
			if(isset($lastDate)) {
				if($lastDate < $now) {
					$lastDate = NULL;//lastDate is in the PAST => NOT NEEDED
				}
			}
			if(isset($lastDate)) {
				$sub_opts_array['startsAt'] = dbGlobal::toISODate($lastDate);
			}
		}
		return(parent::doGetOrCreateSubscription($getOrCreateSubscriptionRequest));
	}
	
	private function haveSubscriptionsWithStatus(array $subscriptions, $status) {
		foreach ($subscriptions as $subscription) {
			if($subscription->getSubStatus() == $status) {
				return(true);
			}
		}
		return(false);
	}
	
	/*public function doGetUserSubscriptionsByUser(GetUserSubscriptionsRequest $getUserSubscriptionsRequest) {
		$user = UserDAO::getUserByUserBillingUuid($getUserSubscriptionsRequest->getUserBillingUuid(), $getUserSubscriptionsRequest->getPlatform()->getId());
		if($user == NULL) {
			$msg = "unknown userBillingUuid : ".$getUserSubscriptionsRequest->getUserBillingUuid();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$provider = ProviderDAO::getProviderById($user->getProviderId());
		if($provider->getName() == 'afr') {
			$getSubscriptionsRequest = new GetSubscriptionsRequest();
			$getSubscriptionsRequest->setOrigin($getUserSubscriptionsRequest->getOrigin());
			$getSubscriptionsRequest->setClientId($getUserSubscriptionsRequest->getClientId());
			$getSubscriptionsRequest->setPlatform($getUserSubscriptionsRequest->getPlatform());
			$getSubscriptionsRequest->setUserReferenceUuid($user->getUserReferenceUuid());
			$subscriptions = $this->doGetUserSubscriptionsByUserReferenceUuid($getSubscriptionsRequest);
			if(count($subscriptions) == 0) {
				//NEVER SUBSCRIBED
				//CREATE COUPON
				$usersInternalCouponsHandler = new UsersInternalCouponsHandler();
				$createUsersInternalCouponRequest = new CreateUsersInternalCouponRequest();
				$createUsersInternalCouponRequest->setOrigin($getUserSubscriptionsRequest->getOrigin());
				$createUsersInternalCouponRequest->setPlatform($getUserSubscriptionsRequest->getPlatform());
				$createUsersInternalCouponRequest->setUserBillingUuid($user->getUserBillingUuid());
				$createUsersInternalCouponRequest->setInternalCouponsCampaignBillingUuid('e40028fa-ab18-4ec8-a573-b396c3165758');
				$createUsersInternalCouponRequest->setInternalPlanUuid(NULL);
				$db_coupon = $usersInternalCouponsHandler->doCreateCoupon($createUsersInternalCouponRequest);
				//USE COUPON
				$getOrCreateSubscriptionRequest = new GetOrCreateSubscriptionRequest();
				$getOrCreateSubscriptionRequest->setOrigin($getUserSubscriptionsRequest->getOrigin());
				$getOrCreateSubscriptionRequest->setPlatform($getUserSubscriptionsRequest->getPlatform());
				$getOrCreateSubscriptionRequest->setClientId($getUserSubscriptionsRequest->getClientId());
				$getOrCreateSubscriptionRequest->setUserBillingUuid($user->getUserBillingUuid());
				$getOrCreateSubscriptionRequest->setInternalPlanUuid('bonus');
				$getOrCreateSubscriptionRequest->setSubOptsArray(['couponCode' => $db_coupon->getCode()]);
				$this->doGetOrCreateSubscription($getOrCreateSubscriptionRequest);
			}
		}
		return(parent::doGetUserSubscriptionsByUser($getUserSubscriptionsRequest));
	}*/
	
	public function doGetUserSubscriptionsByUserReferenceUuid(GetSubscriptionsRequest $getSubscriptionsRequest) {
		$subscriptions = parent::doGetUserSubscriptionsByUserReferenceUuid($getSubscriptionsRequest);
		$clientId = $getSubscriptionsRequest->getClientId();
		//if($clientId == 'blabla') {
			if(count($subscriptions) == 0) {
				//NEVER SUBSCRIBED
				$provider = ProviderDAO::getProviderByName('afr', $getSubscriptionsRequest->getPlatform()->getId());
				$user = UserDAO::getUsersByUserReferenceUuid($getSubscriptionsRequest->getUserReferenceUuid(), $provider->getId(), $getSubscriptionsRequest->getPlatform()->getId());
				if($user != NULL) {
					//CREATE COUPON
					$usersInternalCouponsHandler = new UsersInternalCouponsHandler();
					$createUsersInternalCouponRequest = new CreateUsersInternalCouponRequest();
					$createUsersInternalCouponRequest->setOrigin($getSubscriptionsRequest->getOrigin());
					$createUsersInternalCouponRequest->setPlatform($getSubscriptionsRequest->getPlatform());
					$createUsersInternalCouponRequest->setUserBillingUuid($user->getUserBillingUuid());
					$createUsersInternalCouponRequest->setInternalCouponsCampaignBillingUuid('e40028fa-ab18-4ec8-a573-b396c3165758');
					$createUsersInternalCouponRequest->setInternalPlanUuid(NULL);
					$db_coupon = $usersInternalCouponsHandler->doCreateCoupon($createUsersInternalCouponRequest);
					//USE COUPON
					$getOrCreateSubscriptionRequest = new GetOrCreateSubscriptionRequest();
					$getOrCreateSubscriptionRequest->setOrigin($getSubscriptionsRequest->getOrigin());
					$getOrCreateSubscriptionRequest->setPlatform($getSubscriptionsRequest->getPlatform());
					$getOrCreateSubscriptionRequest->setClientId($getSubscriptionsRequest->getClientId());
					$getOrCreateSubscriptionRequest->setUserBillingUuid($user->getUserBillingUuid());
					$getOrCreateSubscriptionRequest->setInternalPlanUuid('bonus');
					$getOrCreateSubscriptionRequest->setSubOptsArray(['couponCode' => $db_coupon->getCode()]);
					$this->doGetOrCreateSubscription($getOrCreateSubscriptionRequest);
					$subscriptions = parent::doGetUserSubscriptionsByUserReferenceUuid($getSubscriptionsRequest);
				}
			}
		//}
		return($subscriptions);
	}
}
?>