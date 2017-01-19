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
		$salesRecords = $logistaSalesReportLoader->getSaleRecords();
		foreach ($salesRecords as $salesRecord) {
			try {
				$this->doProcessSaleRecord($saleRecord);
			} catch(Exception $e) {
				ScriptsConfig::getLogger()->addError("an error occurred while processing sale record");
			}
		}
	}
	
	private function doProcessSaleRecord(SaleRecord $saleRecord) {
		$billingInternalCoupon = BillingInternalCouponDAO::getBillingInternalCouponById($saleRecord->getSerialNumber());
		if($billingInternalCoupon == NULL) {
			throw new Exception("no internal coupon found with id = ".$saleRecord->getSerialNumber());
		}//Some checks before proccessing...
		$billingInternalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($billingInternalCoupon->getInternalCouponsCampaignsId());
		if($billingInternalCouponsCampaign == NULL) {
			throw new Exception("no internal coupon campaign found with id = ".$billingInternalCoupon->getInternalCouponsCampaignsId());
		}
		if($this->partner->getId() != $billingInternalCouponsCampaign->getPartnerId()) {
			throw new Exception("internal coupon campaign does not belong to the partner with id = ".$this->partner->getId());
		}
		try {
			//START TRANSACTION
			pg_query("BEGIN");
			$billingInternalCoupon->setSoldStatus('sold');
			$billingInternalCoupon = BillingInternalCouponDAO::updateSoldStatus($billingInternalCoupon);
			$billingInternalCoupon->setSoldDate($saleRecord->getSaleDate());
			$billingInternalCoupon = BillingInternalCouponDAO::updateSoldDate($billingInternalCoupon);
			//COMMIT
			pg_query("COMMIT");
		} catch(Exception $e) {
			pg_query("ROLLBACK");
			throw $e;
		}
	}
	
}

?>