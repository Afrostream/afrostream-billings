<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';

/*
 * Tool
 */

print_r("starting tool that create a Braintree subscription...\n");

print_r("processing...\n");

$customerProviderUuid = NULL;
$planProviderUuid = NULL;

foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_GET[$e[0]]=$e[1];
        else
            $_GET[$e[0]]=0;
}

$provider = NULL;
$providerUuid = NULL;

if(isset($_GET["-providerUuid"])) {
    $providerUuid = $_GET["-providerUuid"];
    $provider = ProviderDAO::getProviderByUuid($providerUuid);
} else {
    $msg = "-providerUuid field is missing\n";
    die($msg);
}

if($provider == NULL) {
    $msg = "provider with uuid=".$providerUuid." not found\n";
    die($msg);
}

if($provider->getName() != 'braintree') {
    $msg = "provider with uuid=".$providerUuid." is not connected to braintree\n";
    die($msg);
}

if(isset($_GET["-customerProviderUuid"])) {
    $customerProviderUuid = $_GET["-customerProviderUuid"];
} else {
    $msg = "-customerProviderUuid field is missing\n";
    die($msg);
}

if(isset($_GET["-planProviderUuid"])) {
    $planProviderUuid = $_GET["-planProviderUuid"];
} else {
    $msg = "-planProviderUuid field is missing\n";
    die($msg);
}

Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
Braintree_Configuration::merchantId($provider->getMerchantId());
Braintree_Configuration::publicKey($provider->getApiKey());
Braintree_Configuration::privateKey($provider->getApiSecret());

$customer = Braintree\Customer::find($customerProviderUuid);
$currentPaymentMethod = NULL;
foreach ($customer->paymentMethods as $paymentMethod) {
    if($paymentMethod->isDefault()) {
        $currentPaymentMethod = $paymentMethod;
        break;
    }
}

if($currentPaymentMethod == NULL) {
    $msg = "customer=".$customerProviderUuid.", plan=".$planProviderUuid.", no default paymentMethod found, processing failed";
    die($msg);
} else {
    print_r("customer=".$customerProviderUuid.", plan=".$planProviderUuid.", default paymentMethod found=".var_export($currentPaymentMethod, true)."\n"); 
}

print_r("customer=".$customerProviderUuid.", plan=".$planProviderUuid.", subscription creation...\n"); 

$attribs = array();
$attribs['planId'] = $planProviderUuid;
$attribs['paymentMethodToken'] = $currentPaymentMethod->token;
$result = Braintree\Subscription::create($attribs);
if ($result->success) {
    $subscription = $result->subscription;
    print_r("customer=".$customerProviderUuid.", plan=".$planProviderUuid.", subscription creation done successfully, ID=".$subscription->id."\n");
} else {
    $msg = 'a braintree api error occurred : ';
    $errorString = $result->message;
    foreach($result->errors->deepAll() as $error) {
        $errorString.= '; Code=' . $error->code . ", msg=" . $error->message;
    }
    print_r("customer=".$customerProviderUuid.", plan=".$planProviderUuid.", subscription creation failed, message=".$msg.$errorString."\n");
}

print_r("processing done\n");