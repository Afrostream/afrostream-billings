<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../libs/subscriptions/SubscriptionsHandler.php';

use Money\Money;
use Money\Currency;

class BillingUsersInternalPlanChangeHandler {

	private $platform;
	private $notifyDaysAgoCounter = 35;
	private $changeDaysAgoCounter = 1;

	public function __construct(BillingPlatform $platform) {
		$this->platform = $platform;
	}
	
	public function notifyUsersPlanChange($fromInternalPlanUuid, $toInternalPlanUuid) {
		try {
			ScriptsConfig::getLogger()->addInfo("notifying Plan Change from : ".$fromInternalPlanUuid." to : ".$toInternalPlanUuid."...");
			$now = new DateTime();
			$fromInternalPlan = InternalPlanDAO::getInternalPlanByUuid($fromInternalPlanUuid, $this->platform->getId());
			if($fromInternalPlan == NULL) {
				ScriptsConfig::getLogger()->addError("no internalPlan with uuid : ".$fromInternalPlanUuid);
				return;
			}
			$toInternalPlan = InternalPlanDAO::getInternalPlanByUuid($toInternalPlanUuid, $this->platform->getId());
			if($toInternalPlan == NULL) {
				ScriptsConfig::getLogger()->addError("no internalPlan with uuid : ".$toInternalPlanUuid);
				return;
			}
			$supportedProviderNames = ['recurly'];
			foreach($supportedProviderNames as $supportedProviderName) {
				$provider = ProviderDAO::getProviderByName($supportedProviderName, $this->platform->getId());
				if($provider == NULL) {
					ScriptsConfig::getLogger()->addError("no provider found named : ".$supportedProviderName);
					break;
				}
				$fromProviderPlan = PlanDAO::getPlanByInternalPlanId($fromInternalPlan->getId(), $provider->getId());
				if($fromProviderPlan == NULL) {
					ScriptsConfig::getLogger()->addError("no plan associated to internalPlan with uuid : ".$fromInternalPlan->getInternalPlanUuid()." with provider name : ".$supportedProviderName);
					break;					
				}
				$toProviderPlan = PlanDAO::getPlanByInternalPlanId($toInternalPlan->getId(), $provider->getId());
				if($toProviderPlan == NULL) {
					ScriptsConfig::getLogger()->addError("no plan associated to internalPlan with uuid : ".$toInternalPlan->getInternalPlanUuid()." with provider name : ".$supportedProviderName);
					break;
				}
				$subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByPlanId($fromProviderPlan->getId());
				foreach ($subscriptions as $subscription) {
					try {
						//
						if($subscription->getPlanChangeNotified() == false) {
							if($subscription->getSubStatus() == 'active') {
								$subPeriodEndsDate = $subscription->getSubPeriodEndsDate();
								//
								$diffInDays = $now->diff($subPeriodEndsDate)->days;
								if($subPeriodEndsDate > $now && $diffInDays < $this->notifyDaysAgoCounter) {
									$this->notifyUserPlanChange($subscription, $fromInternalPlan, $fromProviderPlan, $toInternalPlan, $toProviderPlan);
								} else  {
									ScriptsConfig::getLogger()->addInfo("subscription with uuid=".$subscription->getSubscriptionBillingUuid()." ignored, because it is too early, diffInDays=".$diffInDays);
								}
							} else {
								ScriptsConfig::getLogger()->addInfo("subscription with uuid=".$subscription->getSubscriptionBillingUuid()." ignored, because subStatus is NOT active");
							}
						} else {
							ScriptsConfig::getLogger()->addInfo("subscription with uuid=".$subscription->getSubscriptionBillingUuid()." ignored, because user has already been notified");
						}
						//
					} catch(Exception $e) {
						ScriptsConfig::getLogger()->addError("subscription with uuid=".$subscription->getSubscriptionBillingUuid()." failed to be notified, error_code=".$e->getCode().", error_message=".$e->getMessage());
					}
				}
			}
			ScriptsConfig::getLogger()->addInfo("notifying Plan Change from : ".$fromInternalPlanUuid." to : ".$toInternalPlanUuid." done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("notifying Plan Change from : ".$fromInternalPlanUuid." to : ".$toInternalPlanUuid." failed, error_code=".$e->getCode().", error_message=".$e->getMessage());
			throw $e;
		}
	}
	
	private function notifyUserPlanChange(BillingsSubscription $subscription, 
			InternalPlan $fromInternalPlan, Plan $fromProviderPlan,
			InternalPlan $toInternalPlan, Plan $toProviderPlan) {
		try {
			ScriptsConfig::getLogger()->addInfo("subscription with uuid=".$subscription->getSubscriptionBillingUuid()." notifying plan change...");
			$sendgrid_template_id = '66a8e538-641e-4ebb-9b5a-0b7ab4ae72ba';//SUBSCRIPTION_NOTIFY_PLAN_CHANGE';
			$user = UserDAO::getUserById($subscription->getUserId());
			if($user == NULL) {
				$msg = "unknown user with id : ".$subscription->getUserId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			$emailTo = NULL;
			if(array_key_exists('email', $userOpts->getOpts())) {
				$emailTo = $userOpts->getOpts()['email'];
			}
			//DATA -->
			//DATA <--
			//DATA SUBSTITUTION -->
			setlocale(LC_MONETARY, 'fr_FR.utf8');//TODO : Forced to French Locale for "," in floats...
			$substitutions = array();
			//user
			$substitutions['%userreferenceuuid%'] = $user->getUserReferenceUuid();
			$substitutions['%userbillinguuid%'] = $user->getUserBillingUuid();
			//provider : nothing
			//fromInternalPlan :
			$substitutions['%frominternalplanname%'] = $fromInternalPlan->getName();
			$substitutions['%frominternalplandesc%'] = $fromInternalPlan->getDescription();
			$substitutions['%fromamountincents%'] = $fromInternalPlan->getAmountInCents();
			$amountInMoney = new Money((integer) $fromInternalPlan->getAmountInCents(), new Currency($fromInternalPlan->getCurrency()));
			$substitutions['%fromamount%'] = money_format('%!.2n', (float) ($amountInMoney->getAmount() / 100));
			$substitutions['%fromamountincentsexcltax%'] = $fromInternalPlan->getAmountInCentsExclTax();
			$amountExclTaxInMoney = new Money((integer) $fromInternalPlan->getAmountInCentsExclTax(), new Currency($fromInternalPlan->getCurrency()));
			$substitutions['%fromamountexcltax%'] = money_format('%!.2n', (float) ($amountExclTaxInMoney->getAmount() / 100));
			if($fromInternalPlan->getVatRate() == NULL) {
				$substitutions['%fromvat%'] = 'N/A';
			} else {
				$substitutions['%fromvat%'] = number_format($fromInternalPlan->getVatRate(), 2, ',', '').'%';
			}
			$substitutions['%fromamountincentstax%'] = $fromInternalPlan->getAmountInCents() - $fromInternalPlan->getAmountInCentsExclTax();
			$amountTaxInMoney = new Money((integer) ($fromInternalPlan->getAmountInCents() - $fromInternalPlan->getAmountInCentsExclTax()), new Currency($fromInternalPlan->getCurrency()));
			$substitutions['%fromamounttax%'] = money_format('%!.2n', (float) ($amountTaxInMoney->getAmount() / 100));
			$substitutions['%fromcurrency%'] = $fromInternalPlan->getCurrencyForDisplay();
			$substitutions['%fromcycle%'] = $fromInternalPlan->getCycle();
			$substitutions['%fromperiodunit%'] = $fromInternalPlan->getPeriodUnit();
			$substitutions['%fromperiodlength%'] = $fromInternalPlan->getPeriodLength();
			//toInternalPlan :
			$substitutions['%tointernalplanname%'] = $toInternalPlan->getName();
			$substitutions['%tointernalplandesc%'] = $toInternalPlan->getDescription();
			$substitutions['%toamountincents%'] = $toInternalPlan->getAmountInCents();
			$amountInMoney = new Money((integer) $toInternalPlan->getAmountInCents(), new Currency($toInternalPlan->getCurrency()));
			$substitutions['%toamount%'] = money_format('%!.2n', (float) ($amountInMoney->getAmount() / 100));
			$substitutions['%toamountincentsexcltax%'] = $toInternalPlan->getAmountInCentsExclTax();
			$amountExclTaxInMoney = new Money((integer) $toInternalPlan->getAmountInCentsExclTax(), new Currency($toInternalPlan->getCurrency()));
			$substitutions['%toamountexcltax%'] = money_format('%!.2n', (float) ($amountExclTaxInMoney->getAmount() / 100));
			if($toInternalPlan->getVatRate() == NULL) {
				$substitutions['%tovat%'] = 'N/A';
			} else {
				$substitutions['%tovat%'] = number_format($toInternalPlan->getVatRate(), 2, ',', '').'%';
			}
			$substitutions['%toamountincentstax%'] = $toInternalPlan->getAmountInCents() - $toInternalPlan->getAmountInCentsExclTax();
			$amountTaxInMoney = new Money((integer) ($toInternalPlan->getAmountInCents() - $toInternalPlan->getAmountInCentsExclTax()), new Currency($toInternalPlan->getCurrency()));
			$substitutions['%toamounttax%'] = money_format('%!.2n', (float) ($amountTaxInMoney->getAmount() / 100));
			$substitutions['%tocurrency%'] = $toInternalPlan->getCurrencyForDisplay();
			$substitutions['%tocycle%'] = $toInternalPlan->getCycle();
			$substitutions['%toperiodunit%'] = $toInternalPlan->getPeriodUnit();
			$substitutions['%toperiodlength%'] = $toInternalPlan->getPeriodLength();
			//
			//user : nothing
			//userOpts
			$substitutions['%email%'] = ($emailTo == NULL ? '' : $emailTo);
			$firstname = '';
			if(array_key_exists('firstName', $userOpts->getOpts())) {
				$firstname = $userOpts->getOpts()['firstName'];
			}
			if($firstname == 'firstNameValue') {
				$firstname = '';
			}
			$substitutions['%firstname%'] = $firstname;
			$lastname = '';
			if(array_key_exists('lastName', $userOpts->getOpts())) {
				$lastname = $userOpts->getOpts()['lastName'];
			}
			if($lastname == 'lastNameValue') {
				$lastname = '';
			}
			$substitutions['%lastname%'] = $lastname;
			$username = $firstname;
			if($username == '') {
				if(!empty($emailTo)) {
					$username = explode('@', $emailTo)[0];
				}
			}
			$substitutions['%username%'] = $username;
			$fullname = trim($firstname." ".$lastname);
			$substitutions['%fullname%'] = $fullname;
			//subscription
			$substitutions['%subscriptionbillinguuid%'] = $subscription->getSubscriptionBillingUuid();
			$substitutions['%subPeriodEndsDateYear%'] = $subscription->getSubPeriodEndsDate()->format('Y');
			$substitutions['%subPeriodEndsDateMonth%'] = $subscription->getSubPeriodEndsDate()->format('m');
			$substitutions['%subPeriodEndsDateDay%'] = $subscription->getSubPeriodEndsDate()->format('j');
			//DATA SUBSTITUTION <--
			$sendgrid = new SendGrid(getEnv('SENDGRID_API_KEY'));
			$mail = new SendGrid\Mail();
			$email = new SendGrid\Email(getEnv('SENDGRID_FROM_NAME'), getEnv('SENDGRID_FROM'));
			$mail->setFrom($email);
			$personalization = new SendGrid\Personalization();
			$personalization->addTo(new SendGrid\Email(NULL, !empty($emailTo) ? $emailTo : getEnv('SENDGRID_TO_IFNULL')));
			if((null !== (getEnv('SENDGRID_BCC'))) && ('' !== (getEnv('SENDGRID_BCC')))) {
				$personalization->addBcc(new SendGrid\Email(NULL, getEnv('SENDGRID_BCC')));
			}
			foreach($substitutions as $var => $val) {
				//NC $val."" NOT $val which forces to cast to string because of an issue with numerics : https://github.com/sendgrid/sendgrid-php/issues/350
				$personalization->addSubstitution($var, $val."");
			}
			$mail->addPersonalization($personalization);
			$mail->setTemplateId($sendgrid_template_id);
			$response = $sendgrid->client->mail()->send()->post($mail);
			if($response->statusCode() != 202) {
				config::getLogger()->addError('sending mail using sendgrid failed, statusCode='.$response->statusCode());
				config::getLogger()->addError('sending mail using sendgrid failed, body='.$response->body());
				config::getLogger()->addError('sending mail using sendgrid failed, headers='.var_export($response->headers(), true));
			}
			$subscription->setPlanChangeId($toProviderPlan->getId());
			$subscription = BillingsSubscriptionDAO::updatePlanChangeId($subscription);
			$subscription->setPlanChangeNotified(true);
			$subscription = BillingsSubscriptionDAO::updatePlanChangeNotified($subscription);
			ScriptsConfig::getLogger()->addInfo("subscription with uuid=".$subscription->getSubscriptionBillingUuid()." notifying plan change successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("an error occurred while notifying plan change for subscription with uuid=".$subscription->getSubscriptionBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage());
			throw $e;
		}
	}
	
	public function doUsersPlanChange($fromInternalPlanUuid) {
		try {
			ScriptsConfig::getLogger()->addInfo("processing Plan Change from : ".$fromInternalPlanUuid."...");
			$now = new DateTime();
			$fromInternalPlan = InternalPlanDAO::getInternalPlanByUuid($fromInternalPlanUuid, $this->platform->getId());
			if($fromInternalPlan == NULL) {
				ScriptsConfig::getLogger()->addError("no internalPlan with uuid : ".$fromInternalPlanUuid);
				return;
			}
			$supportedProviderNames = ['recurly'];
			foreach($supportedProviderNames as $supportedProviderName) {
				$provider = ProviderDAO::getProviderByName($supportedProviderName, $this->platform->getId());
				if($provider == NULL) {
					ScriptsConfig::getLogger()->addError("no provider found named : ".$supportedProviderName);
					break;
				}
				$fromProviderPlan = PlanDAO::getPlanByInternalPlanId($fromInternalPlan->getId(), $provider->getId());
				if($fromProviderPlan == NULL) {
					ScriptsConfig::getLogger()->addError("no plan associated to internalPlan with uuid : ".$fromInternalPlan->getInternalPlanUuid()." with provider name : ".$supportedProviderName);
					break;
				}
				$subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByPlanId($fromProviderPlan->getId());
				foreach ($subscriptions as $subscription) {
					try {
						//
						if($subscription->getPlanChangeNotified() == true) {
							if($subscription->getPlanChangeProcessed() == false) {
								if($subscription->getSubStatus() == 'active') {
									$subPeriodEndsDate = $subscription->getSubPeriodEndsDate();
									//
									$diffInDays = $now->diff($subPeriodEndsDate)->days;
									if($subPeriodEndsDate > $now && $diffInDays < $this->changeDaysAgoCounter) {
										$this->doUserPlanChange($subscription);
									} else  {
										ScriptsConfig::getLogger()->addInfo("subscription with uuid=".$subscription->getSubscriptionBillingUuid()." ignored, because it is too early, diffInDays=".$diffInDays);
									}
								} else {
									ScriptsConfig::getLogger()->addInfo("subscription with uuid=".$subscription->getSubscriptionBillingUuid()." ignored, because subStatus is NOT active");
								}
							} else {
								ScriptsConfig::getLogger()->addInfo("subscription with uuid=".$subscription->getSubscriptionBillingUuid()." ignored, because it has already been processed");
							}
						} else {
							ScriptsConfig::getLogger()->addInfo("subscription with uuid=".$subscription->getSubscriptionBillingUuid()." ignored, because it has not been notified yet");
						}
						//
					} catch(Exception $e) {
						ScriptsConfig::getLogger()->addError("subscription with uuid=".$subscription->getSubscriptionBillingUuid()." failed to be notified, error_code=".$e->getCode().", error_message=".$e->getMessage());
					}
				}
			}
			ScriptsConfig::getLogger()->addInfo("processing Plan Change from : ".$fromInternalPlanUuid." done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("processing Plan Change from : ".$fromInternalPlanUuid." failed, error_code=".$e->getCode().", error_message=".$e->getMessage());
			throw $e;
		}
	}
	
	private function doUserPlanChange(BillingsSubscription $subscription) {
		try {
			ScriptsConfig::getLogger()->addInfo("subscription with uuid=".$subscription->getSubscriptionBillingUuid()." processing plan change...");
			$toProviderPlan = PlanDAO::getPlanById($subscription->getPlanChangeId());
			if($toProviderPlan == NULL) {
				//Exception
				$msg = "no providerPlan found with id=".$subscription->getPlanChangeId();
				throw new Exception($msg);
			}
			$toInternalPlan = InternalPlanDAO::getInternalPlanById($toProviderPlan->getInternalPlanId());
			if($toInternalPlan == NULL) {
				//Exception
				$msg = "no internalPlan found with id=".$toProviderPlan->getInternalPlanId();
				throw new Exception($msg);
			}
			//
			$subscriptionsHandler = new SubscriptionsHandler();
			$updateInternalPlanSubscriptionRequest = new UpdateInternalPlanSubscriptionRequest();
			$updateInternalPlanSubscriptionRequest->setOrigin('script');
			$updateInternalPlanSubscriptionRequest->setPlatform($this->platform);
			$updateInternalPlanSubscriptionRequest->setSubscriptionBillingUuid($subscription->getSubscriptionBillingUuid());
			$updateInternalPlanSubscriptionRequest->setInternalPlanUuid($toInternalPlan->getInternalPlanUuid());
			$updateInternalPlanSubscriptionRequest->setTimeframe('atRenewal');
			$subscription = $subscriptionsHandler->doUpdateInternalPlanSubscription($updateInternalPlanSubscriptionRequest);
			//done
			$subscription->setPlanChangeProcessed(true);
			$subscription = BillingsSubscriptionDAO::updatePlanChangeProcessed($subscription);
			ScriptsConfig::getLogger()->addInfo("subscription with uuid=".$subscription->getSubscriptionBillingUuid()." processing plan change done successfully");
		} catch(Exception $e) {
			ScriptsConfig::getLogger()->addError("an error occurred while processing plan change for subscription with uuid=".$subscription->getSubscriptionBillingUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage());
			throw $e;
		}
	}

}

?>