<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../ProviderHandlersBuilder.php';
require_once __DIR__ . '/../requests/ExpireSubscriptionRequest.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';

use Money\Money;
use Money\Currency;

class ProviderSubscriptionsHandler {
	
	protected $provider = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
	}
	
	public function doGetUserSubscriptions(User $user) {
		$subscriptions = BillingsSubscriptionDAO::getBillingsSubscriptionsByUserId($user->getId());
		$subscriptions = $this->doFillSubscriptions($subscriptions);
		return($subscriptions);
	}
	
	public function doFillSubscription(BillingsSubscription $subscription = NULL) {
		if($subscription == NULL) {
			return NULL;
		}
		//--> DEFAULT
		// check if subscription still in trial to provide information in boolean mode through inTrial() method
		$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($subscription->getPlanId()));
	
		if ($internalPlan->getTrialEnabled() && !is_null($subscription->getSubActivatedDate())) {
	
			$subscriptionDate = clone $subscription->getSubActivatedDate();
			$subscriptionDate->modify('+ '.$internalPlan->getTrialPeriodLength().' '.$internalPlan->getTrialPeriodUnit());
	
			$subscription->setInTrial(($subscriptionDate->getTimestamp() > time()));
		}
	
		// set cancelable status regarding cycle on internal plan
		if ($internalPlan->getCycle()->getValue() === PlanCycle::once) {
			$subscription->setIsCancelable(false);
		} else {
			$array_status_cancelable = ['future', 'active'];
			if(in_array($subscription->getSubStatus() , $array_status_cancelable)) {
				$subscription->setIsCancelable(true);
			} else {
				$subscription->setIsCancelable(false);
			}
		}
		$array_status_expirable = ['future', 'active', 'canceled'];
		if(in_array($subscription->getSubStatus(), $array_status_expirable)) {
			$subscription->setIsExpirable(true);
		}
		//<-- DEFAULT
		return($subscription);
	}
	
	public function doFillSubscriptions($subscriptions) {
		foreach ($subscriptions as $subscription) {
			$this->doFillSubscription($subscription);
		}
		return($subscriptions);
	}

	protected function getCouponInfos($couponCode, Provider $provider, User $user, InternalPlan $internalPlan) {
		//
		$out = array();
		$internalCoupon = NULL;
		$internalCouponsCampaign = NULL;
		$providerCouponsCampaign = NULL;
		$userInternalCoupon = NULL;
		//
		$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponByCode($couponCode);
		if($internalCoupon == NULL) {
			$msg = "coupon : code=".$couponCode." NOT FOUND";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_CODE_NOT_FOUND);
		}
		//Check internalCoupon
		if($internalCoupon->getStatus() == 'redeemed') {
			$msg = "coupon : code=".$couponCode." already redeemed";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_REDEEMED);
		}
		if($internalCoupon->getStatus() == 'expired') {
			$msg = "coupon : code=".$couponCode." expired";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_EXPIRED);
		}
		if($internalCoupon->getStatus() == 'pending') {
			$msg = "coupon : code=".$couponCode." pending";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_PENDING);
		}
		if($internalCoupon->getStatus() != 'waiting') {
			$msg = "coupon : code=".$couponCode." cannot be used";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_NOT_READY);
		}
		//
		$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($internalCoupon->getInternalCouponsCampaignsId());
		if($internalCouponsCampaign == NULL) {
			$msg = "unknown internalCouponsCampaign with id : ".$internalCoupon->getInternalCouponsCampaignsId();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		//Check compatibility
		$isProviderCompatible = false;
		$providerCouponsCampaigns = BillingProviderCouponsCampaignDAO::getBillingProviderCouponsCampaignsByInternalCouponsCampaignsId($internalCouponsCampaign->getId());
		foreach ($providerCouponsCampaigns as $currentProviderCouponsCampaign) {
			if($currentProviderCouponsCampaign->getProviderId() == $provider->getId()) {
				$providerCouponsCampaign = $currentProviderCouponsCampaign;
				$isProviderCompatible = true;
				break;
			}
		}
		if($isProviderCompatible == false) {
			//Exception
			$msg = "internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid()." is not associated with provider : ".$provider->getName();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_PROVIDER_INCOMPATIBLE);
		}
		$billingInternalCouponsCampaignInternalPlans = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlansByInternalCouponsCampaignsId($internalCouponsCampaign->getId());
		$isInternalPlanCompatible = false;
		foreach($billingInternalCouponsCampaignInternalPlans as $billingInternalCouponsCampaignInternalPlan) {
			if($billingInternalCouponsCampaignInternalPlan->getInternalPlanId() == $internalPlan->getId()) {
				$isInternalPlanCompatible = true; break;
			}
		}
		if($isInternalPlanCompatible == false) {
			//Exception
			$msg = "internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid()." is not associated with internalPlan : ".$internalPlan->getInternalPlanUuid();
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_INTERNALPLAN_INCOMPATIBLE);
		}
		//
		$userInternalCoupons = BillingUserInternalCouponDAO::getBillingUserInternalCouponsByUserId($user->getId(), $internalCoupon->getId());
		if(count($userInternalCoupons) > 0) {
			//TAKING FIRST (EQUALS LAST GENERATED)
			$userInternalCoupon = $userInternalCoupons[0];
		} else {
			$userInternalCoupon = new BillingUserInternalCoupon();
			$userInternalCoupon->setInternalCouponsId($internalCoupon->getId());
			$userInternalCoupon->setCode($internalCoupon->getCode());
			$userInternalCoupon->setUuid(guid());
			$userInternalCoupon->setUserId($user->getId());
			$userInternalCoupon->setExpiresDate($internalCoupon->getExpiresDate());
			$userInternalCoupon = BillingUserInternalCouponDAO::addBillingUserInternalCoupon($userInternalCoupon);
		}
		//Check userInternalCoupon
		if($userInternalCoupon->getStatus() == 'redeemed') {
			$msg = "coupon : code=".$couponCode." already redeemed";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_REDEEMED);
		}
		if($userInternalCoupon->getStatus() == 'expired') {
			$msg = "coupon : code=".$couponCode." expired";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_EXPIRED);
		}
		if($userInternalCoupon->getStatus() == 'pending') {
			$msg = "coupon : code=".$couponCode." pending";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_PENDING);
		}
		if($userInternalCoupon->getStatus() != 'waiting') {
			$msg = "coupon : code=".$couponCode." cannot be used";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_NOT_READY);
		}
		if($userInternalCoupon->getSubId() != NULL) {
			$msg = "coupon : code=".$couponCode." is already linked to another subscription";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::COUPON_ALREADY_LINKED);
		}
		$out['internalCoupon'] = $internalCoupon;
		$out['internalCouponsCampaign'] = $internalCouponsCampaign;
		$out['providerCouponsCampaign'] = $providerCouponsCampaign;
		$out['userInternalCoupon'] = $userInternalCoupon;
		return($out);
	}
	
	public function doExpireSubscription(BillingsSubscription $subscription, ExpireSubscriptionRequest $expireSubscriptionRequest) {
		$msg = "unsupported feature for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		try {
			config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid()."...");
			$subscription_is_new_event = false;
			$subscription_is_canceled_event = false;
			$subscription_is_expired_event = false;
			$sendgrid_template_id = NULL;
			$event = NULL;
			//check subscription_is_new_event
			if($subscription_before_update == NULL) {
				if($subscription_after_update->getSubStatus() == 'active') {
					$subscription_is_new_event = true;
				}
			} else {
				if(
						($subscription_before_update->getSubStatus() != 'active')
						&&
						($subscription_after_update->getSubStatus() == 'active')
						)	{
							$subscription_is_new_event = true;
						}
			}
			if($subscription_is_new_event == true) {
				$sendgrid_template_id = getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_NEW_ID');
				$event = "NEW";
			}
			//check subscription_is_canceled_event
			if($subscription_before_update == NULL) {
				if($subscription_after_update->getSubStatus() == 'canceled') {
					$subscription_is_canceled_event = true;
				}
			} else {
				if(
						($subscription_before_update->getSubStatus() != 'canceled')
						&&
						($subscription_after_update->getSubStatus() == 'canceled')
						)	{
							$subscription_is_canceled_event = true;
						}
			}
			if($subscription_is_canceled_event == true) {
				$sendgrid_template_id = getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_CANCEL_ID');
				$event = "CANCEL";
			}
			//check subscription_is_expired_event
			if($subscription_before_update == NULL) {
				if($subscription_after_update->getSubStatus() == 'expired') {
					$subscription_is_expired_event = true;
				}
			} else {
				if(
						($subscription_before_update->getSubStatus() != 'expired')
						&&
						($subscription_after_update->getSubStatus() == 'expired')
						)	{
							$subscription_is_expired_event = true;
						}
			}
			if($subscription_is_expired_event == true) {
				if($subscription_after_update->getSubExpiresDate() == $subscription_after_update->getSubCanceledDate()) {
					$event = "ENDED_FP";
					$sendgrid_template_id = getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_ENDED_FP_ID');//FP = FAILED PAYMENT
				} else {
					$event = "ENDED";
					$sendgrid_template_id = getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_ENDED_ID');
				}
			}
			$hasEvent = ($event != NULL);
			if($hasEvent) {
				$this->doSendEmail($subscription_after_update, $event, $this->selectSendgridTemplateId($event));
				switch ($event) {
					case 'NEW' :
						//nothing to do : creation is made only by API calls already traced
						break;
					case 'CANCEL' :
						BillingStatsd::inc('route.providers.all.subscriptions.cancel.success');
						break;
					case 'ENDED_FP' :
					case 'ENDED' :
						BillingStatsd::inc('route.providers.all.subscriptions.expire.success');
						break;
					default :
						//nothing to do
						break;
				}
			}
			config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid()." done successfully");
		} catch(Exception $e) {
			config::getLogger()->addError("an error occurred while processing subscription event for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", message=".$e->getMessage());
		}
	}
	
	private function selectSendgridTemplateId($event) {
		$templateNames = array();
		$defaultTemplateName = 'SUBSCRIPTION'.'_'.$event;
		$templateNames[] = $defaultTemplateName;
		//SPECIFIC
		$specific = NULL;
		//TODO : later
		$specificTemplateName = NULL;
		if(isset($specific)) {
			$specificTemplateName = 'SUBSCRIPTION'.'_'.$specific.'_'.$event;
			$templateNames[] = $specificTemplateName;
		}
		$providerTemplateName = $defaultTemplateName.'_'.strtoupper($this->provider->getName());
		$templateNames[] = $providerTemplateName;
		$specificProviderTemplateName = NULL;
		if(isset($specificTemplateName)) {
			$specificProviderTemplateName = $specificTemplateName.'_'.strtoupper($this->provider->getName());
			$templateNames[] = $specificProviderTemplateName;
		}
		//NOW SEARCH TEMPLATE IN DATABASE
		$billingMailTemplate = NULL;
		while(($templateName = array_pop($templateNames)) != NULL) {
			$billingMailTemplate = BillingMailTemplateDAO::getBillingMailTemplateByTemplateName($templateName);
			if(isset($billingMailTemplate)) {
				config::getLogger()->addInfo("found template named : ".$templateName);
				return($billingMailTemplate->getTemplatePartnerUuid());
			}
		}
		$msg = "event by email : no template was found";
		config::getLogger()->addError($msg);
		//throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		return(NULL);
	}
	
	private function doSendEmail($subscription_after_update, $event, $sendgrid_template_id) {
		try {
			config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=".$event.", sending mail...");
			if(getEnv('EVENT_EMAIL_ACTIVATED') != 1) {
				config::getLogger()->addInfo("event by email : email is inactive");
				return;
			}
			if(empty($sendgrid_template_id)) {
				config::getLogger()->addInfo("event by email : no template found for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=".$event);
				return;
			}
			$eventEmailProvidersExceptionArray = explode(";", getEnv('EVENT_EMAIL_PROVIDERS_EXCEPTION'));
			$provider = ProviderDAO::getProviderById($subscription_after_update->getProviderId());
			if($provider == NULL) {
				$msg = "unknown provider with id : ".$subscription_after_update->getProviderId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(in_array($provider->getName(), $eventEmailProvidersExceptionArray)) {
				config::getLogger()->addInfo("event by email : ignored for providerName=".$provider->getName()." for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=".$event);
				return;
			}
			$user = UserDAO::getUserById($subscription_after_update->getUserId());
			if($user == NULL) {
				$msg = "unknown user with id : ".$subscription_after_update->getUserId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(in_array($event, ['ENDED', 'ENDED_FP'])) {
				if($this->hasFutureSubscription($user, $subscription_after_update)) {
					config::getLogger()->addInfo("event by email : ignored - has a future subscription - for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=".$event);
					return;
				}
			}
			$userOpts = UserOptsDAO::getUserOptsByUserId($user->getId());
			$emailTo = NULL;
			if(array_key_exists('email', $userOpts->getOpts())) {
				$emailTo = $userOpts->getOpts()['email'];
			}
			//DATA -->
			$providerPlan = PlanDAO::getPlanById($subscription_after_update->getPlanId());
			if($providerPlan == NULL) {
				$msg = "unknown plan with id : ".$subscription_after_update->getPlanId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($providerPlan->getId()));
			if($internalPlan == NULL) {
				$msg = "plan with uuid=".$providerPlan->getPlanUuid()." for provider ".$provider->getName()." is not linked to an internal plan";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalPlanOpts = InternalPlanOptsDAO::getInternalPlanOptsByInternalPlanId($internalPlan->getId());
			$userInternalCoupon = BillingUserInternalCouponDAO::getBillingUserInternalCouponBySubId($subscription_after_update->getId());
			$internalCoupon = NULL;
			$internalCouponsCampaign = NULL;
			if(isset($userInternalCoupon)) {
				$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponById($userInternalCoupon->getInternalCouponsId());
				$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($internalCoupon->getInternalCouponsCampaignsId());
			}
			//DATA <--
			//DATA SUBSTITUTION -->
			setlocale(LC_MONETARY, 'fr_FR.utf8');//TODO : Forced to French Locale for "," in floats...
			$substitions = array();
			//user
			$substitions['%userreferenceuuid%'] = $user->getUserReferenceUuid();
			$substitions['%userbillinguuid%'] = $user->getUserBillingUuid();
			//provider : nothing
			//providerPlan : nothing
			//internalPlan :
			$substitions['%internalplanname%'] = $internalPlan->getName();
			$substitions['%internalplandesc%'] = $internalPlan->getDescription();
			$substitions['%amountincents%'] = $internalPlan->getAmountInCents();
			$amountInMoney = new Money((integer) $internalPlan->getAmountInCents(), new Currency($internalPlan->getCurrency()));
			$substitions['%amount%'] = money_format('%!.2n', (float) ($amountInMoney->getAmount() / 100));
			$substitions['%amountincentsexcltax%'] = $internalPlan->getAmountInCentsExclTax();
			$amountExclTaxInMoney = new Money((integer) $internalPlan->getAmountInCentsExclTax(), new Currency($internalPlan->getCurrency()));
			$substitions['%amountexcltax%'] = money_format('%!.2n', (float) ($amountExclTaxInMoney->getAmount() / 100));
			if($internalPlan->getVatRate() == NULL) {
				$substitions['%vat%'] = 'N/A';
			} else {
				$substitions['%vat%'] = number_format($internalPlan->getVatRate(), 2, ',', '').'%';
			}
			$substitions['%amountincentstax%'] = $internalPlan->getAmountInCents() - $internalPlan->getAmountInCentsExclTax();
			$amountTaxInMoney = new Money((integer) ($internalPlan->getAmountInCents() - $internalPlan->getAmountInCentsExclTax()), new Currency($internalPlan->getCurrency()));
			$substitions['%amounttax%'] = money_format('%!.2n', (float) ($amountTaxInMoney->getAmount() / 100));
			$substitions['%currency%'] = $internalPlan->getCurrencyForDisplay();
			$substitions['%cycle%'] = $internalPlan->getCycle();
			$substitions['%periodunit%'] = $internalPlan->getPeriodUnit();
			$substitions['%periodlength%'] = $internalPlan->getPeriodLength();
			//user : nothing
			//userOpts
			$substitions['%email%'] = ($emailTo == NULL ? '' : $emailTo);
			$firstname = '';
			if(array_key_exists('firstName', $userOpts->getOpts())) {
				$firstname = $userOpts->getOpts()['firstName'];
			}
			if($firstname == 'firstNameValue') {
				$firstname = '';
			}
			$substitions['%firstname%'] = $firstname;
			$lastname = '';
			if(array_key_exists('lastName', $userOpts->getOpts())) {
				$lastname = $userOpts->getOpts()['lastName'];
			}
			if($lastname == 'lastNameValue') {
				$lastname = '';
			}
			$substitions['%lastname%'] = $lastname;
			$username = $firstname;
			if($username == '') {
				if(!empty($emailTo)) {
					$username = explode('@', $emailTo)[0];
				}
			}
			$substitions['%username%'] = $username;
			$fullname = trim($firstname." ".$lastname);
			$substitions['%fullname%'] = $fullname;
			//subscription
			$substitions['%subscriptionbillinguuid%'] = $subscription_after_update->getSubscriptionBillingUuid();
			//Coupon
			$substitions['%couponCode%'] = '';
			$substitions['%couponAmountForDisplay%'] = '';
			$substitions['%couponDetails%'] = '';
			$substitions['%couponAppliedSentence%'] = '';
			if(isset($internalCouponsCampaign) && $internalCouponsCampaign->getCouponType() == 'promo') {
				$couponAmountForDisplay = '';
				switch($internalCouponsCampaign->getDiscountType()) {
					case 'percent' :
						$couponAmountForDisplay = $internalCouponsCampaign->getPercent().'%';
						break;
					case 'amount' :
						$couponAmountForDisplay = new Money((integer) $internalCouponsCampaign->getAmountInCents(), new Currency($internalCouponsCampaign->getCurrency()));
						$couponAmountForDisplay = money_format('%!.2n', (float) ($couponAmountForDisplay->getAmount() / 100));
						$couponAmountForDisplay = $couponAmountForDisplay.' '.dbGlobal::getCurrencyForDisplay($internalCouponsCampaign->getCurrency());
						break;
				}
				$substitions['%couponCode%'] = $userInternalCoupon->getCode();
				$substitions['%couponAmountForDisplay%'] = $couponAmountForDisplay;
				$substitions['%couponDetails%'] = $internalCouponsCampaign->getDescription();
				$couponAppliedSentence = getEnv('SENDGRID_VAR_couponAppliedSentence');
				$couponAppliedSentence = str_replace(array_keys($substitions), array_values($substitions), $couponAppliedSentence);
				$substitions['%couponAppliedSentence%'] = $couponAppliedSentence;
			}
			//DATA SUBSTITUTION <--
			$sendgrid = new SendGrid(getEnv('SENDGRID_API_KEY'));
			$email = new SendGrid\Email();
			$email->addTo(!empty($emailTo) ? $emailTo : getEnv('SENDGRID_TO_IFNULL'));
			$email
			->setFrom(getEnv('SENDGRID_FROM'))
			->setFromName(getEnv('SENDGRID_FROM_NAME'))
			->setSubject(' ')
			->setText(' ')
			->setHtml(' ')
			->setTemplateId($sendgrid_template_id);
			if((null !== (getEnv('SENDGRID_BCC'))) && ('' !== (getEnv('SENDGRID_BCC')))) {
				$email->setBcc(getEnv('SENDGRID_BCC'));
				foreach($substitions as $var => $val) {
					$vals = array($val, $val);//Bcc (same value twice (To + Bcc))
					$email->addSubstitution($var, $vals);
				}
			} else {
				foreach($substitions as $var => $val) {
					$email->addSubstitution($var, array($val));//once (To)
				}
			}
			$sendgrid->send($email);
			config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=".$event.", sending mail done successfully");
		} catch(\SendGrid\Exception $e) {
			$msg = 'an error occurred while sending email for a new subscription event for subscriptionBillingUuid='.$subscription_after_update->getSubscriptionBillingUuid().', event='.$event.', error_code='.$e->getCode().', error_message=';
			$firstLoop = true;
			foreach($e->getErrors() as $er) {
				if($firstLoop == true) {
					$firstLoop = false;
					$msg.= $er;
				} else {
					$msg.= ", ".$er;
				}
			}
			config::getLogger()->addError($msg);
			throw $e;
		} catch(Exception $e) {
			$msg = 'an error occurred while sending email for a new subscription event for subscriptionBillingUuid='.$subscription_after_update->getSubscriptionBillingUuid().', event='.$event.', error_code='.$e->getCode().', error_message=';
			config::getLogger()->addError($msg);
			throw $e;
		}
	}
	
	protected function hasFutureSubscription(User $user, BillingsSubscription $currentBillingsSubscription) {
		$subscriptionsHandler = new SubscriptionsHandler();
		$subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUserReferenceUuid($user->getUserReferenceUuid());
		if(count($subscriptions) > 0) {
			foreach ($subscriptions as $subscription) {
				if($subscription->getId() != $currentBillingsSubscription->getId()) {
					if($subscription->getSubStatus() == 'future') {
						//NC : Quite risky : For now, we do not filter with dates. We wait for first expirations to decide if we filter or not
						/*$futureSubActivatedDate = $subscription->getSubActivatedDate();
						$currentSubExpiresDate = $currentBillingsSubscription->getSubExpiresDate();
						$diffInSeconds = $futureSubActivatedDate->format('U') - $currentSubExpiresDate->format('U');
						if($diffInSeconds == 0) {
						return(true);
						}*/
						return(true);
					}
				}
			}
		}
		return(false);
	}
	
}

?>