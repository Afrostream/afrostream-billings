<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../PartnerHandlersBuilder.php';
require_once __DIR__ . '/../requests/CreatePartnerOrderRequest.php';
require_once __DIR__ . '/../requests/BookPartnerOrderRequest.php';

class PartnerOrdersHandler {
	
	protected $partner;
	
	public function __construct(BillingPartner $partner) {
		$this->partner = $partner;
	}	
	
	public function doCreatePartnerOrder(CreatePartnerOrderRequest $createPartnerOrderRequest) {
		$billingPartnerOrder = NULL;
		try {
			config::getLogger()->addInfo("creating a ".$this->partner->getName()." partnerOrder...");
			$billingPartnerOrder = new BillingPartnerOrder();
			$billingPartnerOrder->setPartnerOrderBillingUuid(guid());
			$billingPartnerOrder->setPartnerId($this->partner->getId());
			$billingPartnerOrder->setType($createPartnerOrderRequest->getPartnerOrderType());
			$billingPartnerOrder->setName($createPartnerOrderRequest->getPartnerName());
			$billingPartnerOrder = BillingPartnerOrderDAO::addBillingPartnerOrder($billingPartnerOrder);
			config::getLogger()->addInfo("creating a ".$this->partner->getName()." partnerOrder done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a ".$this->partner->getName()." partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("creating a ".$this->partner->getName()."partnerOrder failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a ".$this->partner->getName()." partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("creating a ".$this->partner->getName()." partnerOrder failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($billingPartnerOrder);
	}
	
	public function doAddInternalCouponsCampaignToPartnerOrder(BillingPartnerOrder $billingPartnerOrder, 
			BillingInternalCouponsCampaign $billingInternalCouponsCampaign, 
			AddInternalCouponsCampaignToPartnerOrderRequest $addInternalCouponsCampaignToPartnerOrderRequest) {
		try {
			config::getLogger()->addInfo("adding an internalCouponsCampaign to a ".$this->partner->getName()." partnerOrder...");
			$billingPartnerOrderInternalCouponsCampaignLink = new BillingPartnerOrderInternalCouponsCampaignLink();
			$billingPartnerOrderInternalCouponsCampaignLink->setPartnerOrderId($billingPartnerOrder->getId());
			$billingPartnerOrderInternalCouponsCampaignLink->setInternalCouponsCampaignsId($billingInternalCouponsCampaign->getId());
			$billingPartnerOrderInternalCouponsCampaignLink->setWishedCounter($addInternalCouponsCampaignToPartnerOrderRequest->getWishedCouponsCounter());
			$billingPartnerOrderInternalCouponsCampaignLink = BillingPartnerOrderInternalCouponsCampaignLinkDAO::addBillingPartnerOrderInternalCouponsCampaignLink($billingPartnerOrderInternalCouponsCampaignLink);
			$billingPartnerOrder = BillingPartnerOrderDAO::getBillingPartnerOrderById($billingPartnerOrder->getId());
			config::getLogger()->addInfo("adding an internalCouponsCampaign to a ".$this->partner->getName()." partnerOrder done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while adding an internalCouponsCampaign to a ".$this->partner->getName()." partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an internalCouponsCampaign to a ".$this->partner->getName()."partnerOrder failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while adding an internalCouponsCampaign to a ".$this->partner->getName()." partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("adding an internalCouponsCampaign to a ".$this->partner->getName()." partnerOrder failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($billingPartnerOrder);	
	}
	
	public function doBookPartnerOrder(BillingPartnerOrder $billingPartnerOrder,
			BookPartnerOrderRequest $bookPartnerOrderRequest) {
		try {
			config::getLogger()->addInfo("booking a ".$this->partner->getName()." partnerOrder...");
			$billingPartnerOrderInternalCouponsCampaignLinks = BillingPartnerOrderInternalCouponsCampaignLinkDAO::getBillingPartnerOrderInternalCouponsCampaignLinksByPartnerOrderId($billingPartnerOrder->getId());
			foreach ($billingPartnerOrderInternalCouponsCampaignLinks as $billingPartnerOrderInternalCouponsCampaignLink) {
				$tobookCounter = $billingPartnerOrderInternalCouponsCampaignLink->getWishedCounter() - $billingPartnerOrderInternalCouponsCampaignLink->getBookedCounter();
				config::getLogger()->addInfo("tobookCounter=".$tobookCounter);
				if($tobookCounter > 0) {
					config::getLogger()->addInfo("tobookCounter=".$tobookCounter."...");
					BillingInternalCouponDAO::bookBillingInternalCoupons($billingPartnerOrderInternalCouponsCampaignLink, $tobookCounter);
					config::getLogger()->addInfo("tobookCounter=".$tobookCounter." done");
					//
					$bookedCounter = BillingInternalCouponDAO::getBillingInternalCouponsTotalNumberByInternalCouponsCampaignsId($billingPartnerOrderInternalCouponsCampaignLink->getInternalCouponsCampaignsId(), $billingPartnerOrderInternalCouponsCampaignLink->getId());
					//
					$billingPartnerOrderInternalCouponsCampaignLink->setBookedCounter($bookedCounter);
					$billingPartnerOrderInternalCouponsCampaignLink = BillingPartnerOrderInternalCouponsCampaignLinkDAO::updateBookedCounter($billingPartnerOrderInternalCouponsCampaignLink);
				}
			}
			config::getLogger()->addInfo("booking a ".$this->partner->getName()." partnerOrder done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while booking a ".$this->partner->getName()." partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("booking a ".$this->partner->getName()."partnerOrder failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while booking a ".$this->partner->getName()." partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("booking a ".$this->partner->getName()." partnerOrder failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($billingPartnerOrder);
	}
	
}

?>