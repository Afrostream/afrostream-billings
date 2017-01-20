<?php

require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/partners/logista/utils/LogistaIncidentsReportLoader.php';
require_once __DIR__ . '/../../../../libs/partners/logista/utils/LogistaIncidentsResponseReport.php';

class BillingLogistaProcessIncidentsReport {
	
	protected $partner;
	
	
	public function __construct(BillingPartner $partner) {
		$this->partner = $partner;
	}
	
	public function doProcess($incidentsReportFilePath) {
		$logistaIncidentsReportLoader = new LogistaIncidentsReportLoader($incidentsReportFilePath);
		$logistaIncidentsResponseReport = new LogistaIncidentsResponseReport();
		$incidentRecords = $logistaIncidentsReportLoader->getIncidentRecords();
		foreach($incidentRecords as $incidentRecord) {
			try {
				$this->doProcessIncidentRecord($incidentRecord, $logistaIncidentsResponseReport);
			} catch(Exception $e) {
				ScriptsConfig::getLogger()->addError("an error occurred while processing stocks record, message=".$e->getMessage());
			}
		}
		return($logistaIncidentsResponseReport);
	}
	
	private function doProcessIncidentRecord(IncidentRecord $incidentRecord, LogistaIncidentsResponseReport $logistaIncidentsResponseReport) {
		try {
			$billingInternalCoupon = BillingInternalCouponDAO::getBillingInternalCouponById($incidentRecord->getSerialNumber());
			if($billingInternalCoupon == NULL) {
				throw new Exception("no internal coupon found with id = ".$incidentRecord->getSerialNumber());
			}//Some checks before proccessing...
			$billingInternalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($billingInternalCoupon->getInternalCouponsCampaignsId());
			if($billingInternalCouponsCampaign == NULL) {
				throw new Exception("no internal coupon campaign found with id = ".$billingInternalCoupon->getInternalCouponsCampaignsId());
			}
			if($this->partner->getId() != $billingInternalCouponsCampaign->getPartnerId()) {
				throw new Exception("internal coupon campaign does not belong to the partner with id = ".$this->partner->getId());
			}
			//TODO : how to decide response and creditNoteAmount...
		} finally {
			$incidentResponseRecord = new IncidentResponseRecord();
			$incidentResponseRecord->setRecordType('S');
			$incidentResponseRecord->setSerialNumber($incidentRecord->getSerialNumber());
			$incidentResponseRecord->setShopId($incidentRecord->getShopId());
			$incidentResponseRecord->setRequestId($incidentRecord->getRequestId());
			//TODO
			//$incidentResponseRecord->setResponse(???);
			//$incidentResponseRecord->setCreditNoteAmount(???);
			$logistaIncidentsResponseReport->addIncidentResponseRecord($incidentResponseRecord);
			
		}
	}
	
}

?>