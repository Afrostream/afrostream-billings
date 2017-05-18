<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db/dbGlobal.php';
require_once __DIR__ . '/../providers/global/ProviderHandlersBuilder.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponsCampaignRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponsCampaignsRequest.php';
require_once __DIR__ . '/../providers/global/requests/AddProviderToInternalCouponsCampaignRequest.php';
require_once __DIR__ . '/../providers/global/requests/CreateInternalCouponsCampaignRequest.php';
require_once __DIR__ . '/../providers/global/requests/AddInternalPlanToInternalCouponsCampaignRequest.php';
require_once __DIR__ . '/../providers/global/requests/RemoveInternalPlanFromInternalCouponsCampaignRequest.php';

use Money\Currency;

class InternalCouponsCampaignsHandler {
	
	public function __construct() {
	}
	
	public function doGetInternalCouponsCampaigns(GetInternalCouponsCampaignsRequest $getInternalCouponsCampaignsRequest) {
		$couponsCampaignType = $getInternalCouponsCampaignsRequest->getCouponsCampaignType();
		$db_internal_coupons_campaigns = NULL;
		try {
			config::getLogger()->addInfo("internalCouponsCampaigns getting...");
			$db_internal_coupons_campaigns = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaigns($couponsCampaignType, $getInternalCouponsCampaignsRequest->getPlatform()->getId());
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
	
	public function doGetInternalCouponsCampaign(GetInternalCouponsCampaignRequest $getInternalCouponsCampaignRequest) {
		$couponsCampaignInternalBillingUuid = $getInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid();
		$db_internal_coupons_campaign = NULL;
		try {
			config::getLogger()->addInfo("internalCouponsCampaign getting, couponsCampaignInternalBillingUuid=".$couponsCampaignInternalBillingUuid."....");
			//
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($couponsCampaignInternalBillingUuid, $getInternalCouponsCampaignRequest->getPlatform()->getId());
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
	
	public function doAddToProvider(AddProviderToInternalCouponsCampaignRequest $addProviderToInternalCouponsCampaignRequest) {
		$couponsCampaignInternalBillingUuid = $addProviderToInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid();
		$providerName = $addProviderToInternalCouponsCampaignRequest->getProviderName();
		$db_internal_coupons_campaign = NULL;
		try {
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($couponsCampaignInternalBillingUuid, $addProviderToInternalCouponsCampaignRequest->getPlatform()->getId());
			if($db_internal_coupons_campaign == NULL) {
				$msg = "unknown couponsCampaignInternalBillingUuid : ".$couponsCampaignInternalBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$provider = ProviderDAO::getProviderByName($providerName, $addProviderToInternalCouponsCampaignRequest->getPlatform()->getId());
			if($provider == NULL) {
				$msg = "unknown provider named : ".$providerName;
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
			//create provider side
			$providerCouponsCampaignsHandler = ProviderHandlersBuilder::getProviderCouponsCampaignsHandlerInstance($provider);
			$couponsCampaignProviderBillingUuid = $providerCouponsCampaignsHandler->createProviderCouponsCampaign($db_internal_coupons_campaign);
			//create it in DB
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				$billingProviderCouponsCampaign = new BillingProviderCouponsCampaign();
				$billingProviderCouponsCampaign->setProviderId($provider->getId());
				$billingProviderCouponsCampaign->setInternalCouponsCampaignsId($db_internal_coupons_campaign->getId());
				$billingProviderCouponsCampaign->setExternalUuid($couponsCampaignProviderBillingUuid);
				$billingProviderCouponsCampaign = BillingProviderCouponsCampaignDAO::addBillingProviderCouponsCampaign($billingProviderCouponsCampaign);
				//done
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($couponsCampaignInternalBillingUuid, $addProviderToInternalCouponsCampaignRequest->getPlatform()->getId());
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while adding provider : ".$providerName." to internalCouponsCampaign with couponsCampaignInternalBillingUuid : ".$couponsCampaignInternalBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding a provider to an internalCouponsCampaign failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while adding provider : ".$providerName." to internalCouponsCampaign with couponsCampaignInternalBillingUuid : ".$couponsCampaignInternalBillingUuid.", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding a provider to an internalCouponsCampaign failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_coupons_campaign);
	}
	
	public function doAddToInternalPlan(AddInternalPlanToInternalCouponsCampaignRequest $addInternalPlanToInternalCouponsCampaignRequest) {
		//TODO
	}
	
	public function doRemoveFromInternalPlan(RemoveInternalPlanFromInternalCouponsCampaignRequest $removeInternalPlanFromInternalCouponsCampaignRequest) {
		//TODO
	}
	
	public function create(CreateInternalCouponsCampaignRequest $createInternalCouponsCampaignRequest) {
		// Parameters Verifications...
		if(strlen($createInternalCouponsCampaignRequest->getName()) == 0) {
			//exception
			$msg = "name parameter cannot be empty";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if(strlen($createInternalCouponsCampaignRequest->getDescription()) == 0) {
			//exception
			$msg = "description parameter cannot be empty";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if(strlen($createInternalCouponsCampaignRequest->getPrefix()) == 0) {
			//exception
			$msg = "prefix parameter cannot be empty";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getPercent() != NULL) {
			$percent = $createInternalCouponsCampaignRequest->getPercent();
			if(!(is_numeric($percent)) || !(is_int($percent)) || !($percent > 0) || !($percent <= 100)) {
				$msg = "percent parameter must be an integer > 0 and <= 100";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
		}
		if($createInternalCouponsCampaignRequest->getAmountInCents() != NULL) {
			$amountInCents = $createInternalCouponsCampaignRequest->getAmountInCents();
			if(!(is_numeric($amountInCents)) || !(is_int($amountInCents)) || !($amountInCents > 0)) {
				$msg = "amountInCents parameter must be a positive integer";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
		}
		if($createInternalCouponsCampaignRequest->getDiscountDurationLength() != NULL) {
			$discountDurationLength = $createInternalCouponsCampaignRequest->getDiscountDurationLength();
			if(!(is_numeric($discountDurationLength)) || !(is_int($discountDurationLength)) || !($discountDurationLength > 0)) {
				$msg = "discountDurationLength parameter must be a positive integer";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}			
		}
		if($createInternalCouponsCampaignRequest->getTotalNumber() != NULL) {
			$totalNumber = $createInternalCouponsCampaignRequest->getTotalNumber();
			if(!(is_numeric($totalNumber)) || !(is_int($totalNumber)) || !($totalNumber > 0)) {
				$msg = "totalNumber parameter must be a positive integer";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}			
		}
		if($createInternalCouponsCampaignRequest->getMaxRedemptionsByUser() != NULL) {
			$maxRedemptionsByUser = $createInternalCouponsCampaignRequest->getMaxRedemptionsByUser();
			if(!(is_numeric($maxRedemptionsByUser)) || !(is_int($maxRedemptionsByUser)) || !($maxRedemptionsByUser > 0)) {
				$msg = "maxRedemptionsByUser parameter must be a positive integer";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
		}
		if($createInternalCouponsCampaignRequest->getCurrency() != NULL) {
			$currency = $createInternalCouponsCampaignRequest->getCurrency();
			if(!array_key_exists($currency, Currency::getCurrencies())) {
				$msg = "currency parameter is not valid";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
		}
		switch($createInternalCouponsCampaignRequest->getDiscountType()) {
			case 'percent' :
				if($createInternalCouponsCampaignRequest->getPercent() == NULL) {
					//exception
					$msg = "percent parameter cannot be null when discountType parameter is set to percent";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				break;
			case 'amount' :
				if($createInternalCouponsCampaignRequest->getAmountInCents() == NULL) {
					//exception
					$msg = "amount parameter cannot be null when discountType parameter is set to amount";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($createInternalCouponsCampaignRequest->getCurrency() == NULL) {
					//exception
					$msg = "currency parameter cannot be null when discountType parameter is set to amount";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				break;
			default :
				//exception
				$msg = "discountType parameter : ".$createInternalCouponsCampaignRequest->getDiscountType()." is unknown";
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
		switch($createInternalCouponsCampaignRequest->getDiscountDuration()) {
			case 'once' :
				break;
			case 'repeating' :
				if($createInternalCouponsCampaignRequest->getDiscountDurationUnit() == NULL) {
					//exception
					$msg = "discountDurationUnit parameter cannot be null when discountDuration parameter is set to repeating";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($createInternalCouponsCampaignRequest->getDiscountDurationLength() == NULL) {
					//exception
					$msg = "discountDurationLength parameter cannot be null when discountDuration parameter is set to repeating";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				break;
			case 'forever' :
				break;
			default :
				$msg = "discountDuration parameter : ".$createInternalCouponsCampaignRequest->getDiscountDuration()." is unknown";
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
		switch($createInternalCouponsCampaignRequest->getGeneratedMode()) {
			case 'single' :
				break;
			case 'bulk' :
				if($createInternalCouponsCampaignRequest->getGeneratedCodeLength() == NULL) {
					//exception
					$msg = "generatedCodeLength parameter cannot be null when generatedMode is set to bulk";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($createInternalCouponsCampaignRequest->getTotalNumber() == NULL) {
					//exception
					$msg = "totalNumber parameter cannot be null when generatedMode is set to bulk";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				break;
			default :
				$msg = "generatedMode parameter : ".$createInternalCouponsCampaignRequest->getGeneratedMode()." is unknown";
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
		$timeframesSize = count($createInternalCouponsCampaignRequest->getTimeframes());
		if($timeframesSize == 0) {
			//exception
			$msg = "at least one timeframe must be provided";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		// Parameters Verifications OK
		// Database Verifications...
		if(BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByName($createInternalCouponsCampaignRequest->getName(), $createInternalCouponsCampaignRequest->getPlatform()->getId()) != NULL) {
			//exception
			$msg = "an internalCouponsCampaign with the same name=".$createInternalCouponsCampaignRequest->getName()." already exists";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if(BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByPrefix($createInternalCouponsCampaignRequest->getPrefix(), $createInternalCouponsCampaignRequest->getPlatform()->getId()) != NULL) {
			//exception	
			$msg = "an internalCouponsCampaign with the same prefix=".$createInternalCouponsCampaignRequest->getPrefix()." already exists";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		// Database Verifications OK
		$billingInternalCouponsCampaign = new BillingInternalCouponsCampaign();
		$billingInternalCouponsCampaign->setPlatformId($createInternalCouponsCampaignRequest->getPlatform()->getId());
		$billingInternalCouponsCampaign->setUuid(guid());
		$billingInternalCouponsCampaign->setName($createInternalCouponsCampaignRequest->getName());
		$billingInternalCouponsCampaign->setDescription($createInternalCouponsCampaignRequest->getDescription());
		$billingInternalCouponsCampaign->setPrefix($createInternalCouponsCampaignRequest->getPrefix());
		$billingInternalCouponsCampaign->setDiscountType($createInternalCouponsCampaignRequest->getDiscountType());
		$billingInternalCouponsCampaign->setPercent($createInternalCouponsCampaignRequest->getPercent());
		$billingInternalCouponsCampaign->setAmountInCents($createInternalCouponsCampaignRequest->getAmountInCents());
		$billingInternalCouponsCampaign->setCurrency($createInternalCouponsCampaignRequest->getCurrency());
		$billingInternalCouponsCampaign->setDiscountDuration($createInternalCouponsCampaignRequest->getDiscountDuration());
		$billingInternalCouponsCampaign->setDiscountDurationUnit($createInternalCouponsCampaignRequest->getDiscountDurationUnit());
		$billingInternalCouponsCampaign->setDiscountDurationLength($createInternalCouponsCampaignRequest->getDiscountDurationLength());
		$billingInternalCouponsCampaign->setCouponType($createInternalCouponsCampaignRequest->getCouponsCampaignType());
		$billingInternalCouponsCampaign->setGeneratedMode($createInternalCouponsCampaignRequest->getGeneratedMode());
		$billingInternalCouponsCampaign->setGeneratedCodeLength($createInternalCouponsCampaignRequest->getGeneratedCodeLength());
		$billingInternalCouponsCampaign->setCouponTimeframes($createInternalCouponsCampaignRequest->getTimeframes());
		$billingInternalCouponsCampaign->setEmailsEnabled($createInternalCouponsCampaignRequest->getEmailsEnabled());
		$billingInternalCouponsCampaign->setMaxRedemptionsByUser($createInternalCouponsCampaignRequest->getMaxRedemptionsByUser());
		$billingInternalCouponsCampaign = BillingInternalCouponsCampaignDAO::addBillingInternalCouponsCampaign($billingInternalCouponsCampaign);
		return($billingInternalCouponsCampaign);
	}
	
}

?>