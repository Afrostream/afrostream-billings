<?php

class NetsizeClient {
		
	public function __construct() {
	}
	
	public function initializeSubscription(InitializeSubscriptionRequest $initializeSubscriptionRequest) {
		$initializeSubscriptionResponse = NULL;
		$url = getEnv('NETSIZE_API_URL');
		$data_string = $initializeSubscriptionRequest->getPost();
		$curl_options = array(
				CURLOPT_URL => $url,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => $data_string,
				CURLOPT_HTTPHEADER => array(
						'Content-Length: ' . strlen($data_string),
						'Content-Type: application/xml'
				),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER  => false
		);
		config::getLogger()->addInfo("NETSIZE-INIT-SUB-REQUEST=".$data_string);
		$curl_options[CURLOPT_VERBOSE] = true;
		$CURL = curl_init();
		curl_setopt_array($CURL, $curl_options);
		$content = curl_exec($CURL);
		$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
		curl_close($CURL);
		config::getLogger()->addInfo("NETSIZE-INIT-SUB-RESPONSE=".$content);
		if($httpCode == 200) {
			$initializeSubscriptionResponse = new InitializeSubscriptionResponse($content);
		} else {
			config::getLogger()->addInfo("NETSIZE API CALL : initialize-subscription, code=".$httpCode);
			$initializeSubscriptionResponse = new InitializeSubscriptionResponse($content);
			//InitializeSubscriptionResponse should throw here an Exception anyway
			throw new BillingsException(new ExceptionType(ExceptionType::provider), "NETSIZE API CALL : initialize-subscription, code=".$httpCode." is unexpected...");				
		}
		return($initializeSubscriptionResponse);
	}
	
	public function getStatus(GetStatusRequest $getStatusRequest) {
		$getStatusResponse = NULL;
		$url = getEnv('NETSIZE_API_URL');
		$data_string = $getStatusRequest->getPost();
		$curl_options = array(
				CURLOPT_URL => $url,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => $data_string,
				CURLOPT_HTTPHEADER => array(
						'Content-Length: ' . strlen($data_string),
						'Content-Type: application/xml'
				),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER  => false
		);
		config::getLogger()->addInfo("NETSIZE-GET-STATUS-REQUEST=".$data_string);
		$curl_options[CURLOPT_VERBOSE] = true;
		$CURL = curl_init();
		curl_setopt_array($CURL, $curl_options);
		$content = curl_exec($CURL);
		$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
		curl_close($CURL);
		config::getLogger()->addInfo("NETSIZE-GET-STATUS-RESPONSE=".$content);
		if($httpCode == 200) {
			$getStatusResponse = new GetStatusResponse($content);
			$getStatusResponse->setTransactionId($getStatusRequest->getTransactionId());
		} else {
			config::getLogger()->addInfo("NETSIZE API CALL : get-status, code=".$httpCode);
			$getStatusResponse = new GetStatusResponse($content);
			//GetStatusResponse should throw here an Exception anyway
			throw new BillingsException(new ExceptionType(ExceptionType::provider), "NETSIZE API CALL : get-status, code=".$httpCode." is unexpected...");
		}
		return($getStatusResponse);
	}
	
	public function closeSubscription(CloseSubscriptionRequest $closeSubscriptionRequest) {
		$closeSubscriptionResponse = NULL;
		$url = getEnv('NETSIZE_API_URL');
		$data_string = $closeSubscriptionRequest->getPost();
		$curl_options = array(
				CURLOPT_URL => $url,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => $data_string,
				CURLOPT_HTTPHEADER => array(
						'Content-Length: ' . strlen($data_string),
						'Content-Type: application/xml'
				),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER  => false
		);
		config::getLogger()->addInfo("NETSIZE-CLOSE-SUB-REQUEST=".$data_string);
		$curl_options[CURLOPT_VERBOSE] = true;
		$CURL = curl_init();
		curl_setopt_array($CURL, $curl_options);
		$content = curl_exec($CURL);
		$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
		curl_close($CURL);
		config::getLogger()->addInfo("NETSIZE-CLOSE-SUB-RESPONSE=".$content);
		if($httpCode == 200) {
			$closeSubscriptionResponse = new CloseSubscriptionResponse($content);
		} else {
			config::getLogger()->addInfo("NETSIZE API CALL : close-subscription, code=".$httpCode);
			$closeSubscriptionResponse = new CloseSubscriptionResponse($content);
			//CloseSubscriptionResponse should throw here an Exception anyway
			throw new BillingsException(new ExceptionType(ExceptionType::provider), "NETSIZE API CALL : close-subscription, code=".$httpCode." is unexpected...");
		}
		return($closeSubscriptionResponse);
	}
	
