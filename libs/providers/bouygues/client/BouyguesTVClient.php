<?php

class BouyguesTVClient {
	
	private $cpeid = null;
	
	public function __construct($cpeid) {
		$this->cpeid = $cpeid;
	}
	
	public function getSubscription($subscriptionId) {
		$bouyguesSubscriptionResponse = NULL;
		$url = getEnv('BOUYGUES_TV_API_URL');
		$url.= '/cpeid/@';
		$url.= $this->cpeid;
		$url.= '?ci='.$subscriptionId;
		$curl_options = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER  => false,
		);
		/*if(	null !== (getEnv('PROXY_HOST'))
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
		}*/
		$curl_options[CURLOPT_VERBOSE] = true;
		$CURL = curl_init();
		curl_setopt_array($CURL, $curl_options);
		$content = curl_exec($CURL);
		$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
		curl_close($CURL);
		if($httpCode == 200) {
			$bouyguesSubscriptionResponse = new BouyguesSubscriptionResponse($content);
			$bouyguesSubscription = $bouyguesSubscriptionResponse->getBouyguesSubscription();
			if($bouyguesSubscription == NULL) {
				config::getLogger()->addError("API CALL : getting BouyguesSubscription, No BouyguesSubscription was found");
				throw new BillingsException(new ExceptionType(ExceptionType::provider), "API CALL : getting BouyguesSubscription, No BouyguesSubscription was found", ExceptionError::BOUYGUES_CALL_API_SUBSCRIPTION_NOT_FOUND);	
			}
			$bouyguesSubscription->setSubscriptionId($subscriptionId);
			if($bouyguesSubscription->getResult() != 403) {
				config::getLogger()->addError("API CALL : getting BouyguesSubscription, result <> 403, result=".$bouyguesSubscription->getResult());
				throw new BillingsException(new ExceptionType(ExceptionType::provider), "API CALL : getting BouyguesSubscription, result <> 403, result=".$bouyguesSubscription->getResult(), ExceptionError::BOUYGUES_CALL_API_BAD_RESULT);				
			}
		} else {
			config::getLogger()->addError("API CALL : getting BouyguesSubscription, code=".$httpCode);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), "API CALL : getting BouyguesSubscription, code=".$httpCode." is unexpected...", ExceptionError::BOUYGUES_CALL_API_UNKNOWN_ERROR);
		}
		return($bouyguesSubscriptionResponse);
	}
}

class BouyguesSubscriptionResponse {
	
	private $response = NULL;
	private $bouyguesSubscription = NULL;
	
	public function __construct($response) {
		$xml = simplexml_load_string($response);
		if($xml === false) {
			config::getLogger()->addError("API CALL : getting BouyguesSubscriptionResponse, XML cannot be loaded, response=".(string) $response);
			throw new Exception("API CALL : getting BouyguesSubscriptionResponse, XML cannot be loaded, response=".(string) $response);
		}
		$json = json_encode($xml);
		$this->response = json_decode($json, true);
		$this->bouyguesSubscription = new BouyguesSubscription();
		$this->bouyguesSubscription->setResult($this->response['result']);
		$this->bouyguesSubscription->setResultMessage('SubscribedNotCoupled');
		//TODO : For tests purpose only
		//$this->bouyguesSubscription->setResultMessage($this->response['resultMessage']);
	}
	
	public function getBouyguesSubscription() {
		return($this->bouyguesSubscription);
	}
	
}

class BouyguesSubscription {
	
	private $result = NULL;
	private $resultMessage = NULL;
	private $subscriptionId = NULL;

	public function setSubscriptionId($subscriptionId) {
		$this->subscriptionId = $subscriptionId;
	}
	
	public function getSubscriptionId() {
		return($this->subscriptionId);
	}
	
	public function setResult($result) {
		$this->result = $result;
	}
	
	public function getResult() {
		return($this->result);
	}
	
	public function setResultMessage($resultMessage) {
		$this->resultMessage = $resultMessage;
	}
	
	public function getResultMessage() {
		return($this->resultMessage);
	}
	
}

?>