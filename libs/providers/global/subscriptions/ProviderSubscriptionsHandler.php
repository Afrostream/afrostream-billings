<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../ProviderHandlersBuilder.php';
require_once __DIR__ . '/../requests/ExpireSubscriptionRequest.php';

class ProviderSubscriptionsHandler {
	
	private $provider = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
	}
	
	public function doGetUserSubscriptions(User $user) {
		$subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		$subscriptions = $this->doFillSubscriptions($subscriptions);
		return($subscriptions);
	}
	
	protected function doFillSubscription(BillingsSubscription $subscription = NULL) {
		if($subscription == NULL) {
			return NULL;
		}
		//--> DEFAULT
		// check if subscription still in trial to provide information in boolean mode through inTrial() method
		$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($subscription->getPlanId()));
	
		if ($internalPlan->getTrialEnabled() && !is_null($subscription->getSubActivatedDate())) {
	
			$subscriptionDate = clone $subscription->getSubActivatedDate();
			$subscriptionDate->modify('+ '.$internalPlan->getTrialPeriodLength().' '.$internalPlan->getTrialPeriodUnit());
	
			$subscription->setInTrial(($subscriptionDate->getTimestamp() > time()));
		}
	
		// set cancelable status regarding cycle on internal plan
		if ($internalPlan->getCycle()->getValue() === PlanCycle::once) {
			$subscription->setIsCancelable(false);
		} else {
			$array_status_cancelable = ['future', 'active'];
			if(in_array($subscription->getSubStatus() , $array_status_cancelable)) {
				$subscription->setIsCancelable(true);
			} else {
				$subscription->setIsCancelable(false);
			}
		}
		//<-- DEFAULT
		//--> SPECIFIC
		/*$provider = ProviderDAO::getProviderById($subscription->getProviderId());
		if($provider == NULL) {
			$msg = "unknown provider with id : ".$subscription->getProviderId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		ProviderHandlersBuilder::getProviderSubscriptionsHandlerInstance($provider->getName())->doFillSubscription($subscription);*/
		//<-- SPECIFIC
		return($subscription);
	}
	
	protected function doFillSubscriptions($subscriptions) {
		foreach ($subscriptions as $subscription) {
			$this->doFillSubscription($subscription);
		}
		return($subscriptions);
	}

	protected function getCouponInfos($couponCode, Provider $provider, User $user, InternalPlan $internalPlan) {
		//
		$out = array();
		$internalCoupon = NULL;
		$internalCouponsCampaign = NULL;
		$providerCouponsCampaign = NULL;
		$userInternalCoupon = NULL;
		//
		$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponByCode($couponCode);
		if($internalCoupon == NULL) {
			$msg = "coupon : code=".$couponCode." NOT FOUND";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_CODE_NOT_FOUND);
		}
		//Check internalCoupon
		if($internalCoupon->getStatus() == 'redeemed') {
			$msg = "coupon : code=".$couponCode." already redeemed";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_REDEEMED);
		}
		if($internalCoupon->getStatus() == 'expired') {
			$msg = "coupon : code=".$couponCode." expired";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_EXPIRED);
		}
		if($internalCoupon->getStatus() == 'pending') {
			$msg = "coupon : code=".$couponCode." pending";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_PENDING);
		}
		if($internalCoupon->getStatus() != 'waiting') {
			$msg = "coupon : code=".$couponCode." cannot be used";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_NOT_READY);
		}
		//
		$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($internalCoupon->getInternalCouponsCampaignsId());
		if($internalCouponsCampaign == NULL) {
			$msg = "unknown internalCouponsCampaign with id : ".$internalCoupon->getInternalCouponsCampaignsId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//Check compatibility
		$isProviderCompatible = false;
		$providerCouponsCampaigns = BillingProviderCouponsCampaignDAO::getBillingProviderCouponsCampaignsByInternalCouponsCampaignsId($internalCouponsCampaign->getId());
		foreach ($providerCouponsCampaigns as $currentProviderCouponsCampaign) {
			if($currentProviderCouponsCampaign->getProviderId() == $provider->getId()) {
				$providerCouponsCampaign = $currentProviderCouponsCampaign;
				$isProviderCompatible = true;
				break;
			}
		}
		if($isProviderCompatible == false) {
			//Exception
			$msg = "internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid()." is not associated with provider : ".$provider->getName();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_PROVIDER_INCOMPATIBLE);
		}
		$billingInternalCouponsCampaignInternalPlans = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlansByInternalCouponsCampaignsId($internalCouponsCampaign->getId());
		$isInternalPlanCompatible = false;
		foreach($billingInternalCouponsCampaignInternalPlans as $billingInternalCouponsCampaignInternalPlan) {
			if($billingInternalCouponsCampaignInternalPlan->getInternalPlanId() == $internalPlan->getId()) {
				$isInternalPlanCompatible = true; break;
			}
		}
		if($isInternalPlanCompatible == false) {
			//Exception
			$msg = "internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid()." is not associated with internalPlan : ".$internalPlan->getInternalPlanUuid();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_INTERNALPLAN_INCOMPATIBLE);
		}
		//
		$userInternalCoupons = BillingUserInternalCouponDAO::getBillingUserInternalCouponsByUserId($user->getId(), $internalCoupon->getId());
		if(count($userInternalCoupons) > 0) {
			//TAKING FIRST (EQUALS LAST GENERATED)
			$userInternalCoupon = $userInternalCoupons[0];
		} else {
			$userInternalCoupon = new BillingUserInternalCoupon();
			$userInternalCoupon->setInternalCouponsId($internalCoupon->getId());
			$userInternalCoupon->setCode($internalCoupon->getCode());
			$userInternalCoupon->setUuid(guid());
			$userInternalCoupon->setUserId($user->getId());
			$userInternalCoupon->setExpiresDate($internalCoupon->getExpiresDate());
			$userInternalCoupon = BillingUserInternalCouponDAO::addBillingUserInternalCoupon($userInternalCoupon);
		}
		//Check userInternalCoupon
		if($userInternalCoupon->getStatus() == 'redeemed') {
			$msg = "coupon : code=".$couponCode." already redeemed";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_REDEEMED);
		}
		if($userInternalCoupon->getStatus() == 'expired') {
			$msg = "coupon : code=".$couponCode." expired";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_EXPIRED);
		}
		if($userInternalCoupon->getStatus() == 'pending') {
			$msg = "coupon : code=".$couponCode." pending";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_PENDING);
		}
		if($userInternalCoupon->getStatus() != 'waiting') {
			$msg = "coupon : code=".$couponCode." cannot be used";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_NOT_READY);
		}
		if($userInternalCoupon->getSubId() != NULL) {
			$msg = "coupon : code=".$couponCode." is already linked to another subscription";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_ALREADY_LINKED);
		}
		$out['internalCoupon'] = $internalCoupon;
		$out['internalCouponsCampaign'] = $internalCouponsCampaign;
		$out['providerCouponsCampaign'] = $providerCouponsCampaign;
		$out['userInternalCoupon'] = $userInternalCoupon;
		return($out);
	}
	
	public function doExpireSubscription(BillingsSubscription $subscription, ExpireSubscriptionRequest $expireSubscriptionRequest) {
		$msg = "unsupported feature for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
	}
	
}

?>