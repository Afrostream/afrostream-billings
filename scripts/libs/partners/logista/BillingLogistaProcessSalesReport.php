<?php

require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/partners/logista/utils/LogistaSalesReportLoader.php';

class BillingLogistaProcessSalesReport {
	
	protected $partner;
	
	public function __construct(BillingPartner $partner) {
		$this->partner = $partner;
	}
	
	public function doProcess($salesReportFilePath) {
		$logistaSalesReportLoader = new LogistaSalesReportLoader($salesReportFilePath);
		$saleRecords = $logistaSalesReportLoader->getSaleRecords();
		foreach($saleRecords as $saleRecord) {
			try {
				$this->doProcessSaleRecord($saleRecord);
			} catch(Exception $e) {
				ScriptsConfig::getLogger()->addError("an error occurred while processing sale record, message=".$e->getMessage());
			}
		}
	}
	
	private function doProcessSaleRecord(SaleRecord $saleRecord) {
		$billingInternalCoupon = BillingInternalCouponDAO::getBillingInternalCouponById($saleRecord->getSerialNumber());
		if($billingInternalCoupon == NULL) {
			throw new Exception("no internal coupon found with id = ".$saleRecord->getSerialNumber());
		}
		$billingInternalCouponActionLog = NULL;
		try {
			$billingInternalCouponActionLog = BillingInternalCouponActionLogDAO::addBillingInternalCouponActionLog($billingInternalCoupon->getId(), 'sale_update');
			//Some checks before proccessing...
			$billingInternalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($billingInternalCoupon->getInternalCouponsCampaignsId());
			if($billingInternalCouponsCampaign == NULL) {
				throw new Exception("no internal coupon campaign found with id = ".$billingInternalCoupon->getInternalCouponsCampaignsId());
			}
			if($this->partner->getId() != $billingInternalCouponsCampaign->getPartnerId()) {
				throw new Exception("internal coupon campaign does not belong to the partner with id = ".$this->partner->getId());
			}
			//ok
			$billingInternalCouponOpts = BillingInternalCouponOptsDAO::getBillingInternalCouponOptsByInternalCouponId($billingInternalCoupon->getId());
			$current_internal_coupon_opts_array = $billingInternalCouponOpts->getOpts();
			try {
				//START TRANSACTION
				pg_query("BEGIN");
				$billingInternalCoupon->setSoldStatus('sold');
				$billingInternalCoupon = BillingInternalCouponDAO::updateSoldStatus($billingInternalCoupon);
				$billingInternalCoupon->setSoldDate($saleRecord->getSaleDate());
				$billingInternalCoupon = BillingInternalCouponDAO::updateSoldDate($billingInternalCoupon);
				//HAD OTHERS DATA AVAILABLE IN SALERECORD AS OPTS IN INTERNALCOUPON
				if(array_key_exists('logistaCustomerId', $current_internal_coupon_opts_array)) {
					BillingInternalCouponOptsDAO::updateBillingInternalCouponOptsKey($billingInternalCoupon->getId(), 'logistaCustomerId', $saleRecord->getCustomerId());
				} else {
					BillingInternalCouponOptsDAO::addBillingInternalCouponsOptsKey($billingInternalCoupon->getId(), 'logistaCustomerId', $saleRecord->getCustomerId());
				}
				if(array_key_exists('logistaShopId', $current_internal_coupon_opts_array)) {
					BillingInternalCouponOptsDAO::updateBillingInternalCouponOptsKey($billingInternalCoupon->getId(), 'logistaShopId', $saleRecord->getShopId());
				} else {
					BillingInternalCouponOptsDAO::addBillingInternalCouponsOptsKey($billingInternalCoupon->getId(), 'logistaShopId', $saleRecord->getShopId());
				}			
				if(array_key_exists('logistaZipCode', $current_internal_coupon_opts_array)) {
					BillingInternalCouponOptsDAO::updateBillingInternalCouponOptsKey($billingInternalCoupon->getId(), 'logistaZipCode', $saleRecord->getZipCode());
				} else {
					BillingInternalCouponOptsDAO::addBillingInternalCouponsOptsKey($billingInternalCoupon->getId(), 'logistaZipCode', $saleRecord->getZipCode());
				}
				if(array_key_exists('logistaCountry', $current_internal_coupon_opts_array)) {
					BillingInternalCouponOptsDAO::updateBillingInternalCouponOptsKey($billingInternalCoupon->getId(), 'logistaCountry', $saleRecord->getCountry());
				} else {
					BillingInternalCouponOptsDAO::addBillingInternalCouponsOptsKey($billingInternalCoupon->getId(), 'logistaCountry', $saleRecord->getCountry());
				}
				if(array_key_exists('logistaTimezoneDiff', $current_internal_coupon_opts_array)) {
					BillingInternalCouponOptsDAO::updateBillingInternalCouponOptsKey($billingInternalCoupon->getId(), 'logistaTimezoneDiff', $saleRecord->getTimezoneDiff());
				} else {
					BillingInternalCouponOptsDAO::addBillingInternalCouponsOptsKey($billingInternalCoupon->getId(), 'logistaTimezoneDiff', $saleRecord->getTimezoneDiff());
				}
				//COMMIT
				pg_query("COMMIT");
			} catch(Exception $e) {
				pg_query("ROLLBACK");
				throw $e;
			}
			$billingInternalCouponActionLog->setProcessingStatus('done');
			$billingInternalCouponActionLog = BillingInternalCouponActionLogDAO::updateBillingInternalCouponActionLogProcessingStatus($billingInternalCouponActionLog);
			$billingInternalCouponActionLog = NULL;
		} catch(Exception $e) {
			$msg = "an error occurred while processing sale record, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
			if(isset($billingInternalCouponActionLog)) {
				$billingInternalCouponActionLog->setProcessingStatus('error');
				$billingInternalCouponActionLog->setMessage($msg);
			}
		} finally {
			if(isset($billingInternalCouponActionLog)) {
				$billingInternalCouponActionLog = BillingInternalCouponActionLogDAO::updateBillingInternalCouponActionLogProcessingStatus($billingInternalCouponActionLog);
			}
		}
	}
	
}

?>