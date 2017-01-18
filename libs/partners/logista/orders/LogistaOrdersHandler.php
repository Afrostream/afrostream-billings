<?php

require_once __DIR__ . '/../../global/orders/PartnerOrdersHandler.php';

use Aws\S3\S3Client;

class LogistaOrdersHandler extends PartnerOrdersHandler {
	
	public function doCreatePartnerOrder(CreatePartnerOrderRequest $createPartnerOrderRequest) {
		if(!in_array($createPartnerOrderRequest->getPartnerOrderType(), ['coupons'])) {
			throw new BillingsException(new ExceptionType(ExceptionType::internal), "type : ".$createPartnerOrderRequest->getPartnerOrderType()." is not supported");
		}
		return(parent::doCreatePartnerOrder($createPartnerOrderRequest));
	}
	
	public function doAddInternalCouponsCampaignToPartnerOrder(BillingPartnerOrder $billingPartnerOrder,
			BillingInternalCouponsCampaign $billingInternalCouponsCampaign,
			AddInternalCouponsCampaignToPartnerOrderRequest $addInternalCouponsCampaignToPartnerOrderRequest) {
		$billingPartnerOrderInternalCouponsCampaignLinks = BillingPartnerOrderInternalCouponsCampaignLinkDAO::getBillingPartnerOrderInternalCouponsCampaignLinksByPartnerOrderId($billingPartnerOrder->getId());
		if(count($billingPartnerOrderInternalCouponsCampaignLinks) > 0) {
			throw new BillingsException(new ExceptionType(ExceptionType::internal), "an internalCouponsCampaign is already linked to the partnerOrder");
		}
		return(parent::doAddInternalCouponsCampaignToPartnerOrder($billingPartnerOrder, $billingInternalCouponsCampaign, $addInternalCouponsCampaignToPartnerOrderRequest));
	}
	
	public function doBookPartnerOrder(BillingPartnerOrder $billingPartnerOrder,
			BookPartnerOrderRequest $bookPartnerOrderRequest) {
		return(parent::doBookPartnerOrder($billingPartnerOrder, $bookPartnerOrderRequest));
	}
	
	public function doReadyPartnerOrder(BillingPartnerOrder $billingPartnerOrder,
			ReadyPartnerOrderRequest $readyPartnerOrderRequest) {
		return(parent::doReadyPartnerOrder($billingPartnerOrder, $readyPartnerOrderRequest));
	}
	
