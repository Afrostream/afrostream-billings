<?php

class WecashupClient {
		
	public function __construct() {
	}
	
	public function getTransaction(WecashupTransactionRequest $wecashupTransactionRequest) {
		$url = getEnv('WECASHUP_API_URL').'/'.
				$wecashupTransactionRequest->getMerchantUid().
				'/transactions/'.
				$wecashupTransactionRequest->getTransactionUid().
				'?merchant_public_key='.
				$wecashupTransactionRequest->getMerchantPublicKey().
				'&merchant_secret='.
				$wecashupTransactionRequest->getMerchantSecret();
		config::getLogger()->addInfo("WECASHUP-GETTRANSACTION-REQUEST=".$url);
		$curl_options = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER  => false,
		);
		$CURL = curl_init();
		curl_setopt_array($CURL, $curl_options);
		$content = curl_exec($CURL);
		$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
		curl_close($CURL);
		config::getLogger()->addInfo("WECASHUP-GETTRANSACTION-RESPONSE=".$content);
		$wecashupTransactionResponse = NULL;
		if($httpCode == 200) {
			$data = json_decode($content, true);
			$wecashupTransactionsResponse = WecashupTransactionsResponse::getInstance($data);
		} else {
			throw new Exception("WECASHUP-GETTRANSACTION API CALL, code=".$httpCode." is unexpected...");
		}
		return($wecashupTransactionsResponse);
	}
	
	public function validateTransaction(WecashupValidateTransactionRequest $wecashupValidateTransactionRequest) {
		$url = getEnv('WECASHUP_API_URL').'/'.
				$wecashupValidateTransactionRequest->getMerchantUid().
				'/transactions/'.
				$wecashupValidateTransactionRequest->getTransactionUid().
				'?merchant_public_key='.
				$wecashupValidateTransactionRequest->getMerchantPublicKey();
		$fields = array(
				'merchant_secret' => urlencode($wecashupValidateTransactionRequest->getMerchantSecret()),
				'transaction_token' => urlencode($wecashupValidateTransactionRequest->getTransactionToken()),
				'transaction_uid' => urlencode($wecashupValidateTransactionRequest->getTransactionUid()),
				'transaction_confirmation_code' => urlencode($wecashupValidateTransactionRequest->getTransactionConfirmationCode()),
				'transaction_provider_name' => urlencode($wecashupValidateTransactionRequest->getTransactionProviderName()),
				'_method' => urlencode('PATCH')
		);
		$fields_string = '';
		foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
		rtrim($fields_string, '&');
		
		config::getLogger()->addInfo("WECASHUP-VALIDATETRANSACTION-REQUEST=".$fields_string);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_VERBOSE);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, count($fields));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		$server_output = curl_exec($ch);
		
		config::getLogger()->addInfo("WECASHUP-VALIDATETRANSACTION-RESPONSE=".$server_output);
		
		curl_close($ch);
		
		$data = json_decode($server_output, true);
		
		$wecashupValidateTransactionResponse = new WecashupValidateTransactionResponse();
		$wecashupValidateTransactionResponse->setResponseStatus($data['response_status']);
		$wecashupValidateTransactionResponse->setResponseCode($data['response_code']);
		return($wecashupValidateTransactionResponse);
	}
	
}

class WecashupRequest {
	//
	private $merchantUid;
	private $merchantPublicKey;
	private $merchantSecret;
	//
	public function __construct() {
		$this->merchantUid = getEnv('WECASHUP_MERCHANT_UID');
		$this->merchantPublicKey = getEnv('WECASHUP_MERCHANT_PUBLIC_KEY');
		$this->merchantSecret = getEnv('WECASHUP_MERCHANT_SECRET');
	}
	
	public function getMerchantUid() {
		return($this->merchantUid);
	}
	
	public function getMerchantPublicKey() {
		return($this->merchantPublicKey);
	}
	
	public function getMerchantSecret() {
		return($this->merchantSecret);
	}
	
}

