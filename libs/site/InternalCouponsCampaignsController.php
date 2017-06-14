<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../internalCouponsCampaigns/InternalCouponsCampaignsHandler.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponsCampaignRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetInternalCouponsCampaignsRequest.php';
require_once __DIR__ . '/../providers/global/requests/AddProviderToInternalCouponsCampaignRequest.php';
require_once __DIR__ . '/../providers/global/requests/CreateInternalCouponsCampaignRequest.php';
require_once __DIR__ . '/../providers/global/requests/AddInternalPlanToInternalCouponsCampaignRequest.php';
require_once __DIR__ . '/../providers/global/requests/RemoveInternalPlanFromInternalCouponsCampaignRequest.php';
require_once __DIR__ . '/../providers/global/requests/GenerateInternalCouponsRequest.php';
require_once __DIR__ . '/../providers/global/requests/UpdateInternalCouponsCampaignRequest.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class InternalCouponsCampaignsController extends BillingsController {
	
	public function getMulti(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$couponsCampaignType = NULL;
			if(isset($data['couponsCampaignType'])) {
				$couponsCampaignType = $data['couponsCampaignType'];
			}
			$internalCouponsCampaignsHandler = new InternalCouponsCampaignsHandler();
			$getInternalCouponsCampaignsRequest = new GetInternalCouponsCampaignsRequest();
			$getInternalCouponsCampaignsRequest->setCouponsCampaignType($couponsCampaignType);
			$getInternalCouponsCampaignsRequest->setOrigin('api');
			$internalCouponsCampaigns = $internalCouponsCampaignsHandler->doGetInternalCouponsCampaigns($getInternalCouponsCampaignsRequest);
			return($this->returnObjectAsJson($response, 'couponsCampaigns', $internalCouponsCampaigns));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting internalCouponsCampaigns, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting internalCouponsCampaigns, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function getOne(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$couponsCampaignInternalBillingUuid = NULL;
			if(!isset($args['couponsCampaignInternalBillingUuid'])) {
				//exception
				$msg = "field 'couponsCampaignInternalBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$couponsCampaignInternalBillingUuid = $args['couponsCampaignInternalBillingUuid'];
			$internalCouponsCampaignsHandler = new InternalCouponsCampaignsHandler();
			$getInternalCouponsCampaignRequest = new GetInternalCouponsCampaignRequest();
			$getInternalCouponsCampaignRequest->setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid);
			$getInternalCouponsCampaignRequest->setOrigin('api');
			$internalCouponsCampaign = $internalCouponsCampaignsHandler->doGetInternalCouponsCampaign($getInternalCouponsCampaignRequest);
			
			if($internalCouponsCampaign == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'couponsCampaign', $internalCouponsCampaign));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting an internalCouponsCampaign, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting an internalCouponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function addToProvider(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['couponsCampaignInternalBillingUuid'])) {
				//exception
				$msg = "field 'couponsCampaignInternalBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$couponsCampaignInternalBillingUuid = $args['couponsCampaignInternalBillingUuid'];
			if(!isset($args['providerName'])) {
				//exception
				$msg = "field 'providerName' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerName = $args['providerName'];
			//
			$internalCouponsCampaignsHandler = new InternalCouponsCampaignsHandler();
			$addProviderToInternalCouponsCampaignRequest = new AddProviderToInternalCouponsCampaignRequest();
			$addProviderToInternalCouponsCampaignRequest->setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid);
			$addProviderToInternalCouponsCampaignRequest->setProviderName($providerName);
			$addProviderToInternalCouponsCampaignRequest->setOrigin('api');
			$couponsCampaign = $internalCouponsCampaignsHandler->doAddToProvider($addProviderToInternalCouponsCampaignRequest);
			return($this->returnObjectAsJson($response, 'couponsCampaign', $couponsCampaign));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while linking an internalCouponsCampaign to a provider, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while linking an internalCouponsCampaign to a provider, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function create(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			$name = NULL;
			if(!isset($data['name'])) {
				//Exception
				$msg = "field 'name' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$name = $data['name'];
			$description = NULL;
			if(!isset($data['description'])) {
				//Exception
				$msg = "field 'description' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$description = $data['description'];
			$prefix = NULL;
			if(!isset($data['prefix'])) {
				//Exception
				$msg = "field 'prefix' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$prefix = $data['prefix'];
			$discountType = NULL;
			if(!isset($data['discountType'])) {
				//Exception
				$msg = "field 'discountType' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$discountType = $data['discountType'];
			$amountInCents = NULL;//CAN BE NULL
			if(isset($data['amountInCents'])) {
				$amountInCents = $data['amountInCents'];
			}
			$currency = NULL;//CAN BE NULL
			if(isset($data['currency'])) {
				$currency = $data['currency'];
			}
			$percent = NULL;//CAN BE NULL
			if(isset($data['percent'])) {
				$percent = $data['percent'];
			}
			$discountDuration = NULL;//CAN BE NULL
			if(isset($data['discountDuration'])) {
				$discountDuration = $data['discountDuration'];
			}
			$discountDurationUnit = NULL;//CAN BE NULL
			if(isset($data['discountDurationUnit'])) {
				$discountDurationUnit = $data['discountDurationUnit'];
			}
			$discountDurationLength = NULL;//CAN BE NULL
			if(isset($data['discountDurationLength'])) {
				$discountDurationLength = $data['discountDurationLength'];
			}
			$generatedMode = NULL;
			if(!isset($data['generatedMode'])) {
				//Exception
				$msg = "field 'generatedMode' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$generatedMode = $data['generatedMode'];
			$generatedCodeLength = NULL;
			if(isset($data['generatedCodeLength'])) {
				$generatedCodeLength = $data['generatedCodeLength'];
			}
			$totalNumber = NULL;//CAN BE NULL
			if(isset($data['totalNumber'])) {
				$totalNumber = $data['totalNumber'];
			}
			$couponsCampaignType = NULL;
			if(!isset($data['couponsCampaignType'])) {
				//Exception
				$msg = "field 'couponsCampaignType' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$couponsCampaignType = $data['couponsCampaignType'];
			$timeframes = NULL;
			if(!isset($data['couponsCampaignTimeframes'])) {
				//Exception
				$msg = "field 'couponsCampaignTimeframes' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!is_array($data['couponsCampaignTimeframes'])) {
				//Exception
				$msg = "field 'couponsCampaignTimeframes' must be an array";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$timeframes = $data['couponsCampaignTimeframes'];
			$emailsEnabled = false;
			if(isset($data['emailEnabled'])) {
				$emailsEnabled = $data['emailEnabled'] == 'true' ? true : false;
			}
			$maxRedemptionsByUser = NULL;
			if(!isset($data['maxRedemptionsByUser'])) {
				//Exception
				$msg = "field 'maxRedemptionsByUser' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$maxRedemptionsByUser = $data['maxRedemptionsByUser'];
			$expiresDate = NULL;
			if(array_key_exists('expiresDate', $data)) {
				$expiresDateStr = $data['expiresDate'];
				if($expiresDateStr !== NULL) {
					$expiresDate = DateTime::createFromFormat(DateTime::ISO8601, $expiresDateStr);
					if($expiresDate === false) {
						$msg = "expiresDate date : ".$expiresDateStr." cannot be parsed";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
				}
			}
			//
			$createInternalCouponsCampaignRequest = new CreateInternalCouponsCampaignRequest();
			$createInternalCouponsCampaignRequest->setOrigin('api');
			//
			$createInternalCouponsCampaignRequest->setName($name);
			$createInternalCouponsCampaignRequest->setDescription($description);
			$createInternalCouponsCampaignRequest->setPrefix($prefix);
			$createInternalCouponsCampaignRequest->setDiscountType($discountType);
			$createInternalCouponsCampaignRequest->setAmountInCents($amountInCents);
			$createInternalCouponsCampaignRequest->setCurrency($currency);
			$createInternalCouponsCampaignRequest->setPercent($percent);
			$createInternalCouponsCampaignRequest->setDiscountDuration($discountDuration);
			$createInternalCouponsCampaignRequest->setDiscountDurationUnit($discountDurationUnit);
			$createInternalCouponsCampaignRequest->setDiscountDurationLength($discountDurationLength);
			$createInternalCouponsCampaignRequest->setGeneratedMode($generatedMode);
			$createInternalCouponsCampaignRequest->setGeneratedCodeLength($generatedCodeLength);
			$createInternalCouponsCampaignRequest->setTotalNumber($totalNumber);
			$createInternalCouponsCampaignRequest->setCouponsCampaignType(new CouponCampaignType($couponsCampaignType));
			foreach ($timeframes as $timeframe) {
				$createInternalCouponsCampaignRequest->addTimeframe(new CouponTimeframe($timeframe));
			}
			$createInternalCouponsCampaignRequest->setEmailsEnabled($emailsEnabled);
			$createInternalCouponsCampaignRequest->setMaxRedemptionsByUser($maxRedemptionsByUser);
			$createInternalCouponsCampaignRequest->setExpiresDate($expiresDate);
			//
			$internalCouponsCampaignsHandler = new InternalCouponsCampaignsHandler();
			$couponsCampaign = $internalCouponsCampaignsHandler->create($createInternalCouponsCampaignRequest);
			return($this->returnObjectAsJson($response, 'couponsCampaign', $couponsCampaign));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while creating an InternalCouponsCampaign, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating an InternalCouponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function addInternalPlan(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['couponsCampaignInternalBillingUuid'])) {
				//exception
				$msg = "field 'couponsCampaignInternalBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$couponsCampaignInternalBillingUuid = $args['couponsCampaignInternalBillingUuid'];
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			$addInternalPlanToInternalCouponsCampaignRequest = new AddInternalPlanToInternalCouponsCampaignRequest();
			$addInternalPlanToInternalCouponsCampaignRequest->setOrigin('api');
			$addInternalPlanToInternalCouponsCampaignRequest->setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid);
			$addInternalPlanToInternalCouponsCampaignRequest->setInternalPlanUuid($internalPlanUuid);
			$internalCouponsCampaignsHandler = new InternalCouponsCampaignsHandler();
			$couponsCampaign = $internalCouponsCampaignsHandler->doAddToInternalPlan($addInternalPlanToInternalCouponsCampaignRequest);
			return($this->returnObjectAsJson($response, 'couponsCampaign', $couponsCampaign));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while adding an InternaPlan to an InternalCouponsCampaign, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while adding an InternaPlan to an InternalCouponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function removeInternalPlan(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['couponsCampaignInternalBillingUuid'])) {
				//exception
				$msg = "field 'couponsCampaignInternalBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$couponsCampaignInternalBillingUuid = $args['couponsCampaignInternalBillingUuid'];
			if(!isset($args['internalPlanUuid'])) {
				//exception
				$msg = "field 'internalPlanUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanUuid = $args['internalPlanUuid'];
			$removeInternalPlanFromInternalCouponsCampaignRequest = new RemoveInternalPlanFromInternalCouponsCampaignRequest();
			$removeInternalPlanFromInternalCouponsCampaignRequest->setOrigin('api');
			$removeInternalPlanFromInternalCouponsCampaignRequest->setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid);
			$removeInternalPlanFromInternalCouponsCampaignRequest->setInternalPlanUuid($internalPlanUuid);
			$internalCouponsCampaignsHandler = new InternalCouponsCampaignsHandler();
			$couponsCampaign = $internalCouponsCampaignsHandler->doRemoveFromInternalPlan($removeInternalPlanFromInternalCouponsCampaignRequest);
			return($this->returnObjectAsJson($response, 'couponsCampaign', $couponsCampaign));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while removing an InternaPlan to an InternalCouponsCampaign, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while removing an InternaPlan to an InternalCouponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function generateInternalCoupons(Request $request, Response $response, array $args) {
		try {
			if(!isset($args['couponsCampaignInternalBillingUuid'])) {
				//exception
				$msg = "field 'couponsCampaignInternalBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$couponsCampaignInternalBillingUuid = $args['couponsCampaignInternalBillingUuid'];
			//
			$timeframes = $data['couponsCampaignTimeframes'];
			
			$internalCouponsCampaignsHandler = new InternalCouponsCampaignsHandler();
			$generateInternalCouponsRequest = new GenerateInternalCouponsRequest();
			$generateInternalCouponsRequest->setCouponsCampaignInternalBillingUuid($couponsCampaignInternalBillingUuid);
			$generateInternalCouponsRequest->setOrigin('api');
			$couponsCampaign = $internalCouponsCampaignsHandler->doGenerateInternalCoupons($generateInternalCouponsRequest);
			return($this->returnObjectAsJson($response, 'couponsCampaign', $couponsCampaign));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while generating internalCoupons, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while generating internalCoupons, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function update(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			$updateInternalCouponsCampaignRequest = new UpdateInternalCouponsCampaignRequest();
			$updateInternalCouponsCampaignRequest->setOrigin('api');
			if(!isset($args['couponsCampaignInternalBillingUuid'])) {
				//exception
				$msg = "field 'couponsCampaignInternalBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$updateInternalCouponsCampaignRequest->setCouponsCampaignInternalBillingUuid($args['couponsCampaignInternalBillingUuid']);
			if(isset($data['name'])) {
				$updateInternalCouponsCampaignRequest->setName($data['name']);
			}
			if(isset($data['description'])) {
				$updateInternalCouponsCampaignRequest->setDescription($data['description']);
			}
			if(isset($data['emailsEnabled'])) {
				$updateInternalCouponsCampaignRequest->setEmailsEnabled($data['emailsEnabled'] === true ? true : false);
			}
			if(isset($data['couponsCampaignTimeframes'])) {
				if(!is_array($data['couponsCampaignTimeframes'])) {
					//Exception
					$msg = "field 'couponsCampaignTimeframes' must be an array";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				$timeframes = $data['couponsCampaignTimeframes'];
				$timeframesSize = count($timeframes);
				if($timeframesSize == 0) {
					//exception
					$msg = "at least one timeframe must be provided";
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
				foreach ($timeframes as $timeframe) {
					$updateInternalCouponsCampaignRequest->addTimeframe(new CouponTimeframe($timeframe));
				}
			}
			if(isset($data['maxRedemptionsByUser'])) {
				$updateInternalCouponsCampaignRequest->setMaxRedemptionsByUser($data['maxRedemptionsByUser']);
			}
			if(isset($data['totalNumber'])) {
				$updateInternalCouponsCampaignRequest->setTotalNumber($data['totalNumber']);
			}
			if(isset($data['generatedCodeLength'])) {
				$updateInternalCouponsCampaignRequest->setGeneratedCodeLength($data['generatedCodeLength']);
			}
			if(array_key_exists('expiresDate', $data)) {
				$expiresDateStr = $data['expiresDate'];
				$expiresDate = NULL;
				if($expiresDateStr !== NULL) {
					$expiresDate = DateTime::createFromFormat(DateTime::ISO8601, $expiresDateStr);
					if($expiresDate === false) {
						$msg = "expiresDate date : ".$expiresDateStr." cannot be parsed";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
				}
				$updateInternalCouponsCampaignRequest->setExpiresDate($expiresDate);
			}
			$internalCouponsCampaignsHandler = new InternalCouponsCampaignsHandler();
			$couponsCampaign = $internalCouponsCampaignsHandler->doUpdateInternalCouponsCampaign($updateInternalCouponsCampaignRequest);
			return($this->returnObjectAsJson($response, 'couponsCampaign', $couponsCampaign));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while updating an InternalCouponsCampaign, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating an InternalCouponsCampaign, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
		
}

?>