	public function finalize(FinalizeRequest $finalizeRequest) {
		$finalizeResponse = NULL;
		$url = getEnv('NETSIZE_API_URL');
		$data_string = $finalizeRequest->getPost();
		$curl_options = array(
				CURLOPT_URL => $url,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => $data_string,
				CURLOPT_HTTPHEADER => array(
						'Content-Length: ' . strlen($data_string),
						'Content-Type: application/xml'
				),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER  => false
		);
		config::getLogger()->addInfo("NETSIZE-FINALIZE-REQUEST=".$data_string);
		$curl_options[CURLOPT_VERBOSE] = true;
		$CURL = curl_init();
		curl_setopt_array($CURL, $curl_options);
		$content = curl_exec($CURL);
		$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
		curl_close($CURL);
		config::getLogger()->addInfo("NETSIZE-FINALIZE-RESPONSE=".$content);
		if($httpCode == 200) {
			$finalizeResponse = new FinalizeResponse($content);
			$finalizeResponse->setTransactionId($finalizeRequest->getTransactionId());
		} else {
			config::getLogger()->addInfo("NETSIZE API CALL : finalize, code=".$httpCode);
			$finalizeResponse = new FinalizeResponse($content);
			//FinalizeResponse should throw here an Exception anyway
			throw new BillingsException(new ExceptionType(ExceptionType::provider), "NETSIZE API CALL : finalize, code=".$httpCode." is unexpected...");
		}
		return($finalizeResponse);
	}
	
}

class InitializeSubscriptionRequest {
	
	private $flowId;
	private $subscriptionModelId;
	private $productName;
	private $productType;
	private $productDescription;
	private $countryCode;
	private $languageCode;
	private $merchantUserId;
	private $advancedParams = array();
	
	public function __construct() {
	}
	
	public function setServiceId($serviceId) {
		$this->serviceId = $serviceId;
	}
	
	public function getServiceId() {
		return($this->serviceId);
	}
	
	
	public function setFlowId($flowId) {
		$this->flowId = $flowId;
	}
	
	public function getFlowId() {
		return($this->flowId);
	}
	
	public function setSubscriptionModelId($subscriptionModelId) {
		$this->subscriptionModelId = $subscriptionModelId;
	}
	
	public function getSubscriptionModelId() {
		return($this->subscriptionModelId);
	}
	
	public function setProductName($productName) {
		$this->productName = $productName;
	}
	
	public function getProductName() {
		return($this->productName);
	}
	
	public function setProductType($productType) {
		$this->productType = $productType;
	}
	
	public function getProductType() {
		return($this->productType);
	}
	
	public function setProductDescription($productDescription) {
		$this->productDescription = $productDescription;
	}
	
	public function getProductDescription() {
		return($this->productDescription);
	}
	
	public function setCountryCode($countryCode) {
		$this->countryCode = $countryCode;
	}
	
	public function getCountryCode() {
		return($this->countryCode);
	}
	
	public function setLanguageCode($languageCode) {
		$this->languageCode = $languageCode;
	}
	
	public function getLanguageCode() {
		return($this->languageCode);
	}
	
	public function setMerchantUserId($merchantUserId) {
		$this->merchantUserId = $merchantUserId;
	}
	
	public function getMerchantUserId() {
		return($this->merchantUserId);
	}
	
