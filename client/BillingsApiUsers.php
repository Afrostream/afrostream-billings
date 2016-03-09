<?php

require_once __DIR__ . '/BillingsApiResponse.php';

class BillingsApiUsers {
	
	private $billingsApiClient = NULL;
	
	public function __construct(BillingsApiClient $billingsApiClient) {
		$this->billingsApiClient = $billingsApiClient;
	}
	
	public function getUser(ApiGetUserRequest $apiGetUserRequest) {
		$apiUser = NULL;
		BillingsApiClientConfig::getLogger()->addInfo("API CALL : getting user...");
		$url = $apiGetUserRequest->getUrl();
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
			$billingsApiResponse = new BillingsApiResponse($content, 'user');
			if($billingsApiResponse->getStatus() == 'error') {
				BillingsApiClientConfig::getLogger()->addInfo("API CALL : getting user, code=404 NOT FOUND");
			} else {
				throw new Exception("API CALL : getting user, code=404 NOT FOUND but no status given");
			}
		} else if($httpCode == 200) {
			//OK ?
			$billingsApiResponse = new BillingsApiResponse($content, 'user');
			if($billingsApiResponse->getStatus() == 'done') {
				BillingsApiClientConfig::getLogger()->addInfo("API CALL : getting user, code=200 OK");
				$apiUser = $billingsApiResponse->getObject();
			} else {
				throw new Exception("API CALL : getting user, code=200 OK," 
						." but status=".$billingsApiResponse->getStatus().","
						." statusCode=".$billingsApiResponse->getStatusCode().","
						." statusMessage=".$billingsApiResponse->getStatusMessage());
			}
		} else {
			BillingsApiClientConfig::getLogger()->addError("API CALL : getting user, code=".$httpCode);
			throw new Exception("API CALL : getting user, code=".$httpCode." is unexpected...");
		}
		BillingsApiClientConfig::getLogger()->addInfo("API CALL : getting user done successfully");
		return($apiUser);
	}
	
	public function createUser(ApiCreateUserRequest $apiCreateUserRequest) {
		$apiUser = NULL;
		$url = $apiCreateUserRequest->getUrl();
		$data_string = $apiCreateUserRequest->getPost();
		$curl_options = array(
				CURLOPT_URL => $url,
				CURLOPT_CUSTOMREQUEST => 'POST',
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
		if($httpCode == 200) {
			//OK ?
			$billingsApiResponse = new BillingsApiResponse($content, 'user');
			if($billingsApiResponse->getStatus() == 'done') {
				BillingsApiClientConfig::getLogger()->addInfo("API CALL : creating user, code=200 OK");
				$apiUser = $billingsApiResponse->getObject();
			} else {
				throw new Exception("API CALL : creating user, code=200 OK,"
						." but status=".$billingsApiResponse->getStatus().","
						." statusCode=".$billingsApiResponse->getStatusCode().","
						." statusMessage=".$billingsApiResponse->getStatusMessage());
			}
		} else {
			BillingsApiClientConfig::getLogger()->addError("API CALL : creating user, code=".$httpCode);
			throw new Exception("API CALL : creating user, code=".$httpCode." is unexpected...");
		}
		return($apiUser);
	}
	
	public function updateUser(ApiUpdateUserRequest $apiUpdateUserRequest) {
		$apiUser = NULL;
		$url = $apiUpdateUserRequest->getUrl();
		$data_string = $apiUpdateUserRequest->getPut();
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
		//$curl_options[CURLOPT_VERBOSE] = true;
		$CURL = curl_init();
		curl_setopt_array($CURL, $curl_options);
		$content = curl_exec($CURL);
		$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
		curl_close($CURL);
		if($httpCode == 200) {
			//OK ?
			$billingsApiResponse = new BillingsApiResponse($content, 'user');
			if($billingsApiResponse->getStatus() == 'done') {
				BillingsApiClientConfig::getLogger()->addInfo("API CALL : updating user, code=200 OK");
				$apiUser = $billingsApiResponse->getObject();
			} else {
				throw new Exception("API CALL : updating user, code=200 OK,"
						." but status=".$billingsApiResponse->getStatus().","
						." statusCode=".$billingsApiResponse->getStatusCode().","
						." statusMessage=".$billingsApiResponse->getStatusMessage());
			}
		} else {
			BillingsApiClientConfig::getLogger()->addError("API CALL : updating user, code=".$httpCode);
			throw new Exception("API CALL : updating user, code=".$httpCode." is unexpected...");
		}
		return($apiUser);
	}
	
}

class ApiGetUserRequest {
	
	private $providerName = NULL;
	private $userReferenceUuid = NULL;
	
	public function setProviderName($str) {
		$this->providerName = $str;
	}
	
	public function getProviderName() {
		return($this->providerName);
	}
	
	public function setUserReferenceUuid($str) {
		$this->userReferenceUuid = $str;
	}
	
	public function getUserReferenceUuid() {
		return($this->userReferenceUuid);
	}
	
	public function getUrl() {
		$url = getEnv('BILLINGS_API_URL');
		$url.= "/billings/api/users/";
		$params = array("providerName" => $this->providerName, 
				"userReferenceUuid" => $this->userReferenceUuid);
		$url.= "?".http_build_query($params);
		return($url);
	}
 }
 
 class ApiCreateUserRequest {
 	
 	private $providerName = NULL;
 	private $userReferenceUuid = NULL;
 	private $userProviderUuid = NULL;
 	private $userOpts = array();

 	public function setProviderName($str) {
 		$this->providerName = $str;
 	}
 	
 	public function getProviderName() {
 		return($this->providerName);
 	}
 	
 	public function setUserReferenceUuid($str) {
 		$this->userReferenceUuid = $str;
 	}
 	
 	public function getUserReferenceUuid() {
 		return($this->userReferenceUuid);
 	}
 	
 	public function setUserProviderUuid($str) {
 		$this->userProviderUuid = $str;
 	}
 	
 	public function getUserProviderUid() {
 		return($this->userProviderUuid);
 	}
 	
 	public function setUserOpts($key, $value) {
 		$this->userOpts[$key] = $value;
 	}
 	
 	public function getUserOpts() {
 		return($this->userOpts);
 	}
 	
 	public function getPost() {
 		$out = array();
 		$out['providerName'] = $this->providerName;
 		$out['userReferenceUuid'] = $this->userReferenceUuid;
 		$out['userProviderUuid'] = $this->userProviderUuid;
 		$out['userOpts'] = $this->userOpts;
 		return(json_encode($out));
 	}
 	
 	public function getUrl() {
 		$url = getEnv('BILLINGS_API_URL');
 		$url.= "/billings/api/users/";
 		return($url);
 	}
 	
}

class ApiUpdateUserRequest {
	
	private $userBillingUuid = NULL;
	private $userOpts = array();
	
	public function setUserBillingUuid($uuid) {
		$this->userBillingUuid = $uuid;
	}
	
	public function getUserBillingUuid() {
		return($this->userBillingUuid);
	}
	
	public function setUserOpts($key, $value) {
		$this->userOpts[$key] = $value;
	}
	
	public function getUserOpts() {
		return($this->userOpts);
	}
	
	public function getPut() {
		$out = array();
		$out['userOpts'] = $this->userOpts;
		return(json_encode($out));
	}
	
	public function getUrl() {
		$url = getEnv('BILLINGS_API_URL');
		$url.= '/'.$this->userBillingUuid;
		return($url);
	}

}

?>