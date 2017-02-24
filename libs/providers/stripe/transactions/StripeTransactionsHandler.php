<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/transactions/ProviderTransactionsHandler.php';

class StripeTransactionsHandler extends ProviderTransactionsHandler {
	
	const STRIPE_LIMIT = 50;
	
	public function __construct($provider) {
		parent::__construct($provider);
		\Stripe\Stripe::setApiKey(getenv('STRIPE_API_KEY'));
	}
	
	public function doUpdateTransactionsByUser(User $user, UserOpts $userOpts, DateTime $from = NULL, DateTime $to = NULL, $updateType) {
		try {
			config::getLogger()->addInfo("updating stripe transactions...");
			$stripeCustomer = \Stripe\Customer::retrieve($user->getUserProviderUuid());
			//CHARGES
			$paramsCharges = array();
			$paramsCharges['limit'] = self::STRIPE_LIMIT;
			$paramsCharges = ['customer' => $user->getUserProviderUuid()];
			if(isset($from)) {
				$paramsCharges['created']['gte'] = $from->getTimestamp();
			}
			if(isset($to)) {
				$paramsCharges['created']['lte'] = $to->getTimestamp();
			}
			$hasMoreCharges = true;
			while ($hasMoreCharges) {
				if (isset($offsetCharges)) {
					$options['starting_after'] = $offsetCharges->id;
				}
				$stripeChargeTransactionsResponse = \Stripe\Charge::all($paramsCharges);
				$hasMoreCharges = $stripeChargeTransactionsResponse['has_more'];
				$stripeChargeTransactions = $stripeChargeTransactionsResponse['data'];
				$offsetCharges = end($stripeChargeTransactions);
				reset($stripeChargeTransactions);
				foreach ($stripeChargeTransactions as $stripeChargeTransaction) {
					try {
						config::getLogger()->addInfo("updating stripe transaction id=".$stripeChargeTransaction->id."...");
						$metadata = $stripeChargeTransaction->metadata->__toArray();
						$hasToBeProcessed = false;
						$hasToBeIgnored = false;
						if(array_key_exists('AfrIgnore', $metadata)) {
							$afrIgnore = $metadata['AfrIgnore'];
							if($afrIgnore == 'true') {
								$hasToBeIgnored = true;
							}
						}
						$isRecurlyTransaction = false;
						if(array_key_exists('recurlyTransactionId', $metadata)) {
							$isRecurlyTransaction = true;
						}
						$hasToBeProcessed = !$hasToBeIgnored && !$isRecurlyTransaction;
						if($hasToBeProcessed) {
							$this->createOrUpdateChargeFromProvider($user, $userOpts, $stripeCustomer, $stripeChargeTransaction, $updateType);
						} else {
							config::getLogger()->addInfo("stripe charge transaction =".$stripeChargeTransaction->id." is ignored");
						}
						config::getLogger()->addInfo("updating stripe transaction id=".$stripeChargeTransaction->id." done successfully");
					} catch (Exception $e) {
						$msg = "an unknown exception occurred while updating stripe transaction id=".$stripeChargeTransaction->id.", error_code=".$e->getCode().", error_message=".$e->getMessage();
						config::getLogger()->addError($msg);					
					}
				}
			}
			//
			config::getLogger()->addInfo("updating stripe transactions done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating stripe transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating stripe transactions failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating stripe transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating stripe transactions failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
	}
	
	private static function getChargeMappedTransactionStatus(\Stripe\Charge $stripeChargeTransaction) {
		$billingTransactionStatus = NULL;
		switch ($stripeChargeTransaction->status) {
			case 'succeeded' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::success);
				break;
			case 'pending' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case 'failed' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::failed);
				break;
			default :
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown stripe charge transaction status : ".$stripeChargeTransaction->status);
				break;
		}
		return($billingTransactionStatus);
	}
	