	public function addAdvancedParam($key, $value) {
		$this->advancedParams[$key] = $value;
	}
	
	public function getPost() {
		$xml = new DOMDocument('1.0', 'UTF-8');
		$requestNode = $xml->createElement('request');
		$requestNode = $xml->appendChild($requestNode);
		$requestNode->setAttribute('type', 'initialize-subscription');
		$requestNode->setAttribute('version', '1.2');
		$requestNode->setAttribute('xmlns', 'http://www.netsize.com/ns/pay/api');
		//method
		$methodNode = $xml->createElement('initialize-subscription');
		$methodNode = $requestNode->appendChild($methodNode);
		$methodNode->setAttribute('auth-key', getEnv('NETSIZE_API_AUTH_KEY'));
		$methodNode->setAttribute('service-id', getEnv('NETSIZE_API_SERVICE_ID'));
		$methodNode->setAttribute('country-code', $this->countryCode);
		$methodNode->setAttribute('language-code', $this->languageCode);
		$methodNode->setAttribute('flow-id', $this->flowId);
		$methodNode->setAttribute('subscription-model-id', $this->subscriptionModelId);
		//product
		$productNode = $xml->createElement('product');
		$productNode = $methodNode->appendChild($productNode);
		$productNode->setAttribute('name', $this->productName);
		$productNode->setAttribute('type', $this->productType);
		$productDescriptionNode = $xml->createElement('description', $this->productDescription);
		$productDescriptionNode = $productNode->appendChild($productDescriptionNode);
		//merchant
		$merchantNode = $xml->createElement('merchant');
		$merchantNode = $methodNode->appendChild($merchantNode);
		$merchantNode->setAttribute('user-id', $this->merchantUserId);
		if(count($this->advancedParams) > 0) {
			$advancedParamsNode = $xml->createElement('advanced-params');
			$advancedParamsNode = $methodNode->appendChild($advancedParamsNode);
			foreach ($this->advancedParams as $key => $value) {
				$advancedParamNode = $xml->createElement('advanced-param');
				$advancedParamNode = $advancedParamsNode->appendChild($advancedParamNode);
				$advancedParamNode->setAttribute('key',$key);
				$advancedParamNode->setAttribute('value', $value);
			}
		}
		return($xml->saveXML());
	}
		
}

class InitializeSubscriptionResponse {
	
	private $response = NULL;
	private $transactionId;
	private $transactionStatusCode;
	private $authUrlUrl;
	
	public function __construct($response) {
		$this->response = simplexml_load_string($response);
		if($this->response === false) {
			config::getLogger()->addError("NETSIZE API CALL : netsize initialize-subscription, XML cannot be loaded, response=".(string) $response);
			throw new Exception("NETSIZE API CALL : getting netsize initialize-subscription, XML cannot be loaded, response=".(string) $response);
		}
		$responseNode = $this->response;
		$responseTypeNode = $responseNode['type'];
		if($responseTypeNode == NULL) {
			throw new Exception("NETSIZE API CALL : getting netsize initialize-subscription, response type attribute not found, response=".(string) $response);
		}
		if($responseTypeNode == 'error') {
			$responseErrorNode = self::getNodeByName($responseNode, 'error');
			if($responseErrorNode == NULL) {
				throw new Exception("NETSIZE API CALL : getting netsize initialize-subscription, an unknown error occurred, response=".(string) $response);
			}
			throw new Exception("NETSIZE API CALL : getting netsize initialize-subscription, an error occurred, code=".$responseErrorNode['code'].", reason=".$responseErrorNode['reason'].", response=".(string) $response);
		}
		$initializeSubscriptionNode = self::getNodeByName($responseNode, 'initialize-subscription');
		if($initializeSubscriptionNode == NULL) {
			throw new Exception("NETSIZE API CALL : getting netsize initialize-subscription, initialize-subscription node not found, response=".(string) $response);
		}
		$this->transactionId = $initializeSubscriptionNode['transaction-id'];
		$transactionStatusNode = self::getNodeByName($initializeSubscriptionNode, 'transaction-status');
		if($transactionStatusNode == NULL) {
			throw new Exception("NETSIZE API CALL : getting netsize initialize-subscription, transaction-status node not found, response=".(string) $response);
		}
		$this->transactionStatusCode = $transactionStatusNode['code'];
		$authUrlNode = self::getNodeByName($initializeSubscriptionNode, 'auth-url');
		if($authUrlNode == NULL) {
			throw new Exception("NETSIZE API CALL : getting netsize initialize-subscription, auth-url node not found, response=".(string) $response);
		}
		$this->authUrlUrl = $authUrlNode['url'];
	}
	