class WecashupTransactionRequest extends WecashupRequest {
	//
	private $transactionUid;
	//
	public function __construct() {
		parent::__construct();
	}
	
	public function setTransactionUid($transactionUid) {
		$this->transactionUid = $transactionUid;
	}
	
	public function getTransactionUid() {
		return($this->transactionUid);
	}
	
}

class WecashupTransactionResponse {
	
	private $transactionUid;
	private $transactionType;
	private $transactionSenderTotalAmount;
	private $transactionSenderSplittedAmount;
	private $transactionDate;
	private $transactionStatus;
	private $transactionSenderCurrency;
	private $transactionSenderUid;
	private $transactionReceiverUid;
	private $transactionProviderMode;
	private $transactionProviderCommunicationMode;
	private $transactionParentUid;
	private $transactionSenderCountryCodeIso2;
	private $transactionSenderLang;
	private $transactionToken;
	private $transactionReceiverReference;
	private $transactionConversionRate;
	private $transactionProviderName;
	private $transactionReceiverTotalAmount;
	private $transactionConfirmationCode;
	private $transactionSenderReference;
	private $transactionReceiverCurrency;
	
	public function __construct() {
	}
	
	public static function getInstance(array $response) {
		$out = new WecashupTransactionResponse();
		$out->setTransactionUid($response["transaction_uid"]);
		$out->setTransactionType($response["transaction_type"]);
		$out->setTransactionSenderTotalAmount($response["transaction_sender_total_amount"]);
		$out->setTransactionSenderSplittedAmount($response["transaction_sender_splitted_amount"]);
		$transactionDate = DateTime::createFromFormat('Y-m-d\TH:i:s.uO', $response["date"]);
		if($transactionDate === false) {
			$msg = "transaction date : ".$response["date"]." cannot be parsed, using current time";
			config::getLogger()->addError($msg);
			$transactionDate = new DateTime();
		}
		$out->setTransactionDate($transactionDate);
		$out->setTransactionStatus($response["transaction_status"]);
		$out->setTransactionSenderCurrency($response["transaction_sender_currency"]);
		$out->setTransactionSenderUid($response["transaction_sender_uid"]);
		$out->setTransactionReceiverUid($response["transaction_receiver_uid"]);
		$out->setTransactionProviderMode($response["transaction_provider_mode"]);
		$out->setTransactionProviderCommunicationMode($response["transaction_provider_communication_mode"]);
		$out->setTransactionProviderName($response["transaction_provider_name"]);
		$out->setTransactionParentUid($response["transaction_parent_uid"]);
		$out->setTransactionSenderCountryCodeIso2($response["transaction_sender_country_code_iso2"]);
		$out->setTransactionToken($response["transaction_token"]);
		$out->setTransactionSenderLang($response["transaction_sender_lang"]);
		$out->setTransactionReceiverReference($response["transaction_receiver_reference"]);
		$out->setTransactionConversionRate($response["transaction_conversion_rate"]);
		$out->setTransactionReceiverTotalAmount($response["transaction_receiver_total_amount"]);
		$out->setTransactionConfirmationCode($response["transaction_confirmation_code"]);
		$out->setTransactionSenderReference($response["transaction_sender_reference"]);
		$out->setTransactionReceiverCurrency($response["transaction_receiver_currency"]);
	}
	
	public function setTransactionUid($transactionUid) {
		$this->transactionUid = $transactionUid;
	}
	
	public function getTransactionUid() {
		return($this->transactionUid);
	}
	
	public function setTransactionType($transactionType) {
		$this->transactionType = $transactionType;
	}
	
	public function getTransactionType() {
		return($this->transactionType);
	}
	
	public function setTransactionSenderTotalAmount($transactionSenderTotalAmount) {
		$this->transactionSenderTotalAmount = $transactionSenderTotalAmount;
	}
	
	public function getTransactionSenderTotalAmount() {
		return($this->transactionSenderTotalAmount);
	}
	
