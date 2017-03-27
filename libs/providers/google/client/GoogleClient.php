<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';

class GoogleClient {
	
	private $config;
	
	public function __construct($config) {
		$this->config = $config;
	}
	
	public function getSubscription(GoogleGetSubscriptionRequest $getSubscriptionRequest) {
		$client = new Google_Client($this->config);
		//$client->useApplicationDefaultCredentials();
		$client->addScope(Google_Service_AndroidPublisher::ANDROIDPUBLISHER);
		$androidPublisher = new Google_Service_AndroidPublisher($client);
		return($androidPublisher->purchases_subscriptions->get($getSubscriptionRequest->getPackageName(), $getSubscriptionRequest->getSubscriptionId(), $getSubscriptionRequest->getToken()));
	}
	
	public function cancelSubscription(GoogleCancelSubscriptionRequest $cancelSubscriptionRequest) {
		$client = new Google_Client($this->config);
		//$client->useApplicationDefaultCredentials();
		$client->addScope(Google_Service_AndroidPublisher::ANDROIDPUBLISHER);
		$androidPublisher = new Google_Service_AndroidPublisher($client);
		return($androidPublisher->purchases_subscriptions->cancel($cancelSubscriptionRequest->getPackageName(), $cancelSubscriptionRequest->getSubscriptionId(), $cancelSubscriptionRequest->getToken()));
	}
	
	public function expireSubscription(GoogleExpireSubscriptionRequest $expireSubscriptionRequest) {
		$client = new Google_Client($this->config);
		//$client->useApplicationDefaultCredentials();
		$client->addScope(Google_Service_AndroidPublisher::ANDROIDPUBLISHER);
		$androidPublisher = new Google_Service_AndroidPublisher($client);
		return($androidPublisher->purchases_subscriptions->revoke($expireSubscriptionRequest->getPackageName(), $expireSubscriptionRequest->getSubscriptionId(), $expireSubscriptionRequest->getToken()));
	}
}

class GoogleClientRequest {
	
}

class GoogleSubscriptionRequest extends GoogleClientRequest {

	private $packageName;
	private $subscriptionId;
	private $token;
	
	public function __construct() {
	}
	
	public function setPackageName($packageName) {
		$this->packageName = $packageName;
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

class GoogleGetSubscriptionRequest extends GoogleSubscriptionRequest {
}

class GoogleCancelSubscriptionRequest extends GoogleSubscriptionRequest {
}

class GoogleExpireSubscriptionRequest extends GoogleSubscriptionRequest {
}

?>