	public function setTransactionId($transactionId) {
		$this->transactionId = $transactionId;
	}
	
	public function getTransactionId() {
		return($this->transactionId);
	}
	
	public function setTransactionStatusCode($transactionStatusCode) {
		$this->transactionStatusCode = $transactionStatusCode;
	}
	
	public function getTransactionStatusCode() {
		return($this->transactionStatusCode);
	}
	
	public function setAuthUrlUrl($authUrlUrl) {
		$this->authUrlUrl = $authUrlUrl;
	}
	
	public function getAuthUrlUrl() {
		return($this->authUrlUrl);
	}
	
	private static function getNodeByName(SimpleXMLElement $node, $name) {
		foreach ($node->children() as $children) {
			if($children->getName() == $name) {
				return($children);
			}
		}
		return(NULL);
	}
	
}

class GetStatusRequest {
	
	private $transactionId;
	
	public function __construct() {
	}
	
	public function setTransactionId($transactionId) {
		$this->transactionId = $transactionId;
	}
	
	public function getTransactionId() {
		return($this->transactionId);
	}
	
	public function getPost() {
		$xml = new DOMDocument('1.0', 'UTF-8');
		$requestNode = $xml->createElement('request');
		$requestNode = $xml->appendChild($requestNode);
		$requestNode->setAttribute('type', 'get-status');
		$requestNode->setAttribute('version', '1.2');
		$requestNode->setAttribute('xmlns', 'http://www.netsize.com/ns/pay/api');
		//method
		$methodNode = $xml->createElement('get-status');
		$methodNode = $requestNode->appendChild($methodNode);
		$methodNode->setAttribute('auth-key', getEnv('NETSIZE_API_AUTH_KEY'));
		//$methodNode->setAttribute('service-id', getEnv('NETSIZE_API_SERVICE_ID'));
		$methodNode->setAttribute('transaction-id', $this->transactionId);
		return($xml->saveXML());
	}
	
}

class GetStatusResponse {
	
	private $transactionId;
	
	private $response = NULL;
	private $transactionStatusCode;
	//
	private $lastTransactionErrorCode;
	private $lastTransactionErrorReason;
	//
	private $userIdType;
	private $userId;
	private $providerId;
	
	public function __construct($response) {
		$this->response = simplexml_load_string($response);
		if($this->response === false) {
			config::getLogger()->addError("NETSIZE API CALL : netsize get-status, XML cannot be loaded, response=".(string) $response);
			throw new Exception("NETSIZE API CALL : getting netsize get-status, XML cannot be loaded, response=".(string) $response);
		}
		$responseNode = $this->response;
		$responseTypeNode = $responseNode['type'];
		if($responseTypeNode == NULL) {
			throw new Exception("NETSIZE API CALL : getting netsize get-status, response type attribute not found, response=".(string) $response);
		}
		if($responseTypeNode == 'error') {
			$responseErrorNode = self::getNodeByName($responseNode, 'error');
			if($responseErrorNode == NULL) {
				throw new Exception("NETSIZE API CALL : getting netsize get-status, an unknown error occurred, response=".(string) $response);
			}
			throw new Exception("NETSIZE API CALL : getting netsize get-status, an error occurred, code=".$responseErrorNode['code'].", reason=".$responseErrorNode['reason'].", response=".(string) $response);
		}
		$getStatusNode = self::getNodeByName($responseNode, 'get-status');
		if($getStatusNode == NULL) {
			throw new Exception("NETSIZE API CALL : getting netsize get-status, get-status node not found, response=".(string) $response);
		}
		$transactionStatusNode = self::getNodeByName($getStatusNode, 'transaction-status');
		if($transactionStatusNode == NULL) {
			throw new Exception("NETSIZE API CALL : getting netsize get-status, transaction-status node not found, response=".(string) $response);
		}
		$this->transactionStatusCode = $transactionStatusNode['code'];
		$lastTransactionErrorNode = self::getNodeByName($getStatusNode, 'last-transaction-error');
		if(isset($lastTransactionErrorNode)) {
			$this->lastTransactionErrorCode = $lastTransactionErrorNode['code'];
			$this->lastTransactionErrorReason = $lastTransactionErrorNode['reason'];
		}
		$this->userIdType = $getStatusNode['user-id-type'];
		$this->userId = $getStatusNode['user-id'];
		$this->providerId = $getStatusNode['provider-id'];
	}
	
