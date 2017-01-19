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
		$billingPartnerOrderProcessingLog  = NULL;
		try {
		 	config::getLogger()->addInfo("processing a ".$this->partner->getName()." partnerOrder...");
		 	$billingPartnerOrderProcessingLog = BillingPartnerOrderProcessingLogDAO::addBillingPartnerOrderProcessingLog($billingPartnerOrder->getId());
		 	$billingPartnerOrder->setProcessingStatus('processing');
		 	BillingPartnerOrderDAO::updateProcessingStatus($billingPartnerOrder);
		 	//Get Coupons
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
		 	//Generate locally CSVs
		 	$partnerOrderCSVs = $this->generatePartnerOrderCSVs($billingPartnerOrder, $billingPartnerOrderInternalCouponsCampaignLink, $processPartnerOrderRequest);
		 	//Upload CSVs
		 	$this->uploadPartnerOrderCSVs($billingPartnerOrder, $billingPartnerOrderInternalCouponsCampaignLink, $partnerOrderCSVs, $processPartnerOrderRequest);
		 	//Done
		 	$billingPartnerOrder->setProcessingStatus('processed');
		 	BillingPartnerOrderDAO::updateProcessingStatus($billingPartnerOrder);
		 	$billingPartnerOrderProcessingLog->setProcessingStatus('processed');
		 	BillingPartnerOrderProcessingLogDAO::updateBillingPartnerOrderProcessingLogProcessingStatus($billingPartnerOrderProcessingLog);
		 	config::getLogger()->addInfo("processing a ".$this->partner->getName()." partnerOrder done successfully");
		 	$billingPartnerOrderProcessingLog = NULL;
		 } catch(BillingsException $e) {
		 	$billingPartnerOrder->setProcessingStatus('error');
		 	BillingPartnerOrderDAO::updateProcessingStatus($billingPartnerOrder);
		 	if(isset($billingPartnerOrderProcessingLog)) {
		 		$billingPartnerOrderProcessingLog->setProcessingStatus('error');
		 		$billingPartnerOrderProcessingLog->setMessage($e->getMessage());
		 	}
		 	$msg = "a billings exception occurred while processing a ".$this->partner->getName()." partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("processing a ".$this->partner->getName()."partnerOrder failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$billingPartnerOrder->setProcessingStatus('error');
			BillingPartnerOrderDAO::updateProcessingStatus($billingPartnerOrder);
			if(isset($billingPartnerOrderProcessingLog)) {
				$billingPartnerOrderProcessingLog->setProcessingStatus('error');
				$billingPartnerOrderProcessingLog->setMessage($e->getMessage());
			}
			$msg = "an unknown exception occurred while processing a ".$this->partner->getName()." partnerOrder, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("processing a ".$this->partner->getName()." partnerOrder failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		} finally {
			if(isset($billingPartnerOrderProcessingLog)) {
				BillingPartnerOrderProcessingLogDAO::updateBillingPartnerOrderProcessingLogProcessingStatus($billingPartnerOrderProcessingLog);
			}
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
		$sizeLimit = getEnv('PARTNER_ORDERS_LOGISTA_FILE_SIZE_LIMIT');
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
				$result[getEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_PREFIX').getEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_ID').'_'.$billingPartnerOrder->getId().'_'.$indice.'.txt'] = $current_csv_file_path;
				//fill header line
				$headerfields = array();
				$headerfields[] = 'E';//TYPE_ENREG
				$headerfields[] = $billingPartnerOrder->getId();//NUM_CMD
				$headerfields[] = $indice;//INDICE
				$headerfields[] = ($sizeLimit > 0) ? ceil($totalCounter / $sizeLimit) : 1;//NB_FIC_CMD
				$headerfields[] = $totalCounter;//NB_CODES
				$headerfields[] = $now_as_str;//DATE_CREAT
				$headerfields[] = $now_as_str;//DATE_ENVOI
				fputcsv($current_csv_file_res, $headerfields, $csvDelimiter, $csvEnclosure);
				//fill body line
				$bodyFields = array();
				$bodyFields[] = 'C';
				$bodyFields[] = $internalCouponsCampaignOpts->getOpt('internalEAN');//CODE_ARTICLE (EAN)
				$bodyFields[] = ($sizeLimit > 0) ? min($sizeLimit, $totalCounter - $currentCounter) : $totalCounter;//QUANTITE
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
			if($sizeLimit > 0 && $size == $sizeLimit) {
				fclose($current_csv_file_res);
				$current_csv_file_res = NULL;
				$current_csv_file_path = NULL;
				$size = 0;
				$indice++;
			}
		}
		//close last file if any :
		if($current_csv_file_res != NULL) {
			fclose($current_csv_file_res);
			$current_csv_file_res = NULL;
			$current_csv_file_path = NULL;
		}
		return($result);
	}
	
	private function uploadPartnerOrderCSVs(BillingPartnerOrder $billingPartnerOrder,
			BillingPartnerOrderInternalCouponsCampaignLink $billingPartnerOrderInternalCouponsCampaignLink,
			array $partnerOrderCSVs, 
			ProcessPartnerOrderRequest $processPartnerOrderRequest) {
		//Init openPGP
		$key = OpenPGP_Message::parse(OpenPGP::unarmor(file_get_contents(getEnv('PARTNER_ORDERS_LOGISTA_PUBLIC_KEY_FILE')), "PGP PUBLIC KEY BLOCK"));
		//Init S3
		$s3 = S3Client::factory(array(
				'region' => getEnv('AWS_REGION'),
				'version' => getEnv('AWS_VERSION')));
		$bucket = getEnv('AWS_BUCKET_BILLINGS_EXPORTS');
		$partnerOrderCSVBaseKey = getEnv('AWS_ENV').'/'.'partners'.'/'.$this->partner->getName().'/'.'orders'.'/'.$billingPartnerOrder->getId().'/'.time();
		foreach ($partnerOrderCSVs as $partnerOrderCSVName => $partnerOrderCSVPath) {
			//ENCRYPT FILE
			$partnerOrderCSVEncryptedPath = NULL;
			if(($partnerOrderCSVEncryptedPath = tempnam('', 'tmp')) === false) {
				throw new Exception('encrypted csv file cannot be created');
			}
			//see : https://github.com/singpolyma/openpgp-php/issues/19 for a sample
			$data = new OpenPGP_LiteralDataPacket(file_get_contents($partnerOrderCSVPath), array('format' => 'u', 'filename' => $partnerOrderCSVName));
			$encrypted = OpenPGP_Crypt_Symmetric::encrypt($key, new OpenPGP_Message(array($data)));
			if(file_put_contents($partnerOrderCSVEncryptedPath, OpenPGP::enarmor($encrypted->to_bytes(), "PGP MESSAGE")) === false) {
				throw new Exception('encrypted csv file cannot be filled');
			}
			$partnerOrderCSVKey = $partnerOrderCSVBaseKey.'/'.$partnerOrderCSVName;
			$partnerOrderCSVEncryptedKey = $partnerOrderCSVBaseKey.'/'.'encrypted'.'/'.$partnerOrderCSVName;
			//UPLOAD ORIGINAL FILE TO AMAZON
			$s3->putObject(array(
					'Bucket' => $bucket,
					'Key' => $partnerOrderCSVKey,
					'SourceFile' => $partnerOrderCSVPath
			));
			//UPLOAD ENCRYPTED FILE TO AMAZON
			$s3->putObject(array(
					'Bucket' => $bucket,
					'Key' => $partnerOrderCSVEncryptedKey,
					'SourceFile' => $partnerOrderCSVEncryptedPath
			));
			//UPLOAD ENCRYPTED FILE TO FTP
			$url = "ftp://".getEnv('PARTNER_ORDERS_LOGISTA_FTP_USER');
			$url.= ":".getEnv('PARTNER_ORDERS_LOGISTA_FTP_PWD');
			$url.= "@".getEnv('PARTNER_ORDERS_LOGISTA_FTP_HOST').":".getEnv('PARTNER_ORDERS_LOGISTA_FTP_PORT');
			$url.= "/".getEnv('PARTNER_ORDERS_LOGISTA_FTP_FOLDER_OUT')."/".$partnerOrderCSVName;
			//
			$fp = fopen($partnerOrderCSVEncryptedPath, 'r');
			$curl_options = array(
					CURLOPT_URL => $url,
					CURLOPT_UPLOAD => true,
					CURLOPT_INFILE => $fp,
					CURLOPT_INFILESIZE => filesize($partnerOrderCSVEncryptedPath)
			);
			$curl_options[CURLOPT_PROTOCOLS] = CURLPROTO_FTP;
			$curl_options[CURLOPT_VERBOSE] = true;
			$CURL = curl_init();
			curl_setopt_array($CURL, $curl_options);
			//
			$result = curl_exec($CURL);
			$curl_error_message = curl_error($CURL);
			$curl_error_code = curl_errno($CURL);
			curl_close($CURL);
			fclose($fp);
			$fp = NULL;
			if($result == false) {
				//Exception
				throw new Exception("curl exception, error_message=".$curl_error_message.", error_code=".$curl_error_code);
			}
			//done
			unlink($partnerOrderCSVPath);
			$partnerOrderCSVPath = NULL;
			unlink($partnerOrderCSVEncryptedPath);
			$partnerOrderCSVEncryptedPath = NULL;
		}
	}
	
}

?>