	public function doProcessPartnerOrder(BillingPartnerOrder $billingPartnerOrder,
			ProcessPartnerOrderRequest $processPartnerOrderRequest) {
		try {
		 	config::getLogger()->addInfo("processing a ".$this->partner->getName()." partnerOrder...");
		 	//TODO : processingStatus = 'processing'
		 	//TODO : processingLOG
		 	//TODO : Get Coupons
		 	$billingPartnerOrderInternalCouponsCampaignLinks = BillingPartnerOrderInternalCouponsCampaignLinkDAO::getBillingPartnerOrderInternalCouponsCampaignLinksByPartnerOrderId($billingPartnerOrder->getId());
		 	if(count($billingPartnerOrderInternalCouponsCampaignLinks) == 0) {
		 		//Exception
		 		throw new BillingsException(new ExceptionType(ExceptionType::internal), "no campaign linked to the partnerOrder");
		 	}
		 	if(count($billingPartnerOrderInternalCouponsCampaignLinks) > 1) {
		 		//Exception
		 		throw new BillingsException(new ExceptionType(ExceptionType::internal), "only one campaign can be linked to the partnerOrder");
		 	}
		 	$billingPartnerOrderInternalCouponsCampaignLink = $billingPartnerOrderInternalCouponsCampaignLinks[0];
		 	//Generate CSVs
		 	$partnerOrderCSVs = $this->generatePartnerOrderCSVs($billingPartnerOrder, $billingPartnerOrderInternalCouponsCampaignLink, $processPartnerOrderRequest);
		 	//Generate CSVs
		 	//then encrypt CSVs
		 	//PUT the readable CSVs and the encrypted CSVs on AMAZON
		 	$this->uploadPartnerOrderCSVs($billingPartnerOrder, $billingPartnerOrderInternalCouponsCampaignLink, $partnerOrderCSVs, $processPartnerOrderRequest);
		 	//PUT encrypted CSVs ON FTP
		 	//TODO : processingStatus = 'done'
		 	//Done
		 	config::getLogger()->addInfo("processing a ".$this->partner->getName()." partnerOrder done successfully");
		 } catch(BillingsException $e) {
			$msg = "a billings exception occurred while processing a ".$this->partner->getName()." partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("processing a ".$this->partner->getName()."partnerOrder failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while processing a ".$this->partner->getName()." partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("processing a ".$this->partner->getName()." partnerOrder failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		} finally {
			//TODO : processingStatus has to be SET
			//TODO : processingLOG
		}
		return($billingPartnerOrder);
	}
	
	private function generatePartnerOrderCSVs(BillingPartnerOrder $billingPartnerOrder, 
			BillingPartnerOrderInternalCouponsCampaignLink $billingPartnerOrderInternalCouponsCampaignLink, 
			ProcessPartnerOrderRequest $processPartnerOrderRequest) {
		$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($billingPartnerOrderInternalCouponsCampaignLink->getInternalCouponsCampaignsId());
		if($internalCouponsCampaign == NULL) {
			//Exception
			throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown internalCouponsCampaign with id : ".$billingPartnerOrderInternalCouponsCampaignLink->getInternalCouponsCampaignsId());			
		}
		if($internalCouponsCampaign->getExpiresDate() == NULL) {
			//Exception
			throw new BillingsException(new ExceptionType(ExceptionType::internal),"expires_date is mandatory");	
		}
		$internalCouponsCampaignOpts = BillingInternalCouponsCampaignOptsDAO::getBillingInternalCouponsCampaignOptByInternalCouponsCampaignId($internalCouponsCampaign->getId());
		if($internalCouponsCampaignOpts->getOpt('internalEAN') == NULL) {
			//Exception
			throw new BillingsException(new ExceptionType(ExceptionType::internal), "no internalEAN opts key associated with internalCouponsCampaign with uuid : ".$internalCouponsCampaign->getUuid());			
		}
		$internalCoupons = BillingInternalCouponDAO::getBillingInternalCouponsByInternalCouponsCampaignsId($billingPartnerOrderInternalCouponsCampaignLink->getInternalCouponsCampaignsId(), $billingPartnerOrderInternalCouponsCampaignLink->getId());
		$totalCounter = count($internalCoupons);
		if($totalCounter == 0) {
			//Exception
			throw new BillingsException(new ExceptionType(ExceptionType::internal), "only one campaign can be linked to the partnerOrder");
		}
		if($totalCounter != $billingPartnerOrderInternalCouponsCampaignLink->getBookedCounter()) {
			//Exception
			throw new BillingsException(new ExceptionType(ExceptionType::internal), "booked coupons differ from available coupons");			
		}
		$now = new DateTime();
		$now->setTimezone(new DateTimeZone(config::$timezone));
		$now_as_str = $now->format("d/m/Y H:i:s");
		$expiresDate = $internalCouponsCampaign->getExpiresDate();
		$expiresDate->setTimezone(new DateTimeZone(config::$timezone));
		$expiresDate_str = $expiresDate->format("d/m/Y");
		$result = array();
		$indice = 1;
		$size = 0;
		$sizeLimit = 2;
		$currentCounter = 0;
		$csvDelimiter = ';';
		$csvEnclosure = chr(0);
		$current_csv_file_path = NULL;
		$current_csv_file_res = NULL;
		while (($internalCoupon = array_shift($internalCoupons)) !== NULL) {
			if($size == 0) {
				//Create a new file
				if(($current_csv_file_path = tempnam('', 'tmp')) === false) {
					throw new Exception('csv file cannot be created');
				}
				if(($current_csv_file_res = fopen($current_csv_file_path, 'w')) === false) {
					throw new Exception('csv file cannot be opened');
				}
				//add to result
				$result['AFST055'.'_'.$billingPartnerOrder->getId().'_'.$indice.'.txt'] = $current_csv_file_path;
				//fill header line
				$headerfields = array();
				$headerfields[] = 'E';//TYPE_ENREG
				$headerfields[] = $billingPartnerOrder->getId();//NUM_CMD
				$headerfields[] = $indice;//INDICE
				$headerfields[] = ceil($totalCounter / $sizeLimit);//NB_FIC_CMD
				$headerfields[] = $totalCounter;//NB_CODES
				$headerfields[] = $now_as_str;//DATE_CREAT
				$headerfields[] = $now_as_str;//DATE_ENVOI
				fputcsv($current_csv_file_res, $headerfields, $csvDelimiter, $csvEnclosure);
				//fill body line
				$bodyFields = array();
				$bodyFields[] = 'C';
				$bodyFields[] = $internalCouponsCampaignOpts->getOpt('internalEAN');//CODE_ARTICLE (EAN)
				$bodyFields[] = min($sizeLimit, $totalCounter - $currentCounter);//QUANTITE
				fputcsv($current_csv_file_res, $bodyFields, $csvDelimiter, $csvEnclosure);
			}
			//fill current file
			$detailFields = array();
			$detailFields[] = 'D';
			$detailFields[] = $internalCoupon->getId();//NUM_SERIE
			$detailFields[] = $internalCoupon->getCode();//CODE_ERECH
			$detailFields[] = $expiresDate_str;//DATE_VALID
			fputcsv($current_csv_file_res, $detailFields, $csvDelimiter, $csvEnclosure);
			//done
			$size++;
			$currentCounter++;
			if($size == $sizeLimit) {
				fclose($current_csv_file_res);
				$current_csv_file_res = NULL;
				$current_csv_file_path = NULL;
				$size = 0;
				$indice++;
			}
		}
		return($result);
	}
	
	private function uploadPartnerOrderCSVs(BillingPartnerOrder $billingPartnerOrder,
			BillingPartnerOrderInternalCouponsCampaignLink $billingPartnerOrderInternalCouponsCampaignLink,
			array $partnerOrderCSVs, 
			ProcessPartnerOrderRequest $processPartnerOrderRequest) {
		$s3 = S3Client::factory(array(
				'region' => getEnv('AWS_REGION'),
				'version' => getEnv('AWS_VERSION')));
		$bucket = getEnv('AWS_BUCKET_BILLINGS_EXPORTS');
		$partnerOrderCSVBaseKey = getEnv('AWS_ENV').'/'.'partners'.'/'.$this->partner->getName().'/'.'orders'.'/'.$billingPartnerOrder->getId().'-'.time();
		foreach ($partnerOrderCSVs as $partnerOrderCSVName => $partnerOrderCSVPath) {
			$partnerOrderCSVKey = $partnerOrderCSVBaseKey.'/'.$partnerOrderCSVName;
			//UPLOAD NEW FILE
			$s3->putObject(array(
					'Bucket' => $bucket,
					'Key' => $partnerOrderCSVKey,
					'SourceFile' => $partnerOrderCSVPath
			));
			//done
			unlink($partnerOrderCSVPath);
		}
	}
	
}

?>