	public function setTransactionId($transactionId) {
		$this->transactionId = $transactionId;
	}
	
	public function getTransactionId() {
		return($this->transactionId);
	}
	
	public function setTransactionStatusCode($transactionStatusCode) {
		$this->transactionStatusCode = $transactionStatusCode;
	}
	
	public function getTransactionStatusCode() {
		return($this->transactionStatusCode);
	}
	
	public function setLastTransactionErrorCode($lastTransactionErrorCode) {
		$this->lastTransactionErrorCode = $lastTransactionErrorCode;
	}
	
	public function getLastTransactionErrorCode() {
		return($this->lastTransactionErrorCode);
	}
	
	public function setLastTransactionErrorReason($lastTransactionErrorReason) {
		$this->lastTransactionErrorReason = $lastTransactionErrorReason;
	}
	
	public function getLastTransactionErrorReason() {
		return($this->lastTransactionErrorReason);
	}
	
	public function setUserIdType($userIdType) {
		$this->userIdType = $userIdType;
	}
	
	public function getUserIdType() {
		return($this->userIdType);
	}
	
	public function setUserId($userId) {
		$this->userId = $userId;
	}
	
	public function getUserId() {
		return($this->userId);
	}
	
	public function setProviderId($provderId) {
		$this->providerId = $provderId;
	}
	
	public function getProviderId() {
		return($this->providerId);
	}
		
	private static function getNodeByName(SimpleXMLElement $node, $name) {
		foreach ($node->children() as $children) {
			if($children->getName() == $name) {
				return($children);
			}
		}
		return(NULL);
	}
	
}

class CloseSubscriptionRequest {
	
	private $transactionId;
	private $trigger;
	private $returnUrl;
	
	public function __construct() {
	}
	
	public function setTransactionId($transactionId) {
		$this->transactionId = $transactionId;
	}
	
	public function getTransactionId() {
		return($this->transactionId);
	}
	
	public function setTrigger($trigger) {
		$this->trigger = $trigger;
	}
	
	public function getTrigger() {
		return($this->trigger);
	}
	
	public function setReturnUrl($returnUrl) {
		$this->returnUrl = $returnUrl;
	}
	
	public function getReturnUrl() {
		return($this->returnUrl);
	}
	
	public function getPost() {
		$xml = new DOMDocument('1.0', 'UTF-8');
		$requestNode = $xml->createElement('request');
		$requestNode = $xml->appendChild($requestNode);
		$requestNode->setAttribute('type', 'close-subscription');
		$requestNode->setAttribute('version', '1.2');
		$requestNode->setAttribute('xmlns', 'http://www.netsize.com/ns/pay/api');
		//method
		$methodNode = $xml->createElement('close-subscription');
		$methodNode = $requestNode->appendChild($methodNode);
		$methodNode->setAttribute('auth-key', getEnv('NETSIZE_API_AUTH_KEY'));
		//$methodNode->setAttribute('service-id', getEnv('NETSIZE_API_SERVICE_ID'));
		$methodNode->setAttribute('transaction-id', $this->transactionId);
		$methodNode->setAttribute('trigger', $this->trigger);
		$methodNode->setAttribute('return-url', $this->returnUrl);
		return($xml->saveXML());
	}
	
}