	public function setTransactionSenderSplittedAmount($transactionSenderSplittedAmount) {
		$this->transactionSenderSplittedAmount = $transactionSenderSplittedAmount;
	}
	
	public function getTransactionSenderSplittedAmount() {
		return($this->transactionSenderSplittedAmount);
	}
	
	public function setTransactionDate(DateTime $transactionDate) {
		$this->transactionDate = $transactionDate;
	}
	
	public function getTransactionDate() {
		return($this->transactionDate);
	}
	
	public function setTransactionStatus($transactionStatus) {
		$this->transactionStatus = $transactionStatus;
	}
	
	public function getTransactionStatus() {
		return($this->transactionStatus);
	}
	
	public function setTransactionSenderCurrency($transactionSenderCurrency) {
		$this->transactionSenderCurrency = $transactionSenderCurrency;
	}
	
	public function getTransactionSenderCurrency() {
		return($this->transactionSenderCurrency);
	}
	
	public function setTransactionSenderUid($transactionSenderUid) {
		$this->transactionSenderUid = $transactionSenderUid;
	}
	
	public function getTransactionSenderUid() {
		return($this->transactionSenderUid);
	}
	
	public function setTransactionReceiverUid($transactionReceiverUid) {
		$this->transactionReceiverUid = $transactionReceiverUid;
	}
	
	public function getTransactionReceiverUid() {
		return($this->transactionReceiverUid);
	}
	
	public function setTransactionProviderMode($transactionProviderMode) {
		$this->transactionProviderMode = $transactionProviderMode;
	}
	
	public function getTransactionProviderMode() {
		return($this->transactionProviderMode);
	}
	
	public function setTransactionProviderCommunicationMode($transactionProviderCommunicationMode) {
		$this->transactionProviderCommunicationMode = $transactionProviderCommunicationMode;
	}
	
	public function getTransactionProviderCommunicationMode() {
		return($this->transactionProviderCommunicationMode);
	}

	public function setTransactionProviderName($transactionProviderName) {
		$this->transactionProviderName = $transactionProviderName;
	}
	
	public function getTransactionProviderName() {
		return($this->transactionProviderName);
	}
	
	public function setTransactionParentUid($transactionParentUid) {
		$this->transactionParentUid = $transactionParentUid;
	}
	
	public function getTransactionParentUid() {
		return($this->transactionParentUid);
	}
	
	public function setTransactionSenderCountryCodeIso2($transactionSenderCountryCodeIso2) {
		if(isset($transactionSenderCountryCodeIso2)) {
			$this->transactionSenderCountryCodeIso2 = strtoupper($transactionSenderCountryCodeIso2);
		}
	}
	
	public function getTransactionSenderCountryCodeIso2() {
		return($this->transactionSenderCountryCodeIso2);
	}
	
	public function setTransactionToken($transactionToken) {
		$this->transactionToken = $transactionToken;
	}
	
	public function getTransactionToken() {
		return($this->transactionToken);
	}
	
	public function setTransactionSenderLang($transactionSenderLang) {
		$this->transactionSenderLang = $transactionSenderLang;
	}
	
	public function getTransactionSenderLang() {
		return($this->transactionSenderLang);
	}
	
	public function setTransactionReceiverReference($transactionReceiverReference) {
		$this->transactionReceiverReference = $transactionReceiverReference;
	}
	
	public function getTransactionReceiverReference() {
		return($this->transactionReceiverReference);
	}

	public function setTransactionConversionRate($transactionConversionRate) {
		$this->transactionConversionRate = $transactionConversionRate;
	}
	
	public function getTransactionConversionRate() {
		return($this->transactionConversionRate);
	}
	
	public function setTransactionReceiverTotalAmount($transactionReceiverTotalAmount) {
		$this->transactionReceiverTotalAmount = $transactionReceiverTotalAmount;
	}
	
	public function getTransactionReceiverTotalAmount() {
		return($this->transactionReceiverTotalAmount);
	}
	
