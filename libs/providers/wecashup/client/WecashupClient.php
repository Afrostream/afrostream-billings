<?php

class WecashupClient {
		
	public function __construct() {
	}
	
	public function getTransaction(WecashupTransactionRequest $wecashupTransactionRequest) {
		$url = getEnv('WECASHUP_API_URL').'/'.
				$wecashupValidateTransactionRequest->getMerchantUid().
				'/transactions/'.
				$wecashupValidateTransactionRequest->getTransactionUid().
				'?merchant_public_key='.
				$wecashupValidateTransactionRequest->getMerchantPublicKey();
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
	private $merchantUid = getEnv('WECASHUP_MERCHANT_UID');
	private $merchantPublicKey = getEnv('WECASHUP_MERCHANT_PUBLIC_KEY');
	private $merchantSecret = getEnv('WECASHUP_MERCHANT_SECRET');
	//
	public function __construct() {
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
	}
	
	public function setTransactionUid($transactionUid) {
		$this->transactionUid = $transactionUid;
	}
	
	public function getTransactionUid() {
		return($this->transactionUid);
	}
	
}

class WecashupTransactionResponse {
	
	private $transactionId;
	private $transactionType;
	private $transactionAmount;
	private $transactionAmountSplitted;
	private $transactionDate;
	private $transactionStatus;
	private $transactionCurrency;
	private $transactionSenderId;
	private $transactionReceiverId;
	private $transactionDescription;
	
	public function __construct() {
	}
	
	public static function getInstance(array $response) {
		$out = new WecashupTransactionResponse();
		$out->setTransactionId($response["transaction_id"]);
		$out->setTransactionType($response["transaction_type"]);
		$out->setTransactionAmount($response["transaction_amount"]);
		$out->setTransactionAmountSplitted($response["transaction_amount_splitted"]);
		$out->setTransactionDate($response["transaction_date"]);
		$out->setTransactionStatus($response["transaction_status"]);
		$out->setTransactionCurrency($response["transaction_currency"]);
		$out->setTransactionSenderId($response["transaction_sender_id"]);
		$out->setTransactionReceiverId($response["transaction_receiver_id"]);
		$out->setTransactionDescription($response["transaction_description"]);
	}
	
	public function setTransactionId($transactionId) {
		$this->transactionId = $transactionId;
	}
	
	public function getTransactionId() {
		return($this->transactionId);
	}
	
	public function setTransactionType($transactionType) {
		$this->transactionType = $transactionType;
	}
	
	public function getTransactionType() {
		return($this->transactionType);
	}
	
	public function setTransactionAmount($transactionAmount) {
		$this->transactionAmount = $transactionAmount;
	}
	
	public function getTransactionAmount() {
		return($this->transactionAmount);
	}
	
	public function setTransactionAmountSplitted($transactionAmountSplitted) {
		$this->transactionAmountSplitted = $transactionAmountSplitted;
	}
	
	public function getTransactionAmountSplitted() {
		return($this->transactionAmountSplitted);
	}
	
	public function setTransactionDate($transactionDate) {
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
	
	public function setTransactionCurrency($transactionCurrency) {
		$this->transactionCurrency = $transactionCurrency;
	}
	
	public function getTransactionCurrency() {
		return($this->transactionCurrency);
	}
	
	public function setTransactionSenderId($transactionSenderId) {
		$this->transactionSenderId = $transactionSenderId;
	}
	
	public function getTransactionSenderId() {
		return($this->transactionSenderId);
	}
	
	public function setTransactionReceiverId($transactionReceiverId) {
		$this->transactionReceiverId = $transactionReceiverId;
	}
	
	public function getTransactionReceiverId() {
		return($this->transactionReceiverId);
	}
	
	public function setTransactionDescription($transactionDescription) {
		$this->transactionDescription = $transactionDescription;
	}
	
	public function getTransactionDescription() {
		return($this->transactionDescription);
	}
	
}

class WecashupTransactionsResponse {
	
	private $wecashupTransactionsResponseArray = array();
	
	public function __construct() {
	}
	
	public static function getInstance(array $response) {
		$out = new WecashupTransactionsResponse();
		foreach ($response['transactions'] as $transaction) {
			$out->addWecashupTransactionResponse(WecashupTransactionResponse::getInstance($transaction));
		}
		return($out);
		
	}
	
	public function addWecashupTransactionResponse(WecashupTransactionResponse $wecashupTransactionResponse) {
		$this->wecashupTransactionsResponseArray[] = $wecashupTransactionResponse;
	}
	
	public function getWecashupTransactionsResponseArray() {
		return($this->wecashupTransactionsResponseArray);
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

?>