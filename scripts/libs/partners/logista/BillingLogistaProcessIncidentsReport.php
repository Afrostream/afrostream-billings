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
		$logistaIncidentsResponseReport->setProductionDate(new DateTime());
		$incidentRecords = $logistaIncidentsReportLoader->getIncidentRecords();
		foreach($incidentRecords as $incidentRecord) {
			try {
				$this->doProcessIncidentRecord($incidentRecord, $logistaIncidentsResponseReport);
			} catch(Exception $e) {
				ScriptsConfig::getLogger()->addError("an error occurred while processing incident record, message=".$e->getMessage());
			}
		}
		return($logistaIncidentsResponseReport);
	}
	
	private function doProcessIncidentRecord(IncidentRecord $incidentRecord, LogistaIncidentsResponseReport $logistaIncidentsResponseReport) {
		$response = NULL;
		$creditNoteAmount = 0;
		try {
			$billingInternalCoupon = BillingInternalCouponDAO::getBillingInternalCouponById($incidentRecord->getSerialNumber());
			if($billingInternalCoupon == NULL) {
				$response = 'N';
				$msg = "no internal coupon found with id = ".$incidentRecord->getSerialNumber();
				ScriptsConfig::getLogger()->addError($msg);
			} else {
				$billingInternalCouponActionLog = NULL;
				try {
					$billingInternalCouponActionLog = BillingInternalCouponActionLogDAO::addBillingInternalCouponActionLog($billingInternalCoupon->getId(), 'incident_update');
					//Some checks before proccessing...
					$billingInternalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($billingInternalCoupon->getInternalCouponsCampaignsId());
					if($billingInternalCouponsCampaign == NULL) {
						throw new Exception("no internal coupon campaign found with id = ".$billingInternalCoupon->getInternalCouponsCampaignsId());
					}
					if($this->partner->getId() != $billingInternalCouponsCampaign->getPartnerId()) {
						throw new Exception("internal coupon campaign does not belong to the partner with id = ".$this->partner->getId());
					}
					switch($billingInternalCoupon->getStatus()) {
						case 'waiting' :
						case 'pending' :
							$response = 'A';
							$creditNoteAmount = $this->getLinkedInternalPlan($billingInternalCouponsCampaign)->getAmountInCents();
							break;
						case 'redeemed' :
							$response = 'C';
							break;
						case 'expired' :
							$response = 'P';
							break;
						default :
							throw new Exception("internal coupon status unknown : ".$billingInternalCoupon->getStatus());
							break;
					}
					//
					$billingInternalCouponActionLog->setProcessingStatus('done');
					$billingInternalCouponActionLog = BillingInternalCouponActionLogDAO::updateBillingInternalCouponActionLogProcessingStatus($billingInternalCouponActionLog);
					$billingInternalCouponActionLog = NULL;
				} catch(Exception $e) {
					$response = 'I';
					$msg = "an error occurred while processing incident record, message=".$e->getMessage();
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
		} catch(Exception $e) {
			$response = 'I';
			$msg = "an error occurred while processing incident record, message=".$e->getMessage();
			ScriptsConfig::getLogger()->addError($msg);
		} finally {
			$incidentResponseRecord = new IncidentResponseRecord();
			$incidentResponseRecord->setRecordType('S');
			$incidentResponseRecord->setSerialNumber($incidentRecord->getSerialNumber());
			$incidentResponseRecord->setShopId($incidentRecord->getShopId());
			$incidentResponseRecord->setRequestId($incidentRecord->getRequestId());
			$incidentResponseRecord->setResponse($response);
			$incidentResponseRecord->setCreditNoteAmount($creditNoteAmount);
			$logistaIncidentsResponseReport->addIncidentResponseRecord($incidentResponseRecord);
		}
	}
	
	private function getLinkedInternalPlan(BillingInternalCouponsCampaign $internalCouponsCampaign) {
		$billingInternalCouponsCampaignInternalPlans = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlansByInternalCouponsCampaignsId($internalCouponsCampaign->getId());
		if(count($billingInternalCouponsCampaignInternalPlans) == 0) {
			//Exception
			$msg = "no internalPlan associated to internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid();
			ScriptsConfig::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		} else if(count($billingInternalCouponsCampaignInternalPlans) > 1) {
			//Exception
			$msg = "only one internalPlan can be associated to internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid();
			ScriptsConfig::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		$internalPlan = InternalPlanDAO::getInternalPlanById($billingInternalCouponsCampaignInternalPlans[0]->getInternalPlanId());
		return($internalPlan);
	}
	
}

?>