	public function setTransactionConfirmationCode($transactionConfirmationCode) {
		$this->transactionConfirmationCode = $transactionConfirmationCode;
	}
	
	public function getTransactionConfirmationCode() {
		return($this->transactionConfirmationCode);
	}
	
	public function setTransactionSenderReference($transactionSenderReference) {
		$this->transactionSenderReference = $transactionSenderReference;
	}
	
	public function getTransactionSenderReference() {
		return($this->transactionSenderReference);
	}
	
	public function setTransactionReceiverCurrency($transactionReceiverCurrency) {
		$this->transactionReceiverCurrency = $transactionReceiverCurrency;
	}
	
	public function getTransactionReceiverCurrency() {
		return($this->transactionReceiverCurrency);
	}
	
}

class WecashupTransactionsResponse {
	
	private $responseDetails;
	private $responseCode;
	private $responseStatus;
	private $wecashupTransactionsResponseArray = array();
	
	public function __construct() {
	}
	
	public static function getInstance(array $response) {
		$out = new WecashupTransactionsResponse();
		$out->setResponseDetails($response['response_details']);
		if(in_array('response_content', $response)) {
			$responseContent = $response['response_content'];
			foreach ($responseContent['transactions'] as $transaction) {
				$out->addWecashupTransactionResponse(WecashupTransactionResponse::getInstance($transaction));
			}
		}
		$out->setResponseCode($response['response_code']);
		$out->setResponseStatus($response['response_status']);
		return($out);
	}
	
	public function addWecashupTransactionResponse(WecashupTransactionResponse $wecashupTransactionResponse) {
		$this->wecashupTransactionsResponseArray[] = $wecashupTransactionResponse;
	}
	
	public function getWecashupTransactionsResponseArray() {
		return($this->wecashupTransactionsResponseArray);
	}
	
	public function setResponseDetails($responseDetails) {
		$this->responseDetails = $responseDetails;
	}
	
	public function getResponseDetails() {
		return($this->responseDetails);
	}
	
	public function setResponseStatus($responseStatus) {
		$this->responseStatus = $responseStatus;
	}
	
	public function getResponseStatus() {
		return($this->responseStatus);
	}
	
	public function setResponseCode($responseCode) {
		$this->responseCode = $responseCode;
	}
	
	public function getResponseCode() {
		return($this->responseCode);
	}
	
}

class WecashupValidateTransactionRequest extends WecashupRequest {

	private $transactionUid;
	private $transactionToken;
	private $transactionConfirmationCode;
	private $transactionProviderName;
	//
	public function __construct() {
	}
	
	public function setTransactionUid($transactionUid) {
		$this->transactionUid = $transactionUid;
	}
	
	public function getTransactionUid() {
		return($this->transactionUid);
	}
	
	public function setTransactionToken($transactionToken) {
		$this->transactionToken = $transactionToken;
	}
	
	public function getTransactionToken() {
		return($this->transactionToken);
	}
	
	public function setTransactionConfirmationCode($transactionConfirmationCode) {
		$this->transactionConfirmationCode = $transactionConfirmationCode;
	}
	
	public function getTransactionConfirmationCode() {
		return($this->transactionConfirmationCode);
	}

	public function setTransactionProviderName($transactionProviderName) {
		$this->transactionProviderName = $transactionProviderName;
	}
	
	public function getTransactionProviderName() {
		return($this->transactionProviderName);
	}
	
}

class WecashupValidateTransactionResponse {
	
	private $responseStatus;
	private $responseCode;

	public function __construct() {
	}
	
	public function setResponseStatus($responseStatus) {
		$this->responseStatus = $responseStatus;
	}
	
	public function getResponseStatus() {
		return($this->responseStatus);
	}
	
	public function setResponseCode($responseCode) {
		$this->responseCode = $responseCode;
	}
	
	public function getResponseCode() {
		return($this->responseCode);
	}
	
}

class WecashupCustomerResponse {
	
	public function __construct() {
	}
	
}

?>