class CloseSubscriptionResponse {
	
	private $response = NULL;
	private $transactionStatusCode;
	//
	private $lastTransactionErrorCode;
	private $lastTransactionErrorReason;
	//
	private $closeUrl;
	
	public function __construct($response) {
		$this->response = simplexml_load_string($response);
		if($this->response === false) {
			config::getLogger()->addError("NETSIZE API CALL : netsize close-subscription, XML cannot be loaded, response=".(string) $response);
			throw new Exception("NETSIZE API CALL : getting netsize close-subscription, XML cannot be loaded, response=".(string) $response);
		}
		$responseNode = $this->response;
		$responseTypeNode = $responseNode['type'];
		if($responseTypeNode == NULL) {
			throw new Exception("NETSIZE API CALL : getting netsize close-subscription, response type attribute not found, response=".(string) $response);
		}
		if($responseTypeNode == 'error') {
			$responseErrorNode = self::getNodeByName($responseNode, 'error');
			if($responseErrorNode == NULL) {
				throw new Exception("NETSIZE API CALL : getting netsize close-subscription, an unknown error occurred, response=".(string) $response);
			}
			throw new Exception("NETSIZE API CALL : getting netsize close-subscription, an error occurred, code=".$responseErrorNode['code'].", reason=".$responseErrorNode['reason'].", response=".(string) $response);
		}
		$closeSubscriptionNode = self::getNodeByName($responseNode, 'close-subscription');
		if($closeSubscriptionNode == NULL) {
			throw new Exception("NETSIZE API CALL : getting netsize close-subscription, close-subscription node not found, response=".(string) $response);
		}
		$transactionStatusNode = self::getNodeByName($closeSubscriptionNode, 'transaction-status');
		if($transactionStatusNode == NULL) {
			throw new Exception("NETSIZE API CALL : getting netsize close-subscription, transaction-status node not found, response=".(string) $response);
		}
		$this->transactionStatusCode = $transactionStatusNode['code'];
		$lastTransactionErrorNode = self::getNodeByName($closeSubscriptionNode, 'last-transaction-error');
		if(isset($lastTransactionErrorNode)) {
			$this->lastTransactionErrorCode = $lastTransactionErrorNode['code'];
			$this->lastTransactionErrorReason = $lastTransactionErrorNode['reason'];
		}
		$this->closeUrl = $getStatusNode['close-url'];
	}
	
	public function setTransactionStatusCode($transactionStatusCode) {
		$this->transactionStatusCode = $transactionStatusCode;
	}
	
	public function getTransactionStatusCode() {
		return($this->transactionStatusCode);
	}
	
	public function setLastTransactionErrorCode($lastTransactionErrorCode) {
		$this->lastTransactionErrorCode = $lastTransactionErrorCode;
	}
	
	public function getLastTransactionErrorCode() {
		return($this->lastTransactionErrorCode);
	}
	
	public function setLastTransactionErrorReason($lastTransactionErrorReason) {
		$this->lastTransactionErrorReason = $lastTransactionErrorReason;
	}
	
	public function getLastTransactionErrorReason() {
		return($this->lastTransactionErrorReason);
	}
	
	public function setCloseUrl($closeUrl) {
		$this->closeUrl = $closeUrl;
	}
	
	public function getCloseUrl() {
		return($this->closeUrl);
	}
		
	private static function getNodeByName(SimpleXMLElement $node, $name) {
		foreach ($node->children() as $children) {
			if($children->getName() == $name) {
				return($children);
			}
		}
		return(NULL);
	}
	
}

class FinalizeRequest {


	private $transactionId;
	
	public function __construct() {
	}
	
	public function setTransactionId($transactionId) {
		$this->transactionId = $transactionId;
	}
	
	public function getTransactionId() {
		return($this->transactionId);
	}
	
