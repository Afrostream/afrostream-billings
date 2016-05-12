<?php

class OrangeTVClient {
	
	private $orangeAPIToken = null;
	
	public function __construct($orangeAPIToken) {
		$this->orangeAPIToken = $orangeAPIToken;
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
		$f = fopen("/Users/nelsounet/dev/curl.log", "w+");
		$curl_options[CURLOPT_STDERR] = $f;;
		$curl_options[CURLOPT_VERBOSE] = true;
		$CURL = curl_init();
		curl_setopt_array($CURL, $curl_options);
		$content = curl_exec($CURL);
		$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
		curl_close($CURL);
		fclose($f);
		if($httpCode == 200) {
			$orangeSubscriptionsResponse = new OrangeSubscriptionsResponse($content);
		} else {
			config::getLogger()->addError("API CALL : getting OrangeSubscriptions, code=".$httpCode);
			throw new Exception("API CALL : getting OrangeSubscriptions, code=".$httpCode." is unexpected...");
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