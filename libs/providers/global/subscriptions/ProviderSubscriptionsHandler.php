<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../ProviderHandlersBuilder.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../requests/ExpireSubscriptionRequest.php';
require_once __DIR__ . '/../requests/ReactivateSubscriptionRequest.php';
require_once __DIR__ . '/../requests/CancelSubscriptionRequest.php';
require_once __DIR__ . '/../requests/DeleteSubscriptionRequest.php';
require_once __DIR__ . '/../requests/RenewSubscriptionRequest.php';
require_once __DIR__ . '/../requests/UpdateInternalPlanSubscriptionRequest.php';

use Money\Money;
use Money\Currency;

class ProviderSubscriptionsHandler {
	
	protected $provider = NULL;
	protected $platform = NULL;
	
	public function __construct(Provider $provider) {
		$this->provider = $provider;
		$this->platform = BillingPlatformDAO::getPlatformById($this->provider->getPlatformId());
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
		$internalPlan = InternalPlanDAO::getInternalPlanByProviderPlanId($subscription->getPlanId());
	
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

	protected function getCouponInfos($couponCode, User $user, InternalPlan $internalPlan) {
		//
		$out = array();
		$internalCoupon = NULL;
		$internalCouponsCampaign = NULL;
		$providerCouponsCampaign = NULL;
		$userInternalCoupon = NULL;
		//
		$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponByCode($couponCode, $this->provider->getPlatformId());
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
			if($currentProviderCouponsCampaign->getProviderId() == $this->provider->getId()) {
				$providerCouponsCampaign = $currentProviderCouponsCampaign;
				$isProviderCompatible = true;
				break;
			}
		}
		if($isProviderCompatible == false) {
			//Exception
			$msg = "internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid()." is not associated with provider : ".$this->provider->getName();
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
			$userInternalCoupon->setPlatformId($this->provider->getPlatformId());
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
		$msg = "unsupported feature - expire subscription - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		try {
			config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid()."...");
			$event = NULL;
			//check subscription_is_new_event
			$switchEvent = NULL;
			$afterUpdateMergedStatus = self::mergeStatus($subscription_after_update->getSubStatus());
			switch($afterUpdateMergedStatus) {
				case 'active' :
					$switchEvent = 'NEW';
					break;
				case 'canceled' :
					$switchEvent = 'CANCEL';
					break;
				case 'expired' :
					if($subscription_after_update->getSubExpiresDate() == $subscription_after_update->getSubCanceledDate()) {
						$switchEvent = 'ENDED_FP';
					} else {
						$switchEvent = 'ENDED';
					}
					break;
				case 'future' :
					$switchEvent = 'FUTURE';
					break;
			}
			if(isset($switchEvent)) {
				//There is only a real event, if object was NULL or SubStatus are different
				if($subscription_before_update == NULL) {
					$event = $switchEvent;
				} else {
					//subStatus are 'merged' before comparing
					$beforeUpdateMergedStatus = self::mergeStatus($subscription_before_update->getSubStatus());
					if($beforeUpdateMergedStatus != $afterUpdateMergedStatus) {
						$event = $switchEvent;
					}
				}
			}
			$hasEvent = ($event != NULL);
			if($hasEvent) {
				$this->doSendEmail($subscription_after_update, $event, $this->selectSendgridTemplateId($subscription_after_update, $event));
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
					case 'FUTURE' :
						BillingStatsd::inc('route.providers.all.subscriptions.future.success');
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
	
	private function selectSendgridTemplateId(BillingsSubscription $subscription_after_update, $event) {
		$suffix = getEnv('SENDGRID_TEMPLATE_SUFFIX');
		$templateNames = array();
		$defaultTemplateName = 'SUBSCRIPTION'.'_'.$event;
		$templateNames[] = $defaultTemplateName;
		$templateNames[] = $defaultTemplateName.$suffix;
		$templateNames[] = strtoupper($this->platform->getName()).'_'.$defaultTemplateName;
		$templateNames[] = strtoupper($this->platform->getName()).'_'.$defaultTemplateName.$suffix;
		//SPECIFIC
		$specific = NULL;
		if($this->isSponsorshipSubscription($subscription_after_update)) {
			$specific = 'SPONSORSHIP';
		}
		$specificTemplateName = NULL;
		if(isset($specific)) {
			$specificTemplateName = 'SUBSCRIPTION'.'_'.$specific.'_'.$event;
			$templateNames[] = $specificTemplateName;
			$templateNames[] = $specificTemplateName.$suffix;
			$templateNames[] = strtoupper($this->platform->getName()).'_'.$specificTemplateName;
			$templateNames[] = strtoupper($this->platform->getName()).'_'.$specificTemplateName.$suffix;
		}
		$providerTemplateName = $defaultTemplateName.'_'.strtoupper($this->provider->getName());
		$templateNames[] = $providerTemplateName;
		$templateNames[] = $providerTemplateName.$suffix;
		$templateNames[] = strtoupper($this->platform->getName()).'_'.$providerTemplateName;
		$templateNames[] = strtoupper($this->platform->getName()).'_'.$providerTemplateName.$suffix;
		$specificProviderTemplateName = NULL;
		if(isset($specificTemplateName)) {
			$specificProviderTemplateName = $specificTemplateName.'_'.strtoupper($this->provider->getName());
			$templateNames[] = $specificProviderTemplateName;
			$templateNames[] = $specificProviderTemplateName.$suffix;
			$templateNames[] = strtoupper($this->platform->getName()).'_'.$specificProviderTemplateName;
			$templateNames[] = strtoupper($this->platform->getName()).'_'.$specificProviderTemplateName.$suffix;
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
	
	private function doSendEmail(BillingsSubscription $subscription_after_update, $event, $sendgrid_template_id) {
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
			if(in_array($this->provider->getName(), $eventEmailProvidersExceptionArray)) {
				config::getLogger()->addInfo("event by email : ignored for providerName=".$this->provider->getName()." for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=".$event);
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
			$internalPlan = InternalPlanDAO::getInternalPlanById($providerPlan->getInternalPlanId());
			if($internalPlan == NULL) {
				$msg = "plan with uuid=".$providerPlan->getPlanUuid()." for provider ".$this->provider->getName()." is not linked to an internal plan";
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
			$substitutions = array();
			//user
			$substitutions['%userreferenceuuid%'] = $user->getUserReferenceUuid();
			$substitutions['%userbillinguuid%'] = $user->getUserBillingUuid();
			//provider : nothing
			//providerPlan : nothing
			//internalPlan :
			$substitutions['%internalplanname%'] = $internalPlan->getName();
			$substitutions['%internalplandesc%'] = $internalPlan->getDescription();
			$substitutions['%amountincents%'] = $internalPlan->getAmountInCents();
			$amountInMoney = new Money((integer) $internalPlan->getAmountInCents(), new Currency($internalPlan->getCurrency()));
			$substitutions['%amount%'] = money_format('%!.2n', (float) ($amountInMoney->getAmount() / 100));
			$substitutions['%amountincentsexcltax%'] = $internalPlan->getAmountInCentsExclTax();
			$amountExclTaxInMoney = new Money((integer) $internalPlan->getAmountInCentsExclTax(), new Currency($internalPlan->getCurrency()));
			$substitutions['%amountexcltax%'] = money_format('%!.2n', (float) ($amountExclTaxInMoney->getAmount() / 100));
			if($internalPlan->getVatRate() == NULL) {
				$substitutions['%vat%'] = 'N/A';
			} else {
				$substitutions['%vat%'] = number_format($internalPlan->getVatRate(), 2, ',', '').'%';
			}
			$substitutions['%amountincentstax%'] = $internalPlan->getAmountInCents() - $internalPlan->getAmountInCentsExclTax();
			$amountTaxInMoney = new Money((integer) ($internalPlan->getAmountInCents() - $internalPlan->getAmountInCentsExclTax()), new Currency($internalPlan->getCurrency()));
			$substitutions['%amounttax%'] = money_format('%!.2n', (float) ($amountTaxInMoney->getAmount() / 100));
			$substitutions['%currency%'] = $internalPlan->getCurrencyForDisplay();
			$substitutions['%cycle%'] = $internalPlan->getCycle();
			$substitutions['%periodunit%'] = $internalPlan->getPeriodUnit();
			$substitutions['%periodlength%'] = $internalPlan->getPeriodLength();
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
			$substitutions['%subscriptionbillinguuid%'] = $subscription_after_update->getSubscriptionBillingUuid();
			//Coupon
			$substitutions['%couponCode%'] = '';
			$substitutions['%couponAmountForDisplay%'] = '';
			$substitutions['%couponDetails%'] = '';
			$substitutions['%couponAppliedSentence%'] = '';
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
				$substitutions['%couponCode%'] = $userInternalCoupon->getCode();
				$substitutions['%couponAmountForDisplay%'] = $couponAmountForDisplay;
				$substitutions['%couponDetails%'] = $internalCouponsCampaign->getDescription();
				$couponAppliedSentence = getEnv('SENDGRID_VAR_couponAppliedSentence');
				$couponAppliedSentence = str_replace(array_keys($substitutions), array_values($substitutions), $couponAppliedSentence);
				$substitutions['%couponAppliedSentence%'] = $couponAppliedSentence;
			}
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
			config::getLogger()->addInfo("subscription event processing for subscriptionBillingUuid=".$subscription_after_update->getSubscriptionBillingUuid().", event=".$event.", sending mail done successfully");
		} catch(Exception $e) {
			$msg = 'an error occurred while sending email for a new subscription event for subscriptionBillingUuid=';
			$msg.= $subscription_after_update->getSubscriptionBillingUuid().', event='.$event.', error_code='.$e->getCode().', error_message='.$e->getMessage();
			config::getLogger()->addError($msg);
			throw $e;
		}
	}
	
	protected function hasFutureSubscription(User $user, BillingsSubscription $currentBillingsSubscription) {
		$subscriptionsHandler = new SubscriptionsHandler();
		$subscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUserReferenceUuid($user->getUserReferenceUuid(), $user->getPlatformId());
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
	
	protected function isSponsorshipSubscription(BillingsSubscription $currentBillingsSubscription) {
		$userInternalCoupon = BillingUserInternalCouponDAO::getBillingUserInternalCouponBySubId($currentBillingsSubscription->getId());
		if(isset($userInternalCoupon)) {
			$internalCoupon = BillingInternalCouponDAO::getBillingInternalCouponById($userInternalCoupon->getInternalCouponsId());
			if($internalCoupon == NULL) {
				$msg = "no internal coupon found linked to user coupon with uuid=".$userInternalCoupon->getUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$internalCouponsCampaign = BillingInternalCouponsCampaignDAO::getBillingInternalCouponsCampaignById($internalCoupon->getInternalCouponsCampaignsId());
			if($internalCouponsCampaign == NULL) {
				$msg = "unknown internalCouponsCampaign with id : ".$internalCoupon->getInternalCouponsCampaignsId();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if($internalCouponsCampaign->getCouponType() == CouponCampaignType::sponsorship) {
				return(true);
			}
		}
		return(false);
	}
	
	public function doReactivateSubscription(BillingsSubscription $subscription, ReactivateSubscriptionRequest $reactivateSubscriptionRequest) {
		$msg = "unsupported feature - reactivate subscription - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	public function doCancelSubscription(BillingsSubscription $subscription, CancelSubscriptionRequest $cancelSubscriptionRequest) {
		$msg = "unsupported feature - cancel subscription - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	public function doDeleteSubscription(BillingsSubscription $subscription, DeleteSubscriptionRequest $deleteSubscriptionRequest) {
		$msg = "unsupported feature - delete subscription - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	public function doRenewSubscription(BillingsSubscription $subscription, RenewSubscriptionRequest $renewSubscriptionRequest) {
		$msg = "unsupported feature - renew subscription - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	public function doUpdateInternalPlanSubscription(BillingsSubscription $subscription, UpdateInternalPlanSubscriptionRequest $updateInternalPlanSubscriptionRequest) {
		$msg = "unsupported feature - update internalplan subscription - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	public function doCreateUserSubscription(User $user, 
			UserOpts $userOpts, 
			InternalPlan $internalPlan, 
			InternalPlanOpts $internalPlanOpts, 
			Plan $plan, 
			PlanOpts $planOpts, 
			$subscription_billing_uuid, 
			$subscription_provider_uuid, 
			BillingInfo $billingInfo, 
			BillingsSubscriptionOpts $subOpts) {
		$msg = "unsupported feature - create user subscription - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	public function createDbSubscriptionFromApiSubscriptionUuid(User $user, 
			UserOpts $userOpts, 
			InternalPlan $internalPlan = NULL, 
			InternalPlanOpts $internalPlanOpts = NULL, 
			Plan $plan = NULL, 
			PlanOpts $planOpts = NULL, 
			BillingsSubscriptionOpts $subOpts = NULL, 
			BillingInfo $billingInfo = NULL, 
			$subscription_billing_uuid, 
			$sub_uuid, 
			$update_type, 
			$updateId) {
		$msg = "unsupported feature - create user subscription from api subscription uuid - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	public function doUpdateUserSubscriptions(User $user, UserOpts $userOpts) {
		$msg = "unsupported feature - update user subscriptions - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	public function doUpdateUserSubscription(BillingsSubscription $db_subscription, UpdateSubscriptionRequest $updateSubscriptionRequest) {
		$msg = "unsupported feature - update subscription - for provider named : ".$this->provider->getName();
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg, ExceptionError::REQUEST_UNSUPPORTED);
	}
	
	/*
	 * active, pending_active => active
	 * canceled, requesting_canceled, pending_canceled => canceled
	 * future => future
	 * expired => pending_expired => expired
	 */
	private static function mergeStatus($subStatus) {
		$pos = strrpos($subStatus, "_");
		if($pos === false) {
			return($subStatus);
		} else {
			return(substr($subStatus, $pos + 1));
		}
	}
	
}

?>