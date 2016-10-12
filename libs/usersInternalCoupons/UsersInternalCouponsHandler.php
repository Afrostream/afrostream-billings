<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../providers/cashway/coupons/CashwayCouponsHandler.php';

class UsersInternalCouponsHandler {
	
	public function __construct() {
	}
	
	public function doGetList($userBillingUuid, $internalCouponCampaignType = null, $couponsCampaignInternalBillingUuid = null)
	{
		$user = UserDAO::getUserByUserBillingUuid($userBillingUuid);
		if($user == NULL) {
			$msg = "unknown user_billing_uuid : ".$userBillingUuid;
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	
		$internalCouponsCampaignBillingId = null;
		if (!is_null($couponsCampaignInternalBillingUuid)) {
			$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($couponsCampaignInternalBillingUuid);
	
			if($internalCouponsCampaign == NULL) {
				$msg = "unknown couponsCampaignInternalBillingUuid : ".$couponsCampaignInternalBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
	
			$internalCouponsCampaignBillingId = $internalCouponsCampaign->getId();
		}
		
		$list = BillingUserInternalCouponDAO::getBillingUserInternalCouponsByUserId($user->getId(), NULL, $internalCouponCampaignType, $internalCouponsCampaignBillingId);
	
		return $list;
	}
	
	public function doCreateCoupon($userBillingUuid, $couponsCampaignInternalBillingUuid, $internalPlanUuid = NULL, array $couponOpts) {
		$db_coupon = NULL;
		try {
			config::getLogger()->addInfo("user coupon creating....");
			//user
			$user = UserDAO::getUserByUserBillingUuid($userBillingUuid);
			if($user == NULL) {
				$msg = "unknown user_billing_uuid : ".$userBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			//
			$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($couponsCampaignInternalBillingUuid);
			if($internalCouponsCampaign == NULL) {
				$msg = "unknown couponsCampaignInternalBillingUuid : ".$couponsCampaignInternalBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$billingInternalCouponsCampaignInternalPlans = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlansByInternalCouponsCampaignsId($internalCouponsCampaign->getId());
			$internalPlan = NULL;
			if(isset($internalPlanUuid)) {
				$internalPlan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid);
				if($internalPlan == NULL) {
					//Exception
					$msg = "no internalPlan found with uuid=".$internalPlanUuid;
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);					
				}
			}
			if($internalPlan == NULL) {
				if(count($billingInternalCouponsCampaignInternalPlans) == 0) {
					//Exception
					$msg = "no internalPlan associated to internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				} else if(count($billingInternalCouponsCampaignInternalPlans) == 1) {
					$internalPlan = InternalPlanDAO::getInternalPlanById($billingInternalCouponsCampaignInternalPlans[0]->getInternalPlanId());
				}
			}
			if($internalPlan == NULL) {
				//Exception
				$msg = "no default internalPlan associated to internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$found = false;
			foreach ($billingInternalCouponsCampaignInternalPlans as $billingInternalCouponsCampaignInternalPlan) {
				if($internalPlan->getId() == $billingInternalCouponsCampaignInternalPlan->getInternalPlanId()) {
					$found = true; break;
				}
			}
			if($found == false) {
				//Exception
				$msg = "internalPlan with uuid=".$internalPlan->getInternalPlanUuid()." is not associated to internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//provider
			$provider = ProviderDAO::getProviderById($user->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider with id : ".$user->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$billingCouponsOpts = new BillingsCouponsOpts($couponOpts);
			//
			//compatiblity
			$isProviderCompatible = false;
			$providerCouponsCampaign = NULL;
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
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			$coupon_billing_uuid = guid();
			$coupon_provider_uuid = NULL;
			switch($provider->getName()) {
				case 'cashway' :
					$cashwayCouponsHandler = new CashwayCouponsHandler();
					$coupon_provider_uuid = $cashwayCouponsHandler->doCreateCoupon($user, $userOpts, $internalCouponsCampaign, $providerCouponsCampaign, $internalPlan, $coupon_billing_uuid, $billingCouponsOpts);
					break;
				case 'afr' :
					$afrCouponHandler = new AfrCouponsHandler();
					$coupon_provider_uuid = $afrCouponHandler->doCreateCoupon($user, $userOpts, $internalCouponsCampaign, $providerCouponsCampaign, $internalPlan, $coupon_billing_uuid, $billingCouponsOpts);
					break;
				default :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			switch ($provider->getName()) {
				case 'cashway' :
					$cashwayCouponsHandler = new CashwayCouponsHandler();
					$db_coupon = $cashwayCouponsHandler->createDbCouponFromApiCouponUuid($user, $userOpts, $internalCouponsCampaign, $providerCouponsCampaign, $internalPlan, $coupon_billing_uuid, $coupon_provider_uuid);
					break;
				case 'afr' :
					$afrCouponHandler = new AfrCouponsHandler();
					$db_coupon = $afrCouponHandler->createDbCouponFromApiCouponUuid($user, $userOpts, $internalCouponsCampaign, $providerCouponsCampaign, $internalPlan, $coupon_billing_uuid, $coupon_provider_uuid);
					break;
				default :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			config::getLogger()->addInfo("user coupon creating done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating an user coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user coupon creating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating an user coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("user coupon creating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_coupon);
	}
	
}

?>