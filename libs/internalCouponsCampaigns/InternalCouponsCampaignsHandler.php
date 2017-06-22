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
require_once __DIR__ . '/../providers/global/requests/GenerateInternalCouponsRequest.php';
require_once __DIR__ . '/../providers/global/requests/UpdateInternalCouponsCampaignRequest.php';
require_once __DIR__ . '/../providers/global/requests/CheckInternalCouponsCampaignRequest.php';

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
		$db_internal_coupons_campaign = NULL;
		try {
			config::getLogger()->addInfo("adding an InternalPlan to an internalCouponsCampaign, couponsCampaignInternalBillingUuid=".$addInternalPlanToInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid()."....");
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($addInternalPlanToInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid(), $addInternalPlanToInternalCouponsCampaignRequest->getPlatform()->getId());
			if($db_internal_coupons_campaign == NULL) {
				$msg = "unknown couponsCampaignInternalBillingUuid : ".$addInternalPlanToInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_internal_plan = InternalPlanDAO::getInternalPlanByUuid($addInternalPlanToInternalCouponsCampaignRequest->getInternalPlanUuid(), $addInternalPlanToInternalCouponsCampaignRequest->getPlatform()->getId());
			if($db_internal_plan == NULL) {
				$msg = "unknown internalPlanUuid : ".$addInternalPlanToInternalCouponsCampaignRequest->getInternalPlanUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//linked to that internalPlan ?
			$billingInternalCouponsCampaignInternalPlan = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlanByInternalPlan($db_internal_plan->getId(), $db_internal_coupons_campaign->getId());
			if(isset($billingInternalCouponsCampaignInternalPlan)) {
				$msg = "internal plan with internalPlanUuid : ".$addInternalPlanToInternalCouponsCampaignRequest->getInternalPlanUuid()." is already linked to the couponsCampaignInternalBillingUuid : ".$addInternalPlanToInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$billingInternalCouponsCampaignInternalPlans = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlansByInternalCouponsCampaignsId($db_internal_coupons_campaign->getId());
			switch ($db_internal_coupons_campaign->getCouponType()) {
				case CouponCampaignType::standard :
				case CouponCampaignType::prepaid :
				case CouponCampaignType::sponsorship :
					if(count($billingInternalCouponsCampaignInternalPlans) == 1) {
						$msg = "no more than one internalPlan can be linked to the internalCouponsCampaign when couponCampaignType=".$db_internal_coupons_campaign->getCouponType();
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					break;
				case CouponCampaignType::promo :
					//nothing more to check
					break;
				default :
					$msg = "unknown couponCampaignType : ".$db_internal_coupons_campaign->getCouponType();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			//Verifications ...
			$this->doCheckInternalPlanCompatibility($db_internal_coupons_campaign, $db_internal_plan);
			//Verification OK
			$billingInternalCouponsCampaignInternalPlan = new BillingInternalCouponsCampaignInternalPlan();
			$billingInternalCouponsCampaignInternalPlan->setInternalCouponsCampaignsId($db_internal_coupons_campaign->getId());
			$billingInternalCouponsCampaignInternalPlan->setInternalPlanId($db_internal_plan->getId());
			BillingInternalCouponsCampaignInternalPlansDAO::addBillingInternalCouponsCampaignInternalPlan($billingInternalCouponsCampaignInternalPlan);
			//done
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($db_internal_coupons_campaign->getId());
			config::getLogger()->addInfo("adding an InternalPlan to an internalCouponsCampaign, couponsCampaignInternalBillingUuid=".$addInternalPlanToInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid()." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while adding an InternalPlan to an internalCouponsCampaign for couponsCampaignInternalBillingUuid=".$addInternalPlanToInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an InternalPlan to an internalCouponsCampaign failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while adding an InternalPlan to an internalCouponsCampaign for couponsCampaignInternalBillingUuid=".$addInternalPlanToInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an InternalPlan to an internalCouponsCampaign failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_coupons_campaign);
	}
	
	public function doRemoveFromInternalPlan(RemoveInternalPlanFromInternalCouponsCampaignRequest $removeInternalPlanFromInternalCouponsCampaignRequest) {
		$db_internal_coupons_campaign = NULL;
		try {
			config::getLogger()->addInfo("removing an InternalPlan to an internalCouponsCampaign, couponsCampaignInternalBillingUuid=".$removeInternalPlanFromInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid()."....");
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($removeInternalPlanFromInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid(), $removeInternalPlanFromInternalCouponsCampaignRequest->getPlatform()->getId());
			if($db_internal_coupons_campaign == NULL) {
				$msg = "unknown couponsCampaignInternalBillingUuid : ".$removeInternalPlanFromInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_internal_plan = InternalPlanDAO::getInternalPlanByUuid($removeInternalPlanFromInternalCouponsCampaignRequest->getInternalPlanUuid(), $removeInternalPlanFromInternalCouponsCampaignRequest->getPlatform()->getId());
			if($db_internal_plan == NULL) {
				$msg = "unknown internalPlanUuid : ".$removeInternalPlanFromInternalCouponsCampaignRequest->getInternalPlanUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//linked to that internalPlan ?
			$billingInternalCouponsCampaignInternalPlan = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlanByInternalPlan($db_internal_plan->getId(), $db_internal_coupons_campaign->getId());
			if($billingInternalCouponsCampaignInternalPlan == NULL) {
				$msg = "internal plan with internalPlanUuid : ".$removeInternalPlanFromInternalCouponsCampaignRequest->getInternalPlanUuid()." is NOT linked to the couponsCampaignInternalBillingUuid=".$removeInternalPlanFromInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			BillingInternalCouponsCampaignInternalPlansDAO::deleteBillingInternalCouponsCampaignInternalPlanById($billingInternalCouponsCampaignInternalPlan->getId());
			//done
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($db_internal_coupons_campaign->getId());
			config::getLogger()->addInfo("removing an InternalPlan from an internalCouponsCampaign, couponsCampaignInternalBillingUuid=".$removeInternalPlanFromInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid()." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while removing an InternalPlan from an internalCouponsCampaign for couponsCampaignInternalBillingUuid=".$removeInternalPlanFromInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("removing an InternalPlan from an internalCouponsCampaign failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while removing an InternalPlan from an internalCouponsCampaign for couponsCampaignInternalBillingUuid=".$removeInternalPlanFromInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("removing an InternalPlan from an internalCouponsCampaign failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_coupons_campaign);
	}
	
	public function create(CreateInternalCouponsCampaignRequest $createInternalCouponsCampaignRequest) {
		// Parameters Verifications...
		$this->doCheckInternalCouponsCampaignCreation($createInternalCouponsCampaignRequest);
		// Parameters Verifications OK
		// Database Verifications...
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
		$billingInternalCouponsCampaign->setTotalNumber($createInternalCouponsCampaignRequest->getTotalNumber());
		$billingInternalCouponsCampaign->setCouponTimeframes($createInternalCouponsCampaignRequest->getTimeframes());
		$billingInternalCouponsCampaign->setEmailsEnabled($createInternalCouponsCampaignRequest->getEmailsEnabled());
		$billingInternalCouponsCampaign->setMaxRedemptionsByUser($createInternalCouponsCampaignRequest->getMaxRedemptionsByUser());
		$billingInternalCouponsCampaign->setExpiresDate($createInternalCouponsCampaignRequest->getExpiresDate());
		$billingInternalCouponsCampaign = BillingInternalCouponsCampaignDAO::addBillingInternalCouponsCampaign($billingInternalCouponsCampaign);
		return($billingInternalCouponsCampaign);
	}
	
	public function doGenerateInternalCoupons(GenerateInternalCouponsRequest $generateInternalCouponsRequest) {
		$db_internal_coupons_campaign = NULL;
		try {
			config::getLogger()->addInfo("generating internalCoupons for an internalCouponsCampaign, couponsCampaignInternalBillingUuid=".$generateInternalCouponsRequest->getCouponsCampaignInternalBillingUuid()."....");
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($generateInternalCouponsRequest->getCouponsCampaignInternalBillingUuid(), $generateInternalCouponsRequest->getPlatform()->getId());
			if($db_internal_coupons_campaign == NULL) {
				$msg = "unknown couponsCampaignInternalBillingUuid : ".$generateInternalCouponsRequest->getCouponsCampaignInternalBillingUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//
			$separator = $this->getSeparator($db_internal_coupons_campaign);
			//
			$coupon_counter = BillingInternalCouponDAO::getBillingInternalCouponsTotalNumberByInternalCouponsCampaignsId($db_internal_coupons_campaign->getId());
			$coupon_counter_duplicate = 0;
			$coupon_total_number = $db_internal_coupons_campaign->getGeneratedMode() == 'single' ? 1 : $db_internal_coupons_campaign->getTotalNumber();
			$coupon_counter_missing = $coupon_total_number - $coupon_counter;
			config::getLogger()->addInfo("generating ".$coupon_counter_missing." missing coupons out of ".$coupon_total_number." for couponsCampaignInternalBillingUuid=".$generateInternalCouponsRequest->getCouponsCampaignInternalBillingUuid()."...");
			while($coupon_counter < $coupon_total_number) {
				$code = NULL;
				if($db_internal_coupons_campaign->getGeneratedMode() == 'single') {
					$code = strtoupper($db_internal_coupons_campaign->getPrefix());
				} else {
					$code = strtoupper($db_internal_coupons_campaign->getPrefix().$separator.$this->getRandomString($db_internal_coupons_campaign->getGeneratedCodeLength()));
				}
				$internalCouponAlreadyExisting = BillingInternalCouponDAO::getBillingInternalCouponByCode($code, $generateInternalCouponsRequest->getPlatform()->getId());
				if(isset($internalCouponAlreadyExisting)) {
					$coupon_counter_duplicate++;
					config::getLogger()->addInfo("generating internalCoupons, duplicates : ".$coupon_counter_duplicate." => let's continue anyway");
					if($coupon_counter_duplicate == 10) {
						$msg = "generating internalCoupons : too many duplicates => STOP";
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					continue;
				} else {
					$internalCoupon = new BillingInternalCoupon();
					$internalCoupon->setInternalCouponsCampaignsId($db_internal_coupons_campaign->getId());
					$internalCoupon->setCode($code);
					$internalCoupon->setUuid(guid());
					$internalCoupon->setExpiresDate($db_internal_coupons_campaign->getExpiresDate());
					$internalCoupon->setPlatformId($generateInternalCouponsRequest->getPlatform()->getId());
					$internalCoupon = BillingInternalCouponDAO::addBillingInternalCoupon($internalCoupon);
					$coupon_counter++;
					config::getLogger()->addInfo("(".$coupon_counter."/".$coupon_total_number.") coupon with code ".$internalCoupon->getCode()." for couponsCampaignInternalBillingUuid=".$generateInternalCouponsRequest->getCouponsCampaignInternalBillingUuid()." generated successfully");
				}
			}
			//done
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($db_internal_coupons_campaign->getId());
			config::getLogger()->addInfo("generating internalCoupons for an internalCouponsCampaign, couponsCampaignInternalBillingUuid=".$generateInternalCouponsRequest->getCouponsCampaignInternalBillingUuid()." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while generating internalCoupons for an internalCouponsCampaign, couponsCampaignInternalBillingUuid=".$generateInternalCouponsRequest->getCouponsCampaignInternalBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("generating internalCoupons for an internalCouponsCampaign failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while generating internalCoupons for an internalCouponsCampaign, couponsCampaignInternalBillingUuid=".$generateInternalCouponsRequest->getCouponsCampaignInternalBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("generating internalCoupons for an internalCouponsCampaign failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_coupons_campaign);
	}
	
	private function getRandomString($length) {
		$strAlphaNumericString = '23456789bcdfghjkmnpqrstvwxz';
		$strReturnString = '';
		for ($intCounter = 0; $intCounter < $length; $intCounter++) {
			$strReturnString .= $strAlphaNumericString[random_int(0, strlen($strAlphaNumericString) - 1)];
		}
		return $strReturnString;
	}
	
	private function getSeparator(BillingInternalCouponsCampaign $billingInternalCouponsCampaign) {
		$partner = NULL;
		if($billingInternalCouponsCampaign->getPartnerId() != NULL) {
			$partner = BillingPartnerDAO::getPartnerById($billingInternalCouponsCampaign->getPartnerId());
		}
		if($partner != NULL) {
			if($partner->getName() == 'logista') {
				return("");//logista = alphanumeric only
			}
		}
		return("-");
	}
	
	public function doUpdateInternalCouponsCampaign(UpdateInternalCouponsCampaignRequest $updateInternalCouponsCampaignRequest) {
		$couponsCampaignInternalBillingUuid = $updateInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid();
		//
		$db_internal_coupons_campaign = NULL;
		try {
			config::getLogger()->addInfo("internalCouponsCampaign updating...");
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($couponsCampaignInternalBillingUuid, $updateInternalCouponsCampaignRequest->getPlatform()->getId());
			if($db_internal_coupons_campaign == NULL) {
				$msg = "unknown couponsCampaignInternalBillingUuid : ".$couponsCampaignInternalBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				//name
				if($updateInternalCouponsCampaignRequest->getName() != NULL) {
					if(strlen($updateInternalCouponsCampaignRequest->getName()) == 0) {
						//exception
						$msg = "name parameter cannot be empty";
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					$db_internal_coupons_campaign->setName($updateInternalCouponsCampaignRequest->getName());
					$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::updateName($db_internal_coupons_campaign);
				}
				//description //allow empty => !==
				if($updateInternalCouponsCampaignRequest->getDescription() !== NULL) {
					$db_internal_coupons_campaign->setDescription($updateInternalCouponsCampaignRequest->getDescription());
					$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::updateDescription($db_internal_coupons_campaign);
				}
				//emailsEnabled
				if($updateInternalCouponsCampaignRequest->getEmailsEnabled() !== NULL) {
					$db_internal_coupons_campaign->setEmailsEnabled($updateInternalCouponsCampaignRequest->getEmailsEnabled());
					$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::updateEmailsEnabled($db_internal_coupons_campaign);
				}
				//timeframes
				if($updateInternalCouponsCampaignRequest->getTimeframes() != NULL) {
					$timeframesSize = count($updateInternalCouponsCampaignRequest->getTimeframes());
					if($timeframesSize == 0) {
						//exception
						$msg = "at least one timeframe must be provided";
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					switch($db_internal_coupons_campaign->getCouponType()) {
						case CouponCampaignType::standard :
						case CouponCampaignType::prepaid :
						case CouponCampaignType::sponsorship :
							if($timeframesSize != 1) {
								//exception
								$msg = "only one couponsCampaignTimeframe is supported when couponsCampaignType is set to ".$db_internal_coupons_campaign->getCouponType();
								throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							}
							if($updateInternalCouponsCampaignRequest->getTimeframes()[0] != CouponTimeframe::onSubCreation) {
								//exception
								$msg = "only couponsCampaignTimeframe=".CouponTimeframe::onSubCreation." is supported when couponsCampaignType is set to ".$db_internal_coupons_campaign->getCouponType();
								throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							}
							break;
						case CouponCampaignType::promo :
							//nothing more to check
							break;
						default :
							$msg = "unknown couponCampaignType : ".$db_internal_coupons_campaign->getCouponType();
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							break;
					}
					$db_internal_coupons_campaign->setCouponTimeframes($updateInternalCouponsCampaignRequest->getTimeframes());
					$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::updateTimeframes($db_internal_coupons_campaign);
				}
				//maxRedemptionsByUser
				if($updateInternalCouponsCampaignRequest->getMaxRedemptionsByUser() !== NULL) {
					$maxRedemptionsByUser = $updateInternalCouponsCampaignRequest->getMaxRedemptionsByUser();
					if(!(is_numeric($maxRedemptionsByUser)) || !(is_int($maxRedemptionsByUser)) || !($maxRedemptionsByUser > 0)) {
						$msg = "maxRedemptionsByUser parameter must be a positive integer";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					$db_internal_coupons_campaign->setMaxRedemptionsByUser($maxRedemptionsByUser);
					$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::updateMaxRedemptionsByUser($db_internal_coupons_campaign);
				}
				//totalNumber
				if($updateInternalCouponsCampaignRequest->getTotalNumber() !== NULL) {
					$totalNumber = $updateInternalCouponsCampaignRequest->getTotalNumber();
					if(!(is_numeric($totalNumber)) || !(is_int($totalNumber)) || !($totalNumber > 0)) {
						$msg = "totalNumber parameter must be a positive integer";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					switch($db_internal_coupons_campaign->getGeneratedMode()) {
						case 'single' :
							//exception
							$msg = "totalNumber parameter must be null when generatedMode is set to single";
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							break;
						case 'bulk' :
							//nothing to check
							break;
						default :
							$msg = "generatedMode parameter : ".$db_internal_coupons_campaign->getGeneratedMode()." is unknown";
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							break;
					}
					//totalNumber cannot be less than current number
					$currentTotalNumber = BillingInternalCouponDAO::getBillingInternalCouponsTotalNumberByInternalCouponsCampaignsId($db_internal_coupons_campaign->getId());
					if(isset($currentTotalNumber)) {
						if($totalNumber < $currentTotalNumber) {
							$msg = "totalNumber parameter : ".$totalNumber." cannot be less than current totalNumber : ".$currentTotalNumber;
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						}
					}
					$db_internal_coupons_campaign->setTotalNumber($totalNumber);
					$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::updateTotalNumber($db_internal_coupons_campaign);
				}
				//generatedCodeLength
				if($updateInternalCouponsCampaignRequest->getGeneratedCodeLength() !== NULL) {
					$generatedCodeLength = $updateInternalCouponsCampaignRequest->getGeneratedCodeLength();
					if(!(is_numeric($generatedCodeLength)) || !(is_int($generatedCodeLength)) || !($generatedCodeLength > 0)) {
						$msg = "generatedCodeLength parameter must be a positive integer";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					switch($db_internal_coupons_campaign->getGeneratedMode()) {
						case 'single' :
							//exception
							$msg = "generatedCodeLength parameter must be null when generatedMode is set to single";
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							break;
						case 'bulk' :
							//nothing to check
							break;
						default :
							$msg = "generatedMode parameter : ".$db_internal_coupons_campaign->getGeneratedMode()." is unknown";
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							break;
					}
					$db_internal_coupons_campaign->setGeneratedCodeLength($generatedCodeLength);
					$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::updateGeneratedCodeLength($db_internal_coupons_campaign);
				}
				//expiresDate
				if($updateInternalCouponsCampaignRequest->getExpiresDate() !== false) {
					if($updateInternalCouponsCampaignRequest->getExpiresDate() !== NULL) {
						//30 seconds in the past MAX
						if((new DateTime())->getTimestamp() - ($updateInternalCouponsCampaignRequest->getExpiresDate()->getTimestamp()) > 30) {
							//exception
							$msg = "expiresDate cannot be in the past";
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						}
					}
					$db_internal_coupons_campaign->setExpiresDate($updateInternalCouponsCampaignRequest->getExpiresDate());
					$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::updateExpiresDate($db_internal_coupons_campaign);
				}
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			//done
			config::getLogger()->addInfo("internalCouponsCampaign updating done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating internalCouponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCouponsCampaign updating failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating internalCouponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCouponsCampaign updating failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_coupons_campaign);
	}
	
	protected function doCheckInternalPlanCompatibility(BillingInternalCouponsCampaign $db_internal_coupons_campaign, InternalPlan $db_internal_plan) {
		//provider check
		$billingProviderCouponsCampaigns = BillingProviderCouponsCampaignDAO::getBillingProviderCouponsCampaignsByInternalCouponsCampaignsId($db_internal_coupons_campaign->getId());
		foreach ($billingProviderCouponsCampaigns as $billingProviderCouponsCampaign) {
			$providerPlan = PlanDAO::getPlanByInternalPlanId($db_internal_plan->getId(), $billingProviderCouponsCampaign->getProviderId());
			if($providerPlan == NULL) {
				$msg = "internalPlan is not linked to the provider named : '".ProviderDAO::getProviderById($billingProviderCouponsCampaign->getProviderId())->getName()."'";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
		}
		//currency check
		if($db_internal_coupons_campaign->getCurrency() != NULL) {
			if($db_internal_coupons_campaign->getCurrency() != $db_internal_plan->getCurrency()) {
				$msg = "internalPlan and internalCouponsCampaign must have the same currency";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
		}
		//amount check
		switch($db_internal_coupons_campaign->getCouponType()) {
			case CouponCampaignType::standard :
				if($db_internal_plan->getAmountInCents() == 0) {
					//Exception
					$msg = "couponCampaignType : ".$db_internal_coupons_campaign->getCouponType()." not compatible with amountInCents=".$db_internal_plan->getAmountInCents();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				break;
			case CouponCampaignType::prepaid :
				//nothing to do
				break;
			case CouponCampaignType::sponsorship :
				if($db_internal_plan->getAmountInCents() > 0) {
					//Exception
					$msg = "couponCampaignType : ".$db_internal_coupons_campaign->getCouponType()." not compatible with amountInCents=".$db_internal_plan->getAmountInCents();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						
				}
				break;
			case CouponCampaignType::promo :
				if($db_internal_plan->getAmountInCents() == 0) {
					//Exception
					$msg = "couponCampaignType : ".$db_internal_coupons_campaign->getCouponType()." not compatible with amountInCents=".$db_internal_plan->getAmountInCents();
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				break;
			default :
				$msg = "unknown couponCampaignType : ".$db_internal_coupons_campaign->getCouponType();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
	}
	
	protected function doCheckInternalCouponsCampaignCreation(CreateInternalCouponsCampaignRequest $createInternalCouponsCampaignRequest) {
		//GLOBAL
		if(strlen($createInternalCouponsCampaignRequest->getName()) == 0) {
			//exception
			$msg = "name parameter cannot be empty";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if(strlen($createInternalCouponsCampaignRequest->getPrefix()) == 0) {
			//exception
			$msg = "prefix parameter cannot be empty";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		switch($createInternalCouponsCampaignRequest->getGeneratedMode()) {
			case 'single' :
				//must be null : generated_code_length, total_number
				if($createInternalCouponsCampaignRequest->getGeneratedCodeLength() !== NULL) {
					//exception
					$msg = "generatedCodeLength parameter must be null when generatedMode is set to single";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($createInternalCouponsCampaignRequest->getTotalNumber() !== NULL) {
					//exception
					$msg = "totalNumber parameter must be null when generatedMode is set to single";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				break;
			case 'bulk' :
				//must not to be null : RAS, in some case, code is generated outside (cashway), totalNumber can be null as coupon is generated when asked
				if($createInternalCouponsCampaignRequest->getGeneratedCodeLength() === NULL) {
					//exception
					$msg = "generatedCodeLength parameter must not null when generatedMode is set to bulk";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				} else {
					$generatedCodeLength = $createInternalCouponsCampaignRequest->getGeneratedCodeLength();
					if(!(is_numeric($generatedCodeLength)) || !(is_int($generatedCodeLength)) || !($generatedCodeLength > 0)) {
						$msg = "generatedCodeLength parameter must be a positive integer";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
				}
				if($createInternalCouponsCampaignRequest->getTotalNumber() === NULL) {
					//exception
					$msg = "totalNumber parameter must be not null when generatedMode is set to bulk";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				} else {
					$totalNumber = $createInternalCouponsCampaignRequest->getTotalNumber();
					if(!(is_numeric($totalNumber)) || !(is_int($totalNumber)) || !($totalNumber > 0)) {
						$msg = "totalNumber parameter must be a positive integer";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
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
		if($createInternalCouponsCampaignRequest->getExpiresDate() != NULL) {
			//30 seconds in the past MAX
			if((new DateTime())->getTimestamp() - ($createInternalCouponsCampaignRequest->getExpiresDate()->getTimestamp()) > 30) {
				//exception
				$msg = "expiresDate cannot be in the past";
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
		}
		if($createInternalCouponsCampaignRequest->getMaxRedemptionsByUser() !== NULL) {
			$maxRedemptionsByUser = $createInternalCouponsCampaignRequest->getMaxRedemptionsByUser();
			if(!(is_numeric($maxRedemptionsByUser)) || !(is_int($maxRedemptionsByUser)) || !($maxRedemptionsByUser > 0)) {
				$msg = "maxRedemptionsByUser parameter must be a positive integer";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
		}
		//SPECIFIC
		switch($createInternalCouponsCampaignRequest->getCouponsCampaignType()) {
			case CouponCampaignType::standard :
				$this->doCheckStandardInternalCouponsCampaignCreation($createInternalCouponsCampaignRequest);
				break;
			case CouponCampaignType::prepaid :
				$this->doCheckPrepaidInternalCouponsCampaignCreation($createInternalCouponsCampaignRequest);
				break;
			case CouponCampaignType::sponsorship :
				$this->doCheckSponsorshipInternalCouponsCampaignCreation($createInternalCouponsCampaignRequest);
				break;
			case CouponCampaignType::promo :
				$this->doCheckPromoInternalCouponsCampaignCreation($createInternalCouponsCampaignRequest);
				break;
			default :
				$msg = "unknown couponCampaignType : ".$createInternalCouponsCampaignRequest->getCouponsCampaignType();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
	}
	
	protected function doCheckStandardInternalCouponsCampaignCreation(CreateInternalCouponsCampaignRequest $createInternalCouponsCampaignRequest) {
		if($createInternalCouponsCampaignRequest->getDiscountType() != 'none') {
			//exception
			$msg = "discountType parameter must be set to 'none' when couponsCampaignType is set to standard";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//must be NULL : amountInCents, currency, percent, discount_duration, discount_duration_unit, discount_duration_length
		if($createInternalCouponsCampaignRequest->getAmountInCents() !== NULL) {
			//exception
			$msg = "amountInCents parameter must be null when couponsCampaignType is set to standard";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getCurrency() !== NULL) {
			//exception
			$msg = "currency parameter must be null when couponsCampaignType is set to standard";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getPercent() !== NULL) {
			//exception
			$msg = "percent parameter must be null when couponsCampaignType is set to standard";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getDiscountDuration() !== NULL) {
			//exception
			$msg = "discountDuration parameter must be null when couponsCampaignType is set to standard";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getDiscountDurationUnit() !== NULL) {
			//exception
			$msg = "discountDurationUnit parameter must be null when couponsCampaignType is set to standard";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getDiscountDurationLength() !== NULL) {
			//exception
			$msg = "discountDurationLength parameter must be null when couponsCampaignType is set to standard";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$timeframesSize = count($createInternalCouponsCampaignRequest->getTimeframes());
		if($timeframesSize != 1) {
			//exception
			$msg = "only one couponsCampaignTimeframe is supported when couponsCampaignType is set to standard";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getTimeframes()[0] != CouponTimeframe::onSubCreation) {
			//exception
			$msg = "only couponsCampaignTimeframe=".CouponTimeframe::onSubCreation." is supported when couponsCampaignType is set to standard";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	protected function doCheckPrepaidInternalCouponsCampaignCreation(CreateInternalCouponsCampaignRequest $createInternalCouponsCampaignRequest) {
		if($createInternalCouponsCampaignRequest->getDiscountType() != 'none') {
			//exception
			$msg = "discountType parameter must be set to 'none' when couponsCampaignType is set to prepaid";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//must be NULL : amountInCents, currency, percent, discount_duration, discount_duration_unit, discount_duration_length
		if($createInternalCouponsCampaignRequest->getAmountInCents() !== NULL) {
			//exception
			$msg = "amountInCents parameter must be null when couponsCampaignType is set to prepaid";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getCurrency() !== NULL) {
			//exception
			$msg = "currency parameter must be null when couponsCampaignType is set to prepaid";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getPercent() !== NULL) {
			//exception
			$msg = "percent parameter must be null when couponsCampaignType is set to prepaid";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getDiscountDuration() !== NULL) {
			//exception
			$msg = "discountDuration parameter must be null when couponsCampaignType is set to prepaid";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getDiscountDurationUnit() !== NULL) {
			//exception
			$msg = "discountDurationUnit parameter must be null when couponsCampaignType is set to prepaid";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getDiscountDurationLength() !== NULL) {
			//exception
			$msg = "discountDurationLength parameter must be null when couponsCampaignType is set to prepaid";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$timeframesSize = count($createInternalCouponsCampaignRequest->getTimeframes());
		if($timeframesSize != 1) {
			//exception
			$msg = "only one couponsCampaignTimeframe is supported when couponsCampaignType is set to prepaid";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getTimeframes()[0] != CouponTimeframe::onSubCreation) {
			//exception
			$msg = "only couponsCampaignTimeframe=".CouponTimeframe::onSubCreation." is supported when couponsCampaignType is set to prepaid";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	protected function doCheckSponsorshipInternalCouponsCampaignCreation(CreateInternalCouponsCampaignRequest $createInternalCouponsCampaignRequest) {
		if($createInternalCouponsCampaignRequest->getDiscountType() != 'none') {
			//exception
			$msg = "discountType parameter must be set to 'none' when couponsCampaignType is set to sponsorship";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//must be NULL : amountInCents, currency, percent, discount_duration, discount_duration_unit, discount_duration_length
		if($createInternalCouponsCampaignRequest->getAmountInCents() !== NULL) {
			//exception
			$msg = "amountInCents parameter must be null when couponsCampaignType is set to sponsorship";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getCurrency() !== NULL) {
			//exception
			$msg = "currency parameter must be null when couponsCampaignType is set to sponsorship";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getPercent() !== NULL) {
			//exception
			$msg = "percent parameter must be null when couponsCampaignType is set to sponsorship";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getDiscountDuration() !== NULL) {
			//exception
			$msg = "discountDuration parameter must be null when couponsCampaignType is set to sponsorship";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getDiscountDurationUnit() !== NULL) {
			//exception
			$msg = "discountDurationUnit parameter must be null when couponsCampaignType is set to sponsorship";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getDiscountDurationLength() !== NULL) {
			//exception
			$msg = "discountDurationLength parameter must be null when couponsCampaignType is set to sponsorship";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$timeframesSize = count($createInternalCouponsCampaignRequest->getTimeframes());
		if($timeframesSize != 1) {
			//exception
			$msg = "only one couponsCampaignTimeframe is supported when couponsCampaignType is set to sponsorship";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		if($createInternalCouponsCampaignRequest->getTimeframes()[0] != CouponTimeframe::onSubCreation) {
			//exception
			$msg = "only couponsCampaignTimeframe=".CouponTimeframe::onSubCreation." is supported when couponsCampaignType is set to sponsorship";
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	
	protected function doCheckPromoInternalCouponsCampaignCreation(CreateInternalCouponsCampaignRequest $createInternalCouponsCampaignRequest) {
		$possible_discountTypes = ['percent', 'amount'];
		if(!in_array($createInternalCouponsCampaignRequest->getDiscountType(), $possible_discountTypes)) {
			//exception
			$msg = "discountType must be one of follows : ".implode(', ', $possible_discountTypes);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		switch ($createInternalCouponsCampaignRequest->getDiscountType()) {
			case 'percent' :
				//percent must be not null
				if($createInternalCouponsCampaignRequest->getPercent() === NULL) {
					//exception
					$msg = "percent parameter cannot be null when discountType is set to percent";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				} else {
					$percent = $createInternalCouponsCampaignRequest->getPercent();
					if(!(is_numeric($percent)) || !(is_int($percent)) || !($percent > 0) || !($percent <= 100)) {
						$msg = "percent parameter must be an integer > 0 and <= 100";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
				}
				//amountInCents, currency must be null
				if($createInternalCouponsCampaignRequest->getAmountInCents() !== NULL) {
					//exception
					$msg = "amountInCents parameter must be null when discountType is set to percent";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($createInternalCouponsCampaignRequest->getCurrency() !== NULL) {
					//exception
					$msg = "currency parameter must be null when discountType is set to percent";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				break;
			case 'amount' :
				//amountInCents, currency must be not null
				if($createInternalCouponsCampaignRequest->getAmountInCents() === NULL) {
					//exception
					$msg = "amountInCents parameter cannot be null when discountType is set to amount";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				} else {
					$amountInCents = $createInternalCouponsCampaignRequest->getAmountInCents();
					if(!(is_numeric($amountInCents)) || !(is_int($amountInCents)) || !($amountInCents > 0)) {
						$msg = "amountInCents parameter must be a positive integer";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
				}
				if($createInternalCouponsCampaignRequest->getCurrency() === NULL) {
					//exception
					$msg = "currency parameter cannot be null when discountType is set to amount";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				} else {
					$currency = $createInternalCouponsCampaignRequest->getCurrency();
					if(!array_key_exists($currency, Currency::getCurrencies())) {
						$msg = "currency parameter is not valid";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
				}
				//percent must be null
				if($createInternalCouponsCampaignRequest->getPercent() !== NULL) {
					//exception
					$msg = "percent parameter must be null when discountType is set to amount";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				break;
			default : 
				//should not happen
				$msg = "unknown discountType : ".$createInternalCouponsCampaignRequest->getDiscountType();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
		$possible_discountDurations = ['once', 'repeating', 'forever'];
		if(!in_array($createInternalCouponsCampaignRequest->getDiscountDuration(), $possible_discountDurations)) {
			//exception
			$msg = "discountDuration must be one of follows : ".implode(', ', $possible_discountDurations);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		switch($createInternalCouponsCampaignRequest->getDiscountDuration()) {
			case 'once' :
				//must be null : discount_duration_unit, discount_duration_length
				if($createInternalCouponsCampaignRequest->getDiscountDurationUnit() !== NULL) {
					//exception
					$msg = "discountDurationUnit parameter must be null when discountDuration is set to once";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($createInternalCouponsCampaignRequest->getDiscountDurationLength() !== NULL) {
					//exception
					$msg = "discountDurationLength parameter must be null when discountDuration is set to once";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				break;
			case 'repeating' :
				//must be not null : discount_duration_unit, discount_duration_length
				if($createInternalCouponsCampaignRequest->getDiscountDurationUnit() === NULL) {
					//exception
					$msg = "discountDurationUnit parameter cannot be null when discountDuration parameter is set to repeating";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				} else {
					$possible_discountDurationUnits = ['month', 'year'];
					if(!in_array($createInternalCouponsCampaignRequest->getDiscountDurationUnit(), $possible_discountDurationUnits)) {
						//exception
						$msg = "discountDurationUnit must be one of follows : ".implode(', ', $possible_discountDurationUnits);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
				}
				if($createInternalCouponsCampaignRequest->getDiscountDurationLength() === NULL) {
					//exception
					$msg = "discountDurationLength parameter cannot be null when discountDuration parameter is set to repeating";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				} else {
					$discountDurationLength = $createInternalCouponsCampaignRequest->getDiscountDurationLength();
					if(!(is_numeric($discountDurationLength)) || !(is_int($discountDurationLength)) || !($discountDurationLength > 0)) {
						$msg = "discountDurationLength parameter must be a positive integer";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
				}
				break;
			case 'forever' :
				//must be null : discount_duration_unit, discount_duration_length
				if($createInternalCouponsCampaignRequest->getDiscountDurationUnit() !== NULL) {
					//exception
					$msg = "discountDurationUnit parameter must be null when discountDuration is set to forever";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				if($createInternalCouponsCampaignRequest->getDiscountDurationLength() !== NULL) {
					//exception
					$msg = "discountDurationLength parameter must be null when discountDuration is set to forever";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				break;
			default :
				//should not happen
				$msg = "unknown discountDuration : ".$createInternalCouponsCampaignRequest->getDiscountDuration();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
		}
	}
	
	public function doCheckInternalCouponsCampaign(CheckInternalCouponsCampaignRequest $checkInternalCouponsCampaignRequest) {
		$couponsCampaignInternalBillingUuid = $checkInternalCouponsCampaignRequest->getCouponsCampaignInternalBillingUuid();
		//
		$db_internal_coupons_campaign = NULL;
		try {
			config::getLogger()->addInfo("internalCouponsCampaign checking...");
			$db_internal_coupons_campaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignByUuid($couponsCampaignInternalBillingUuid, $checkInternalCouponsCampaignRequest->getPlatform()->getId());
			if($db_internal_coupons_campaign == NULL) {
				$msg = "unknown couponsCampaignInternalBillingUuid : ".$couponsCampaignInternalBillingUuid;
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//TODO : connected to one (or more internalPlans) / connected to one (or more providers) 
			$billingInternalCouponsCampaignInternalPlans = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlansByInternalCouponsCampaignsId($db_internal_coupons_campaign->getId());
			//has as many coupons as given
			$coupon_counter = BillingInternalCouponDAO::getBillingInternalCouponsTotalNumberByInternalCouponsCampaignsId($db_internal_coupons_campaign->getId());
			switch ($db_internal_coupons_campaign->getGeneratedMode()) {
				case 'single' :
					if($coupon_counter != 1) {
						//TODO
					}
					break;
				case 'bulk';
					if($db_internal_coupons_campaign->getTotalNumber() != NULL) {
						//TODO
					}
					break;
				default :
					$msg = "generatedMode parameter : ".$db_internal_coupons_campaign->getGeneratedMode()." is unknown";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
			//done
			config::getLogger()->addInfo("internalCouponsCampaign checking done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while checking internalCouponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCouponsCampaign checking failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while checking internalCouponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("internalCouponsCampaign checking failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($db_internal_coupons_campaign);
	}
	
}

?>