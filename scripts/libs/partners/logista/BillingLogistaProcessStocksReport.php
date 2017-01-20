<?php

require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/partners/logista/utils/LogistaStocksReportLoader.php';

class BillingLogistaProcessStocksReport {
	
	protected $partner;
	
	public function __construct(BillingPartner $partner) {
		$this->partner = $partner;
	}
	
	public function doProcess($stocksReportFilePath) {
		$logistaStocksReportLoader = new LogistaStocksReportLoader($stocksReportFilePath);
		$stocksRecords = $logistaStocksReportLoader->getStocksRecords();
		foreach($stocksRecords as $stocksRecord) {
			try {
				$this->doProcessStocksRecord($stocksRecord, $logistaStocksReportLoader->getStocksDate());
			} catch(Exception $e) {
				ScriptsConfig::getLogger()->addError("an error occurred while processing stocks record, message=".$e->getMessage());
			}
		}
	}
	
	private function doProcessStocksRecord(StocksRecord $stocksRecord, DateTime $stockDate) {
		$billingInternalCoupon = BillingInternalCouponDAO::getBillingInternalCouponById($stocksRecord->getSerialNumber());
		if($billingInternalCoupon == NULL) {
			throw new Exception("no internal coupon found with id = ".$stocksRecord->getSerialNumber());
		}//Some checks before proccessing...
		$billingInternalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($billingInternalCoupon->getInternalCouponsCampaignsId());
		if($billingInternalCouponsCampaign == NULL) {
			throw new Exception("no internal coupon campaign found with id = ".$billingInternalCoupon->getInternalCouponsCampaignsId());
		}
		if($this->partner->getId() != $billingInternalCouponsCampaign->getPartnerId()) {
			throw new Exception("internal coupon campaign does not belong to the partner with id = ".$this->partner->getId());
		}
		//ok
		try {
			//START TRANSACTION
			pg_query("BEGIN");
			$billingInternalCoupon->setStockDate($stockDate);
			$billingInternalCoupon = BillingInternalCouponDAO::updateStockDate($billingInternalCoupon);
			//COMMIT
			pg_query("COMMIT");
		} catch(Exception $e) {
			pg_query("ROLLBACK");
			throw $e;
		}
	}
	
}

?>