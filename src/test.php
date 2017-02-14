<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

//date_default_timezone_set("Europe/Paris");

//putenv('GOOGLE_APPLICATION_CREDENTIALS='.__DIR__.'/../libs/providers/google/credentials/afrostream-billing-project-100ce17ea594.json');

$client = new Google_Client();
$client->useApplicationDefaultCredentials();

$client->addScope(Google_Service_AndroidPublisher::ANDROIDPUBLISHER);

$androidPublisher = new Google_Service_AndroidPublisher($client);

//Type is : Google_Service_AndroidPublisher_Resource_PurchasesSubscriptions
$packageName = getEnv('GOOGLE_PACKAGENAME');//'tv.afrostream.app';
$subscriptionId = '1234';
$token = 'todo';
$sub = $androidPublisher->purchases_subscriptions->get($packageName, $subscriptionId, $token);


/*$Google_Service_AndroidPublisher_Resource_PurchasesSubscriptions = new Google_Service_AndroidPublisher_Resource_PurchasesSubscriptions(
		$androidPublisher, $androidPublisher->serviceName, 'subscriptions', $androidPublisher->purchases_subscriptions);
*/


?>