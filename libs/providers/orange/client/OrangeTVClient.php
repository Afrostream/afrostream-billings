<?php

class OrangeTVClient {
	
	private $orangeAPIToken = null;
	
	public function __construct($orangeAPIToken) {
		self::checkOrangeAPIToken($orangeAPIToken);
		$this->orangeAPIToken = $orangeAPIToken;
	}
	
	/*	
	 * sample : B64UydUgJByFfJ6xZkgkJCt9kKQA9hXc6DyDvA4DSDriF6TMtEtuUvUCDXqmsgMZu4gUTEIXLfcuX3lQO81klg90nCoyxmg/zJ/FqufE1Nxvsk=
	 * |MCO=OFR
	 * |sau=3
	 * |ted=1468156418
	 * |tcd=1462886018
	 * |f7lul5v/LE+/xOk9gsw406Yx+T4=
	 * 
	 */
	
	private static function checkOrangeAPIToken($orangeAPIToken) {
		$exploded_array = explode("|", $orangeAPIToken);
		$ted = NULL;
		$tcd = NULL;
		foreach ($exploded_array as $key_value) {
			$key_value_array = explode("=", $key_value);
			if(count($key_value_array) >= 2) {
				if($key_value_array[0] == "ted") {
					$ted = $key_value_array[1];
				} else if($key_value_array[0] == "tcd") {
					$tcd = $key_value_array[1];
				}
			}
		}
		if($ted == NULL) {
			config::getLogger()->addError("OrangeAPIToken malformed, ted parameter was not found");
			throw new BillingsException(new ExceptionType(ExceptionType::internal), "OrangeAPIToken malformed, ted parameter was not found", ExceptionError::ORANGE_CALL_API_MALFORMED_TOKEN);
		}
		$now = new DateTime();
		$ted_as_datetime = new DateTime();
		$ted_as_datetime->setTimestamp($ted);
		if($ted_as_datetime < $now) {
			config::getLogger()->addError("OrangeAPIToken has expired");
			throw new BillingsException(new ExceptionType(ExceptionType::internal), "OrangeAPIToken has expired", ExceptionError::ORANGE_CALL_API_EXPIRED_TOKEN);			
		}
	}
	
	public function getSubscriptions($subscriptionID = NULL) {
		$orangeSubscriptionsResponse = NULL;
		$url = getEnv('ORANGE_TV_API_URL');
		$url.= '/tvprofile/subscriptions';
		if(isset($subscriptionID)) {
			$url.= '?id='.$subscriptionID;
		}
		$curl_options = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER  => false,
				CURLOPT_HTTPHEADER => array(
						'Content-Type: application/json; charset=utf-8',
						'OrangeAPIToken : ' .$this->orangeAPIToken
				),
		);
		if(	null !== (getEnv('PROXY_HOST'))
				&&
			null !== (getEnv('PROXY_PORT'))
		) {
			$curl_options[CURLOPT_PROXY] = getEnv('PROXY_HOST');
			$curl_options[CURLOPT_PROXYPORT] = getEnv('PROXY_PORT');
		}
		if(	null !== (getEnv('PROXY_USER'))
				&&
			null !== (getEnv('PROXY_PWD'))
		) {
			$curl_options[CURLOPT_PROXYUSERPWD] = getEnv('PROXY_USER').":".getEnv('PROXY_PWD');
		}
		if(	null !== (getEnv('ORANGE_TV_HTTP_AUTH_USER'))
			&&
			null !== (getEnv('ORANGE_TV_HTTP_AUTH_PWD'))
		) {
			$curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
			$curl_options[CURLOPT_USERPWD] = getEnv('ORANGE_TV_HTTP_AUTH_USER').":".getEnv('ORANGE_TV_HTTP_AUTH_PWD');
		}
		$curl_options[CURLOPT_VERBOSE] = true;
		$CURL = curl_init();
		curl_setopt_array($CURL, $curl_options);
		$content = curl_exec($CURL);
		$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
		curl_close($CURL);
		if($httpCode == 200) {
			$orangeSubscriptionsResponse = new OrangeSubscriptionsResponse($content);
		} else if($httpCode == 401) {
			config::getLogger()->addError("ORANGETV API CALL : getting OrangeSubscriptions, code=".$httpCode.", Invalid Token");
			throw new BillingsException(new ExceptionType(ExceptionType::provider), "ORANGETV API CALL : getting OrangeSubscriptions, code=".$httpCode.", Invalid Token", ExceptionError::ORANGE_CALL_API_INVALID_TOKEN);
		} else if($httpCode == 403) {
			config::getLogger()->addError("ORANGETV API CALL : getting OrangeSubscriptions, code=".$httpCode.", Invalid Request");
			throw new BillingsException(new ExceptionType(ExceptionType::provider), "ORANGETV API CALL : getting OrangeSubscriptions, code=".$httpCode.", Invalid Request", ExceptionError::ORANGE_CALL_API_INVALID_REQUEST);	
		} else {
			config::getLogger()->addError("ORANGETV API CALL : getting OrangeSubscriptions, code=".$httpCode);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), "ORANGETV API CALL : getting OrangeSubscriptions, code=".$httpCode." is unexpected...", ExceptionError::ORANGE_CALL_API_UNKNOWN_ERROR);
		}
		return($orangeSubscriptionsResponse);
	}
}

class OrangeSubscriptionsResponse {
	
	private $response = NULL;
	private $orangeSubscriptions = array();
	
	public function __construct($response) {
		$this->response = json_decode($response, true);
		foreach($this->response['user']['tvprofile']['subscriptions'] as $sub) {
			$orangeSubscription = new OrangeSubscription();
			$orangeSubscription->setId($sub['id']);
			$orangeSubscription->setStatus($sub['status']);
			$this->addOrangeSubscription($orangeSubscription);
		}
	}
		
	public function addOrangeSubscription(OrangeSubscription $orangeSubscription)
	{
		$this->orangeSubscriptions[$orangeSubscription->getId()] = $orangeSubscription;
	}
	
	public function getOrangeSubscriptionById($subscriptionID) {
		return($this->orangeSubscriptions[$subscriptionID]);
	}
	
	public function getOrangeSubscriptions() {
		return($this->orangeSubscriptions);
	}
	
}

class OrangeSubscription {
	
	private $id = NULL;
	private $status = NULL;

	public function setId($id) {
		$this->id = $id;
	}
	
	public function getId() {
		return($this->id);
	}
	
	public function setStatus($status) {
		$this->status = $status;
	}
	
	public function getStatus() {
		return($this->status);
	}
	
}

?>