	private static function getRefundMappedTransactionStatus(\Stripe\Refund $stripeRefundTransaction) {
		$billingTransactionStatus = NULL;
		switch($stripeRefundTransaction->status) {
			case 'succeeded' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::success);
				break;
			case 'pending' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case 'failed' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::failed);
				break;
			case 'cancelled' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::canceled);
				break;
			default :
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown stripe refund transaction status : ".$stripeRefundTransaction->status);
				break;
		}
		return($billingTransactionStatus);
	}
	
	
	public function createOrUpdateChargeFromProvider(User $user = NULL, UserOpts $userOpts = NULL, \Stripe\Customer $stripeCustomer = NULL, \Stripe\Charge $stripeChargeTransaction, $updateType) {
		config::getLogger()->addInfo("creating/updating charge transaction from stripe charge transaction id=".$stripeChargeTransaction->id."...");
		$billingsTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($this->provider->getId(), $stripeChargeTransaction->id);
		$userId = ($user == NULL ? NULL : $user->getId());
		$subId = NULL;
		$couponId = NULL;
		$metadata = $stripeChargeTransaction->metadata->__toArray();
		$afrOrigin = NULL;
		if(array_key_exists('AfrOrigin', $metadata)) {
		 	$afrOrigin = $metadata['AfrOrigin'];
		}
		$searchForSubId = false;
		if(isset($afrOrigin)) {
			switch($afrOrigin) {
				case 'subscription' :
					if(array_key_exists('AfrSubscriptionBillingUuid', $metadata)) {
						$subscription_billing_uuid = $metadata['AfrSubscriptionBillingUuid'];
						$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubscriptionBillingUuid($subscription_billing_uuid);
						if($subscription == NULL) {
							if($stripeChargeTransaction->status != 'failed') {
								$msg = "AfrSubscriptionBillingUuid=".$subscription_billing_uuid." in metadata cannot be found";
								config::getLogger()->addError($msg);
								throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							} else {
								$msg = "AfrSubscriptionBillingUuid=".$subscription_billing_uuid." in metadata cannot be found but has been ignored since subscription was not created";
								config::getLogger()->addInfo($msg);								
							}
						} else {
							$subId = $subscription->getId();
						}
					} else {
						$msg = "'AfrSubscriptionBillingUuid' field is missing in metadata";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					if($userId == NULL) {
						if(array_key_exists('AfrUserBillingUuid', $metadata)) {
							$user_billing_uuid = $metadata['AfrUserBillingUuid'];
							$user = UserDAO::getUserByUserBillingUuid($user_billing_uuid);
							if($user == NULL) {
								$msg = "AfrUserBillingUuid=".$user_billing_uuid." in metadata cannot be found";
								config::getLogger()->addError($msg);
								throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);						
							}
							$userId = $user->getId();
						} else {
							$msg = "'AfrUserBillingUuid' field is missing in metadata";
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						}
					}
					break;
				case 'coupon' :
					if(array_key_exists('AfrCouponBillingUuid', $metadata)) {
						$coupon_billing_uuid = $metadata['AfrCouponBillingUuid'];
						$coupon = BillingUserInternalCouponDAO::getBillingUserInternalCouponByCouponBillingUuid($coupon_billing_uuid);
						if($coupon == NULL) {
							$msg = "AfrCouponBillingUuid=".$coupon_billing_uuid." in metadata cannot be found";
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);						
						}
						$couponId = $coupon->getId();
					} else {
						$msg = "'AfrCouponBillingUuid' field is missing in metadata";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					if($userId == NULL) {
						if(array_key_exists('AfrUserBillingUuid', $metadata)) {
							$user_billing_uuid = $metadata['AfrUserBillingUuid'];
							$user = UserDAO::getUserByUserBillingUuid($user_billing_uuid);
							if($user == NULL) {
								$msg = "AfrUserBillingUuid=".$user_billing_uuid." in metadata cannot be found";
								config::getLogger()->addError($msg);
								throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
							}
							$userId = $user->getId();
						} else {
							$msg = "'AfrUserBillingUuid' field is missing in metadata";
							config::getLogger()->addError($msg);
							throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
						}
					}
					break;
				default :
					$msg = "afrOrigin unknown value : ".$afrOrigin;
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					break;
			}
		} else {
			$searchForSubId = true;
		}
		if($searchForSubId) {
			if(isset($stripeChargeTransaction->invoice)) {
				$invoice = \Stripe\Invoice::retrieve($stripeChargeTransaction->invoice);
				if(isset($invoice->subscription)) {
					$subscription_provider_uuid = $invoice->subscription;
					$subscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($user->getProviderId(), $subscription_provider_uuid);
					if($subscription == NULL) {
						$msg = "subscription with subscription_provider_uuid=".$subscription_provider_uuid." not found";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
					}
					$subId = $subscription->getId();
				} else {
					$msg = "no subscription linked to the invoice";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);					
				}
			} else {
				$msg = "no invoice linked to the transaction, so transaction cannot be linked to a subscription";
				config::getLogger()->addInfo($msg);
				//throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);				
			}
			//should not happen
			if($userId == NULL) {
				if(isset($stripeChargeTransaction->customer)) {
					$user = UserDAO::getUserByUserProviderUuid($providerid, $stripeChargeTransaction->customer);
					if($user == NULL) {
						$msg = "user with user_provider_uuid=".$stripeChargeTransaction->customer." not found";
						config::getLogger()->addError($msg);
						throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);						
					}
					$userId = $user->getId();
				}
			}
		}
		$invoiceId = NULL;
		if($billingsTransaction == NULL) {
			//CREATE
			$billingsTransaction = new BillingsTransaction();
			$billingsTransaction->setTransactionLinkId(NULL);
			$billingsTransaction->setProviderId($this->provider->getId());
			$billingsTransaction->setUserId($userId);
			$billingsTransaction->setSubId($subId);
			$billingsTransaction->setCouponId($couponId);
			$billingsTransaction->setInvoiceId($invoiceId);
			$billingsTransaction->setTransactionBillingUuid(guid());
			$billingsTransaction->setTransactionProviderUuid($stripeChargeTransaction->id);
			$billingsTransaction->setTransactionCreationDate(DateTime::createFromFormat('U', $stripeChargeTransaction->created));
			$billingsTransaction->setAmountInCents($stripeChargeTransaction->amount);
			$billingsTransaction->setCurrency($stripeChargeTransaction->currency);
			$billingsTransaction->setCountry($stripeChargeTransaction->source->country);
			$billingsTransaction->setTransactionStatus(self::getChargeMappedTransactionStatus($stripeChargeTransaction));
			$billingsTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::purchase));
			if(isset($stripeChargeTransaction->invoice)) {
				$billingsTransaction->setInvoiceProviderUuid($stripeChargeTransaction->invoice);
			} else {
				$billingsTransaction->setInvoiceProviderUuid(NULL);
			}
			$billingsTransaction->setMessage("provider_status=".$stripeChargeTransaction->status);
			$billingsTransaction->setUpdateType($updateType);
			$billingsTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsTransaction);
		} else {
			//UPDATE
			$billingsTransaction->setTransactionLinkId(NULL);
			$billingsTransaction->setProviderId($this->provider->getId());
			$billingsTransaction->setUserId($userId);
			$billingsTransaction->setSubId($subId);
			$billingsTransaction->setCouponId($couponId);
			$billingsTransaction->setInvoiceId($invoiceId);
			//NO !!! : $billingsTransaction->setTransactionBillingUuid(guid());
			$billingsTransaction->setTransactionProviderUuid($stripeChargeTransaction->id);
			$billingsTransaction->setTransactionCreationDate(DateTime::createFromFormat('U', $stripeChargeTransaction->created));
			$billingsTransaction->setAmountInCents($stripeChargeTransaction->amount);
			$billingsTransaction->setCurrency($stripeChargeTransaction->currency);
			$billingsTransaction->setCountry($stripeChargeTransaction->source->country);
			$billingsTransaction->setTransactionStatus(self::getChargeMappedTransactionStatus($stripeChargeTransaction));
			$billingsTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::purchase));
			if(isset($stripeChargeTransaction->invoice)) {
				$billingsTransaction->setInvoiceProviderUuid($stripeChargeTransaction->invoice);
			} else {
				$billingsTransaction->setInvoiceProviderUuid(NULL);
			}
			$billingsTransaction->setMessage("provider_status=".$stripeChargeTransaction->status);
			$billingsTransaction->setUpdateType($updateType);
			$billingsTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsTransaction);
		}
		$this->updateRefundsFromProvider($user, $userOpts, $stripeChargeTransaction, $billingsTransaction, $updateType);
		config::getLogger()->addInfo("creating/updating charge transaction from stripe charge transaction id=".$stripeChargeTransaction->id." done successfully");
		return($billingsTransaction);
	}
	
	private function updateRefundsFromProvider(User $user = NULL, UserOpts $userOpts = NULL, \Stripe\Charge $stripeChargeTransaction, BillingsTransaction $billingsTransaction, $updateType) {
		$params = array();
		$params['charge'] = $stripeChargeTransaction->id;
		$params['limit'] = self::STRIPE_LIMIT;
		$hasMoreRefunds = true;
		while ($hasMoreRefunds) {
			if (isset($offsetRefunds)) {
				$params['starting_after'] = $offsetRefunds->id;
			}
			$stripeRefundTransactionsResponse = \Stripe\Refund::all($params);
			$hasMoreRefunds = $stripeRefundTransactionsResponse['has_more'];
			$stripeRefundTransactions = $stripeRefundTransactionsResponse['data'];
			$offsetRefunds = end($stripeRefundTransactions);
			reset($stripeRefundTransactions);
			//
			foreach ($stripeRefundTransactions as $stripeRefundTransaction) {
				$this->createOrUpdateRefundFromProvider($user, $userOpts, $stripeRefundTransaction, $billingsTransaction, $updateType);
			}
		}
	}
	
	private function createOrUpdateRefundFromProvider(User $user = NULL, UserOpts $userOpts = NULL, \Stripe\Refund $stripeRefundTransaction, BillingsTransaction $billingsTransaction, $updateType) {
		config::getLogger()->addInfo("creating/updating refund transaction from stripe refund transaction id=".$stripeRefundTransaction->id."...");
		$billingsRefundTransaction = BillingsTransactionDAO::getBillingsTransactionByTransactionProviderUuid($user->getProviderId(), $stripeRefundTransaction->id);
		if($billingsRefundTransaction == NULL) {
			//CREATE
			$billingsRefundTransaction = new BillingsTransaction();
			$billingsRefundTransaction->setTransactionLinkId($billingsTransaction->getId());
			$billingsRefundTransaction->setProviderId($billingsTransaction->getProviderId());
			$billingsRefundTransaction->setUserId($billingsTransaction->getUserId());
			$billingsRefundTransaction->setSubId($billingsTransaction->getSubId());
			$billingsRefundTransaction->setCouponId($billingsTransaction->getCouponId());
			$billingsRefundTransaction->setInvoiceId($billingsTransaction->getInvoiceId());
			$billingsRefundTransaction->setTransactionBillingUuid(guid());
			$billingsRefundTransaction->setTransactionProviderUuid($stripeRefundTransaction->id);
			$billingsRefundTransaction->setTransactionCreationDate(DateTime::createFromFormat('U', $stripeRefundTransaction->created));
			$billingsRefundTransaction->setAmountInCents($stripeRefundTransaction->amount);
			$billingsRefundTransaction->setCurrency($stripeRefundTransaction->currency);
			$billingsRefundTransaction->setCountry($billingsTransaction->getCountry());//Country = Country of the Charge
			$billingsRefundTransaction->setTransactionStatus(self::getRefundMappedTransactionStatus($stripeRefundTransaction));
			$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
			$billingsRefundTransaction->setInvoiceProviderUuid($billingsTransaction->getInvoiceProviderUuid());
			$billingsRefundTransaction->setMessage("provider_status=".$stripeRefundTransaction->status);
			$billingsRefundTransaction->setUpdateType($updateType);
			$billingsRefundTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsRefundTransaction);
		} else {
			//UPDATE
			$billingsRefundTransaction->setTransactionLinkId($billingsTransaction->getId());
			$billingsRefundTransaction->setProviderId($billingsTransaction->getProviderId());
			$billingsRefundTransaction->setUserId($billingsTransaction->getUserId());
			$billingsRefundTransaction->setSubId($billingsTransaction->getSubId());
			$billingsRefundTransaction->setCouponId($billingsTransaction->getCouponId());
			$billingsRefundTransaction->setInvoiceId($billingsTransaction->getInvoiceId());
			//NO !!! : $billingsRefundTransaction->setTransactionBillingUuid(guid());
			$billingsRefundTransaction->setTransactionProviderUuid($stripeRefundTransaction->id);
			$billingsRefundTransaction->setTransactionCreationDate(DateTime::createFromFormat('U', $stripeRefundTransaction->created));
			$billingsRefundTransaction->setAmountInCents($stripeRefundTransaction->amount);
			$billingsRefundTransaction->setCurrency($stripeRefundTransaction->currency);
			$billingsRefundTransaction->setCountry($billingsTransaction->getCountry());//Country = Country of the Charge
			$billingsRefundTransaction->setTransactionStatus(self::getRefundMappedTransactionStatus($stripeRefundTransaction));
			$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
			$billingsRefundTransaction->setInvoiceProviderUuid($billingsTransaction->getInvoiceProviderUuid());
			$billingsRefundTransaction->setMessage("provider_status=".$stripeRefundTransaction->status);
			$billingsRefundTransaction->setUpdateType($updateType);
			$billingsRefundTransaction = BillingsTransactionDAO::updateBillingsTransaction($billingsRefundTransaction);
		}
		config::getLogger()->addInfo("creating/updating refund transaction from stripe refund transaction id=".$stripeRefundTransaction->id." done successfully");
		return($billingsRefundTransaction);
	}
	
	public function doUpdateTransactionByTransactionProviderUuid($transactionProviderUuid, $updateType) {
		try {
			$stripeChargeTransaction = \Stripe\Charge::retrieve($transactionProviderUuid);
			if(isset($stripeChargeTransaction->customer)) {
				$msg = "only stand-alone charge can be updated here";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$metadata = $stripeChargeTransaction->metadata->__toArray();
			$hasToBeProcessed = false;
			$hasToBeIgnored = false;
			if(array_key_exists('AfrIgnore', $metadata)) {
				$afrIgnore = $metadata['AfrIgnore'];
				if($afrIgnore == 'true') {
					$hasToBeIgnored = true;
				}
			}
			$isRecurlyTransaction = false;
			if(array_key_exists('recurlyTransactionId', $metadata)) {
				$isRecurlyTransaction = true;
			}
			$hasToBeProcessed = !$hasToBeIgnored && !$isRecurlyTransaction;
			if($hasToBeProcessed) {
				$this->createOrUpdateChargeFromProvider(NULL, NULL, NULL, $stripeChargeTransaction, $updateType);
			} else {
				config::getLogger()->addInfo("stripe charge transaction =".$stripeChargeTransaction->id." is ignored");
			}
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating stripe transaction, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating stripe transaction failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating stripe transaction, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating stripe transaction failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
	}
	
	public function doRefundTransaction(BillingsTransaction $transaction, RefundTransactionRequest $refundTransactionRequest) {
		try {
			config::getLogger()->addInfo("refunding a ".$this->provider->getName()." transaction with transactionBillingUuid=".$transaction->getTransactionBillingUuid()."...");
			
			$api_refund = \Stripe\Refund::create(["charge" => $transaction->getTransactionProviderUuid()]);
			//reload payment, in order to be up to date
			$api_payment = \Stripe\Charge::retrieve($transaction->getTransactionProviderUuid());
			//
			$user = UserDAO::getUserById($transaction->getUserId());
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			$stripeCustomer = \Stripe\Customer::retrieve($user->getUserProviderUuid());
			$transaction = $this->createOrUpdateChargeFromProvider($user, $userOpts, $stripeCustomer, $api_payment, $refundTransactionRequest->getOrigin());
			//
			config::getLogger()->addInfo("refunding a ".$this->provider->getName()." transaction with transactionBillingUuid=".$transaction->getTransactionBillingUuid()." done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while refunding a ".$this->provider->getName()." transaction with transactionBillingUuid=".$transaction->getTransactionBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("refunding a ".$this->provider->getName()." transaction failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while refunding a ".$this->provider->getName()." transaction with transactionBillingUuid=".$transaction->getTransactionBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("refunding a ".$this->provider->getName()." transaction failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($transaction);
	}
	
}

?>