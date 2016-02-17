<?php

require_once __DIR__ . '/BillingsApiResponse.php';

class BillingsApiSubscriptions {
	
	private $billingsApiClient = NULL;
	
	public function __construct(BillingsApiClient $billingsApiClient) {
		$this->billingsApiClient = $billingsApiClient;
	}
	
	public function update(ApiUpdateSubscriptionsRequest $apiUpdateSubscriptionsRequest) {
		$apiSubscriptions = NULL;
		$url = $apiUpdateSubscriptionsRequest->getUrl();
		$data_string = $apiUpdateSubscriptionsRequest->getPut();
		$curl_options = array(
				CURLOPT_URL => $url,
				CURLOPT_CUSTOMREQUEST => 'PUT',
				CURLOPT_POSTFIELDS => $data_string,
				CURLOPT_HTTPHEADER => array(
						'Content-Type: application/json',
						'Content-Length: ' . strlen($data_string)
				),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER  => false,
				CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
				CURLOPT_USERPWD => getEnv('BILLINGS_API_HTTP_AUTH_USER').":".getEnv('BILLINGS_API_HTTP_AUTH_PWD')
		);
		$CURL = curl_init();
		curl_setopt_array($CURL, $curl_options);
		$content = curl_exec($CURL);
		$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
		curl_close($CURL);
		if($httpCode == 404) {
			//OK : NOT FOUND, but...
			$billingsApiResponse = new BillingsApiResponse($content, 'subscriptions');
			if($billingsApiResponse->getStatus() == 'error') {
				BillingsApiClientConfig::getLogger()->addInfo("API CALL : updating subscriptions, code=404 NOT FOUND");
			} else {
				throw new Exception("API CALL : updating subscriptions, code=404 NOT FOUND but no status given");
			}
		} else if($httpCode == 200) {
			//OK ?
			$billingsApiResponse = new BillingsApiResponse($content, 'subscriptions');
			if($billingsApiResponse->getStatus() == 'done') {
				BillingsApiClientConfig::getLogger()->addInfo("API CALL : updating subscriptions, code=200 OK");
				$apiSubscriptions = $billingsApiResponse->getObject();
			} else {
				throw new Exception("API CALL : updating subscriptions, code=200 OK,"
						." but status=".$billingsApiResponse->getStatus().","
						." statusCode=".$billingsApiResponse->getStatusCode().","
						." statusMessage=".$billingsApiResponse->getStatusMessage());
			}
		} else {
			BillingsApiClientConfig::getLogger()->addError("API CALL : updating subscriptions, code=".$httpCode);
			throw new Exception("API CALL : updating subscriptions, code=".$httpCode." is unexpected...");
		}
		return($apiSubscriptions);
	}
	
	public function getMulti(ApiGetSubscriptionsRequest $apiGetSubscriptionsRequest) {
		$apiSubscriptions = NULL;
		BillingsApiClientConfig::getLogger()->addInfo("API CALL : getting subscriptions...");
		$url = $apiGetSubscriptionsRequest->getUrl();
		$curl_options = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER  => false,
				CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
				CURLOPT_USERPWD => getEnv('BILLINGS_API_HTTP_AUTH_USER').":".getEnv('BILLINGS_API_HTTP_AUTH_PWD')
		);
		$CURL = curl_init();
		curl_setopt_array($CURL, $curl_options);
		$content = curl_exec($CURL);
		$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
		curl_close($CURL);
		if($httpCode == 404) {
			//OK : NOT FOUND, but...
			$billingsApiResponse = new BillingsApiResponse($content, 'subscriptions');
			if($billingsApiResponse->getStatus() == 'error') {
				BillingsApiClientConfig::getLogger()->addInfo("API CALL : getting subscriptions, code=404 NOT FOUND");
			} else {
				throw new Exception("API CALL : getting subscriptions, code=404 NOT FOUND but no status given");
			}
		} else if($httpCode == 200) {
			//OK ?
			$billingsApiResponse = new BillingsApiResponse($content, 'subscriptions');
			if($billingsApiResponse->getStatus() == 'done') {
				BillingsApiClientConfig::getLogger()->addInfo("API CALL : getting subscriptions, code=200 OK");
				$apiSubscriptions = $billingsApiResponse->getObject();
			} else {
				throw new Exception("API CALL : getting subscriptions, code=200 OK," 
						." but status=".$billingsApiResponse->getStatus().","
						." statusCode=".$billingsApiResponse->getStatusCode().","
						." statusMessage=".$billingsApiResponse->getStatusMessage());
			}
		} else {
			BillingsApiClientConfig::getLogger()->addError("API CALL : getting subscriptions, code=".$httpCode);
			throw new Exception("API CALL : getting subscriptions, code=".$httpCode." is unexpected...");
		}
		BillingsApiClientConfig::getLogger()->addInfo("API CALL : getting subscriptions done successfully");
		return($apiSubscriptions);
	}
	
}


class ApiUpdateSubscriptionsRequest {

	private $userBillingUuid = NULL;
	private $userReferenceUuid = NULL;
	
	public function setUserBillingUuid($str) {
		$this->userBillingUuid = $str;
	}
	
	public function getUserBillingUuid() {
		return($this->userBillingUuid);
	}

	public function setUserReferenceUuid($str) {
		$this->userReferenceUuid = $str;
	}

	public function getUserReferenceUuid() {
		return($this->userReferenceUuid);
	}
	
	public function getPut() {
		$out = array();
		if(isset($this->userBillingUuid)) {
			$out['userBillingUuid'] = $this->userBillingUuid;
		}
		if(isset($this->userReferenceUuid)) {
			$out['userReferenceUuid'] = $this->userReferenceUuid;
		}
		return(json_encode($out));
	}

	public function getUrl() {
		$url = getEnv('BILLINGS_API_URL');
		$url.= "/billings/api/subscriptions/";
		return($url);
	}
}

class ApiGetSubscriptionsRequest {
	
	private $userBillingUuid = NULL;
	private $userReferenceUuid = NULL;
	
	public function setUserBillingUuid($str) {
		$this->userBillingUuid = $str;
	}
	
	public function getUserBillingUuid() {
		return($this->userBillingUuid);
	}
	
	public function setUserReferenceUuid($str) {
		$this->userReferenceUuid = $str;
	}
	
	public function getUserReferenceUuid() {
		return($this->userReferenceUuid);
	}
	
	public function getUrl() {
		$url = getEnv('BILLINGS_API_URL');
		$url.= "/billings/api/subscriptions/";
		$params = array();
		if(isset($this->userBillingUuid)) {
			$params['userBillingUuid'] = $this->userBillingUuid;
		}
		if(isset($this->userReferenceUuid)) {
			$params['userReferenceUuid'] = $this->userReferenceUuid;
		}
		$url.= "?".http_build_query($params);
		return($url);
	}
	
}

?>