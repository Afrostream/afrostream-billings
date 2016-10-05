<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../client/cashway_lib.php';

use CashWay\API;

class CashwayCouponsHandler {
	
	public function __construct() {
	}
		
	public function doCreateCoupon(User $user, UserOpts $userOpts, CouponsCampaign $couponsCampaign, $coupon_billing_uuid, BillingsCouponsOpts $billingCouponsOpts) {
		try {
			config::getLogger()->addInfo("cashway coupon creation...");
			//
			if(getEnv('CASHWAY_COUPON_ONE_BY_USER_FOR_EACH_CAMPAIGN_ACTIVATED') == 1) {
				$couponsByUserForOneCampaign = CouponDAO::getCouponsByUserId($user->getId(), $couponsCampaign->getId());
				foreach ($couponsByUserForOneCampaign as $coupon) {
					if($coupon->getStatus() == 'pending') {
						//exception
						$msg = "there's already a coupon waiting for payment";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::CASHWAY_COUPON_ONE_BY_USER_FOR_EACH_CAMPAIGN);						
					}
				}
			}
			$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($couponsCampaign->getProviderPlanId()));
			//
			$conf = array (
					'API_KEY'		=> getEnv('CASHWAY_API_HTTP_AUTH_USER'),
					'API_SECRET'	=> getEnv('CASHWAY_API_HTTP_AUTH_PWD'),
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
			//
			$coupon = new Coupon();
			$coupon->setCouponBillingUuid($coupon_billing_uuid);
			$coupon->setCouponsCampaignId($couponsCampaign->getId());
			$coupon->setProviderId($couponsCampaign->getProviderId());
			$coupon->setProviderPlanId($couponsCampaign->getProviderPlanId());
			$coupon->setCode($coupon_provider_uuid);
			$coupon->setExpiresDate($expires_date);
			$coupon->setUserId($user->getId());
			CouponDAO::addCoupon($coupon);
			//
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
	
	public function createDbCouponFromApiCouponUuid(User $user,  UserOpts $userOpts, CouponsCampaign $couponsCampaign, $coupon_billing_uuid, $coupon_provider_uuid) {
		//LATER : cashway do not allow yet to have a status from an unique transaction
		return(CouponDAO::getCouponByCouponBillingUuid($coupon_billing_uuid));
	}
	
	/*public function doGetCoupon(User $user = NULL, UserOpts $userOpts = NULL, $couponCode) {
		$db_coupon = NULL;
		try {
			if($user == NULL) {
				$msg = "unsupported feature for provider named : cashway, user has to be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//provider
			$provider = ProviderDAO::getProviderByName('cashway');
			if($provider == NULL) {
				$msg = "provider named 'cashway' not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_coupon = CouponDAO::getCoupon($provider->getId(), $couponCode, $user->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting a cashway coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway coupon getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a cashway coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("cashway coupon getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($db_coupon);
	}*/
	
}

?>