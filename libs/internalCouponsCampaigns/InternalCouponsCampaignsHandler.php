<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../providers/recurly/couponsCampaigns/RecurlyCouponsCampaignsHandler.php';

class InternalCouponsCampaignsHandler {
	
	public function __construct() {
	}
	
	public function doGetInternalCouponsCampaigns($couponsCampaignType = NULL) {
		$db_internal_coupons_campaigns = NULL;
		try {
			config::getLogger()->addInfo("internalCouponsCampaigns getting...");
			$db_internal_coupons_campaigns = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaigns($couponsCampaignType);
			config::getLogger()->addInfo("internalCouponsCampaigns getting done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting internalCouponsCampaigns, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCouponsCampaigns getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting internalCouponsCampaigns, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCouponsCampaigns getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_coupons_campaigns);
	}
	
	public function doGetInternalCouponsCampaign($couponsCampaignInternalBillingUuid) {
		$db_internal_coupons_campaign = NULL;
		try {
			config::getLogger()->addInfo("internalCouponsCampaign getting, couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid."....");
			//
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($couponsCampaignInternalBillingUuid);
			//
			config::getLogger()->addInfo("internalCouponsCampaign getting, couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting an internalCouponsCampaign for couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCouponsCampaign getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an internalCouponsCampaign for couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCouponsCampaign getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_coupons_campaign);
	}
	
	public function doAddToProvider($couponsCampaignInternalBillingUuid, Provider $provider) {
		$db_internal_coupons_campaign = NULL;
		try {
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($couponsCampaignInternalBillingUuid);
			if($db_internal_coupons_campaign == NULL) {
				$msg = "unknown couponsCampaignInternalBillingUuid : ".$couponsCampaignInternalBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//linked ?
			$billingProviderCouponsCampaigns = BillingProviderCouponsCampaignDAO::getBillingProviderCouponsCampaignsByInternalCouponsCampaignsId($db_internal_coupons_campaign->getId());
			foreach ($billingProviderCouponsCampaigns as $billingProviderCouponsCampaign) {
 				if($billingProviderCouponsCampaign->getProviderId() == $provider->getId()) {
 					$msg = "internalCouponsCampaign with couponsCampaignInternalBillingUuid : ".$couponsCampaignInternalBillingUuid." is already linked to provider : ".$provider->getName();
 					config::getLogger()->addError($msg);
 					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
 				}
			}
			//already exist ?
			//TODO : ???
			//create provider side
			$couponsCampaignProviderBillingUuid = NULL;
			switch($provider->getName()) {
				case 'afr' :
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
				case 'recurly' :
					$recurlyCouponsCampaignsHandler = new RecurlyCouponsCampaignsHandler();
					$couponsCampaignProviderBillingUuid = $recurlyCouponsCampaignsHandler->createProviderCouponsCampaign($db_internal_coupons_campaign);
					break;
				case 'stripe':
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
				default:
					$msg = "unsupported feature for provider named : ".$provider->getName();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			//create it in DB
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				$billingProviderCouponsCampaign = new BillingProviderCouponsCampaign();
				$billingProviderCouponsCampaign->setProviderId($provider->getId());
				$billingProviderCouponsCampaign->setInternalCouponsCampaignsId($db_internal_coupons_campaign->getId());
				$billingProviderCouponsCampaign->setUuid($couponsCampaignProviderBillingUuid);
				$billingProviderCouponsCampaign->setPrefix($db_internal_coupons_campaign->getPrefix());
				$billingProviderCouponsCampaign = BillingProviderCouponsCampaignDAO::addBillingProviderCouponsCampaign($billingProviderCouponsCampaign);
				//done
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($couponsCampaignInternalBillingUuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while adding provider : ".$provider->getName()." to internalCouponsCampaign with couponsCampaignInternalBillingUuid : ".$couponsCampaignInternalBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding a provider to an internalCouponsCampaign failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while adding provider : ".$provider->getName()." to internalCouponsCampaign with couponsCampaignInternalBillingUuid : ".$couponsCampaignInternalBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding a provider to an internalCouponsCampaign failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_coupons_campaign);
	}
	
}

?>