	public function getPost() {
		$xml = new DOMDocument('1.0', 'UTF-8');
		$requestNode = $xml->createElement('request');
		$requestNode = $xml->appendChild($requestNode);
		$requestNode->setAttribute('type', 'finalize');
		$requestNode->setAttribute('version', '1.2');
		$requestNode->setAttribute('xmlns', 'http://www.netsize.com/ns/pay/api');
		//method
		$methodNode = $xml->createElement('finalize');
		$methodNode = $requestNode->appendChild($methodNode);
		$methodNode->setAttribute('auth-key', getEnv('NETSIZE_API_AUTH_KEY'));
		//$methodNode->setAttribute('service-id', getEnv('NETSIZE_API_SERVICE_ID'));
		$methodNode->setAttribute('transaction-id', $this->transactionId);
		return($xml->saveXML());
	}
	
}

class FinalizeResponse {

	private $transactionId;

	private $response = NULL;
	private $transactionStatusCode;
	//
	private $lastTransactionErrorCode;
	private $lastTransactionErrorReason;
	//

	public function __construct($response) {
		$this->response = simplexml_load_string($response);
		if($this->response === false) {
			config::getLogger()->addError("NETSIZE API CALL : netsize finalize, XML cannot be loaded, response=".(string) $response);
			throw new Exception("NETSIZE API CALL : getting netsize finalize, XML cannot be loaded, response=".(string) $response);
		}
		$responseNode = $this->response;
		$responseTypeNode = $responseNode['type'];
		if($responseTypeNode == NULL) {
			throw new Exception("NETSIZE API CALL : getting netsize finalize, response type attribute not found, response=".(string) $response);
		}
		if($responseTypeNode == 'error') {
			$responseErrorNode = self::getNodeByName($responseNode, 'error');
			if($responseErrorNode == NULL) {
				throw new Exception("NETSIZE API CALL : getting netsize finalize, an unknown error occurred, response=".(string) $response);
			}
			throw new Exception("NETSIZE API CALL : getting netsize finalize, an error occurred, code=".$responseErrorNode['code'].", reason=".$responseErrorNode['reason'].", response=".(string) $response);
		}
		$getStatusNode = self::getNodeByName($responseNode, 'finalize');
		if($getStatusNode == NULL) {
			throw new Exception("NETSIZE API CALL : getting netsize , finalize node not found, response=".(string) $response);
		}
		$transactionStatusNode = self::getNodeByName($getStatusNode, 'transaction-status');
		if($transactionStatusNode == NULL) {
			throw new Exception("NETSIZE API CALL : getting netsize finalize, transaction-status node not found, response=".(string) $response);
		}
		$this->transactionStatusCode = $transactionStatusNode['code'];
		$lastTransactionErrorNode = self::getNodeByName($getStatusNode, 'last-transaction-error');
		if(isset($lastTransactionErrorNode)) {
			$this->lastTransactionErrorCode = $lastTransactionErrorNode['code'];
			$this->lastTransactionErrorReason = $lastTransactionErrorNode['reason'];
		}
	}

	public function setTransactionId($transactionId) {
		$this->transactionId = $transactionId;
	}

	public function getTransactionId() {
		return($this->transactionId);
	}

	public function setTransactionStatusCode($transactionStatusCode) {
		$this->transactionStatusCode = $transactionStatusCode;
	}

	public function getTransactionStatusCode() {
		return($this->transactionStatusCode);
	}

	public function setLastTransactionErrorCode($lastTransactionErrorCode) {
		$this->lastTransactionErrorCode = $lastTransactionErrorCode;
	}

	public function getLastTransactionErrorCode() {
		return($this->lastTransactionErrorCode);
	}

	public function setLastTransactionErrorReason($lastTransactionErrorReason) {
		$this->lastTransactionErrorReason = $lastTransactionErrorReason;
	}

	public function getLastTransactionErrorReason() {
		return($this->lastTransactionErrorReason);
	}
	
	private static function getNodeByName(SimpleXMLElement $node, $name) {
		foreach ($node->children() as $children) {
			if($children->getName() == $name) {
				return($children);
			}
		}
		return(NULL);
	}

}


?>