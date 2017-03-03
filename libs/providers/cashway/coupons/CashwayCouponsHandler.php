<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../client/cashway_lib.php';
require_once __DIR__ . '/../../global/coupons/ProviderCouponsHandler.php';

use CashWay\API;

class CashwayCouponsHandler extends ProviderCouponsHandler {
	
	public function doCreateCoupon(User $user, 
			UserOpts $userOpts, 
			BillingInternalCouponsCampaign $internalCouponsCampaign, 
			BillingProviderCouponsCampaign $providerCouponsCampaign,
			InternalPlan $internalPlan = NULL, 
			$coupon_billing_uuid, 
			BillingsCouponsOpts $billingCouponsOpts) {
		$coupon_provider_uuid = NULL;
		try {
			config::getLogger()->addInfo("cashway coupon creation...");
			//
			//TODO : should check internalCouponsCampaign compatibility
			//
			if(getEnv('CASHWAY_COUPON_ONE_BY_USER_FOR_EACH_CAMPAIGN_ACTIVATED') == 1) {
				$couponsByUserForOneCampaign = BillingUserInternalCouponDAO::getBillingUserInternalCouponsByUserId($user->getId(), NULL, NULL, $internalCouponsCampaign->getId());
				foreach ($couponsByUserForOneCampaign as $coupon) {
					if($coupon->getStatus() == 'pending') {
						//exception
						$msg = "there's already a coupon waiting for payment";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::CASHWAY_COUPON_ONE_BY_USER_FOR_EACH_CAMPAIGN);						
					}
				}
			}
			//Checking InternalPlan Compatibility
			$billingInternalCouponsCampaignInternalPlans = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlansByInternalCouponsCampaignsId($internalCouponsCampaign->getId());
			if(count($billingInternalCouponsCampaignInternalPlans) == 0) {
				//Exception
				$msg = "no internalPlan associated to internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			} else if(count($billingInternalCouponsCampaignInternalPlans) == 1) {
				if($internalPlan == NULL) {
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
				$msg = "given internalPlan with uuid=".$internalPlan->getInternalPlanUuid()." is not associated to internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			$conf = array (
					'API_KEY'		=> $this->provider->getApiKey(),
					'API_SECRET'	=> $this->provider->getApiSecret(),
					'USER_AGENT' 	=> getEnv('CASHWAY_USER_AGENT'),
					'USE_STAGING'	=> getEnv('CASHWAY_USE_STAGING') == 1 ? true : false
			);
			$cashwayApi = new API($conf);
			$cashwayApi->order = array (
					'id' => $coupon_billing_uuid,
					'description' => $internalPlan->getName(),
					'at' => dbGlobal::toISODate(new DateTime()),
					'currency' => $internalPlan->getCurrency(),
					'total' =>  (string) number_format($internalPlan->getAmount(), 2, '.', '')
			);
			$cashwayApi->customer = array(
					'id' => $user->getUserProviderUuid(),
					'email' => $userOpts->getOpts()['email']
			);
			$result = $cashwayApi->openTransaction(true);
			if(!is_array($result)) {
				//exception
				$msg = "result cannot be recognized";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(array_key_exists('errors', $result)) {
				//exception
				$msg = "CASHWAY error, details=".$result['errors'][0]['code'].' '.$result['errors'][0]['status'];
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);				
			}
			if(!array_key_exists('status', $result)) {
				//exception
				$msg = "status field not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists('barcode', $result)) {
				//exception
				$msg = "barcode field not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists('expires_at', $result)) {
				//exception
				$msg = "expires_at field not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($result['status'] != 'confirmed') {
				//exception
				$msg = "status not confirmed (".$result['status'].")";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);				
			}
			//
			$expires_date_str = $result['expires_at'];
			//http://stackoverflow.com/questions/4411340/php-datetimecreatefromformat-doesnt-parse-iso-8601-date-time
			//https://bugs.php.net/bug.php?id=51950
			$expires_date = DateTime::createFromFormat('Y-m-d\TH:i:s.uO', $expires_date_str);
			if($expires_date === false) {
				$msg = "expires_at date : ".$expires_date_str." cannot be parsed";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$coupon_provider_uuid = $result['barcode'];
			//<-- DB -->
			//Create an internalCoupon
			$internalCoupon = new BillingInternalCoupon();
			$internalCoupon->setInternalCouponsCampaignsId($internalCouponsCampaign->getId());
			$internalCoupon->setCode($coupon_provider_uuid);
			$internalCoupon->setUuid($coupon_billing_uuid);
			$internalCoupon->setExpiresDate($expires_date);
			$internalCoupon = BillingInternalCouponDAO::addBillingInternalCoupon($internalCoupon);
			//Create an userCoupon linked to the internalCoupon
			$userInternalCoupon = new BillingUserInternalCoupon();
			$userInternalCoupon->setInternalCouponsId($internalCoupon->getId());
			$userInternalCoupon->setCode($coupon_provider_uuid);
			$userInternalCoupon->setUuid($coupon_billing_uuid);
			$userInternalCoupon->setUserId($user->getId());
			$userInternalCoupon->setExpiresDate($expires_date);
			$userInternalCoupon = BillingUserInternalCouponDAO::addBillingUserInternalCoupon($userInternalCoupon);
			//<-- DB -->
			config::getLogger()->addInfo("cashway coupon creation done successfully, coupon_provider_uuid=".$coupon_provider_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a cashway coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway coupon creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a cashway coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway coupon creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($coupon_provider_uuid);
	}
	
	public function createDbCouponFromApiCouponUuid(User $user, 
			UserOpts $userOpts, 
			BillingInternalCouponsCampaign $internalCouponsCampaign, 
			BillingProviderCouponsCampaign $providerCouponsCampaign, 
			InternalPlan $internalPlan = NULL, 
			$coupon_billing_uuid, 
			$coupon_provider_uuid) {
		//LATER : cashway do not allow yet to have a status from an unique transaction
		return(BillingUserInternalCouponDAO::getBillingUserInternalCouponByCouponBillingUuid($coupon_billing_uuid));
	}
	
}

?>