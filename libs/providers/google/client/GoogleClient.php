<?php

class GoogleClient {
		
	public function __construct() {
	}
	
	public function getSubscription(GetSubscriptionRequest $getSubscriptionRequest) {
		$client = new Google_Client();
		$client->useApplicationDefaultCredentials();
		$client->addScope(Google_Service_AndroidPublisher::ANDROIDPUBLISHER);
		$androidPublisher = new Google_Service_AndroidPublisher($client);
		return($androidPublisher->purchases_subscriptions->get($getSubscriptionRequest->getPackageName(), $getSubscriptionRequest->getSubscriptionId(), $getSubscriptionRequest->getToken()));
	}

}

class GetSubscriptionRequest {
	
	private $packageName;
	private $subscriptionId;
	private $token;
	
	public function __construct() {
		$this->packageName = getEnv('GOOGLE_PACKAGENAME');
	}
	
	public function getPackageName() {
		return($this->packageName);	
	}
	
	public function setSubscriptionId($subscriptionId) {
		$this->subscriptionId = $subscriptionId;
	}
	
	public function getSubscriptionId() {
		return($this->subscriptionId);
	}
	
	public function setToken($token) {
		$this->token = $token;
	}
	
	public function getToken() {
		return($this->token);
	}
	
}

class GetSubscriptionResponse {
	
}

?>