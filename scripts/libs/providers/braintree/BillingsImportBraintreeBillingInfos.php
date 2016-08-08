<?php

require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';

use PayPal\Api\Payment;
use PayPal\Auth\OAuthTokenCredential;

use PayPal\Rest\ApiContext;

class BillingsImportBraintreeBillingInfos {
	
	private $providerid = NULL;
	
	public function __construct() {
		$this->providerid = ProviderDAO::getProviderByName('braintree')->getId();
	}
	
	public function doImportBillingInfos() {
		try {
			ScriptsConfig::getLogger()->addInfo("importing billinginfos from braintree...");
			//PAYPAL INIT
			$config = array();
			$config['mode'] = 'live';
			
			$cred = new OAuthTokenCredential(getEnv('PAYPAL_API_CLIENT_ID'), getEnv('PAYPAL_API_SECRET'));
			
			$apiContext = new ApiContext($cred);
			$apiContext->setConfig($config);
			//BRAINTREE INIT
			Braintree_Configuration::environment(getenv('BRAINTREE_ENVIRONMENT'));
			Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
			Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
			Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
			//
			$braintreeCustomers = Braintree\Customer::all();
				
			foreach ($braintreeCustomers as $braintreeCustomer) {
				try {
					foreach ($braintreeCustomer->paymentMethods as $paymentMethod) {
						foreach ($paymentMethod->subscriptions as $customer_subscription) {
							$currentBraintreeSubscription = Braintree\Subscription::find($customer_subscription->id);
							$currentBraintreeTransactions = $currentBraintreeSubscription->transactions;
							if(count($currentBraintreeTransactions) > 0) {
								$currentBraintreeTransaction = $currentBraintreeTransactions[0];
								$this->doImportSubscriptionBillingInfo($currentBraintreeSubscription, $currentBraintreeTransaction, $apiContext);
							}
						}
					}
				} catch (Exception $e) {
					ScriptsConfig::getLogger()->addError("unexpected exception while importing billinginfos from braintree with account_code=".$braintreeCustomer->id.", message=".$e->getMessage());
				}
			}
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("unexpected exception while importing billinginfos from braintree, message=".$e->getMessage());
		}
		ScriptsConfig::getLogger()->addInfo("importing billinginfos from braintree done");
	}
	
	private function doImportSubscriptionBillingInfo(Braintree\Subscription $currentBraintreeSubscription, Braintree\Transaction $currentBraintreeTransaction, ApiContext $apiContext) {
		$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($this->providerid, $currentBraintreeSubscription->id);
		if($subscription == NULL) {
			throw new Exception("subscription with subscription_provider_id=".$currentBraintreeSubscription->id." does not exist in billings database");
		}
		//
		$paypalPaymentId = $currentBraintreeTransaction->paypalDetails->paymentId;
		ScriptsConfig::getLogger()->addInfo("importing paypal data from paymentId=".$paypalPaymentId."...");
		$payment = Payment::get($paypalPaymentId, $apiContext);
		ScriptsConfig::getLogger()->addInfo("importing paypal data from paymentId=".$paypalPaymentId." done successfully");
		//

		$countryCode = $payment->payer->payer_info->country_code;
		if(isset($countryCode)) {
			ScriptsConfig::getLogger()->addInfo("importing paypal data, countryCode=".$countryCode);
			$billingInfo = NULL;
			if($subscription->getBillingInfoId() == NULL) {
				//CREATE
				$billingInfo = new BillingInfo();
				$billingInfo->setBillingInfoBillingUuid(guid());
				$billingInfo = BillingInfoDAO::addBillingInfo($billingInfo);
				$subscription->setBillingInfoId($billingInfo->getId());
				$subscription = BillingsSubscriptionDAO::updateBillingInfoId($subscription);
			} else {
				//GET
				$billingInfo = BillingInfoDAO::getBillingInfoByBillingInfoId($subscription->getBillingInfoId());
			}
			//UPDATE
			$billingInfo->setCountryCode($countryCode);
			$billingInfo = BillingInfoDAO::updateCountryCode($billingInfo);
		} else {
			ScriptsConfig::getLogger()->addInfo("importing paypal data, no countryCode found");
		}
	}
	
}

?>