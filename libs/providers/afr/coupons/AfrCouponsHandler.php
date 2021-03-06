<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../../global/coupons/ProviderCouponsHandler.php';

use Money\Money;
use Money\Currency;

class AfrCouponsHandler extends ProviderCouponsHandler {
	
	public function __construct(Provider $provider) {
		parent::__construct($provider);
		\Stripe\Stripe::setApiKey($this->provider->getApiSecret());
	}
	
	public function doCreateCoupon(User $user,
			UserOpts $userOpts,
			BillingInternalCouponsCampaign $internalCouponsCampaign,
			BillingProviderCouponsCampaign $providerCouponsCampaign,
			InternalPlan $internalPlan = NULL,
			$coupon_billing_uuid,
			BillingsCouponsOpts $billingCouponsOpts) {
		$coupon_provider_uuid = NULL;
		try {
			config::getLogger()->addInfo("afr coupon creation...");
			//
			//TODO : should check internalCouponsCampaign compatibility
			//
			//Checking InternalPlan Compatibility
			$billingInternalCouponsCampaignInternalPlans = BillingInternalCouponsCampaignInternalPlansDAO::getBillingInternalCouponsCampaignInternalPlansByInternalCouponsCampaignsId($internalCouponsCampaign->getId());
			if(count($billingInternalCouponsCampaignInternalPlans) == 0) {
				//Exception
				$msg = "no internalPlan associated to internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			} else if(count($billingInternalCouponsCampaignInternalPlans) == 1) {
				if($internalPlan == NULL) {
					$internalPlan = InternalPlanDAO::getInternalPlanById($billingInternalCouponsCampaignInternalPlans[0]->getInternalPlanId());
				}
			}
			if($internalPlan == NULL) {
				//Exception
				$msg = "no default internalPlan associated to internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$found = false;
			foreach ($billingInternalCouponsCampaignInternalPlans as $billingInternalCouponsCampaignInternalPlan) {
				if($internalPlan->getId() == $billingInternalCouponsCampaignInternalPlan->getInternalPlanId()) {
					$found = true; break;
				}
			}
			if($found == false) {
				//Exception
				$msg = "given internalPlan with uuid=".$internalPlan->getInternalPlanUuid()." is not associated to internalCouponsCampaign with uuid=".$internalCouponsCampaign->getUuid();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$separator = $this->getSeparator($internalCouponsCampaign);
			//
			$couponCode = strtoupper($internalCouponsCampaign->getPrefix().$separator.$this->getRandomString($internalCouponsCampaign->getGeneratedCodeLength()));
			
			switch ($internalCouponsCampaign->getCouponType()) {
				case CouponCampaignType::standard :
					if($internalCouponsCampaign->getEmailsEnabled() == true) {
						if (is_null($billingCouponsOpts->getOpt('recipientEmail'))) {
							throw new BillingsException(new ExceptionType(ExceptionType::internal), 'You must provide a recipient mail');
						}
					}
					if (is_null($billingCouponsOpts->getOpt('customerBankAccountToken'))) {
						throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Error while creating afr coupon. Missing stripe token');
					}
					$chargeData = [
							'amount' => $internalPlan->getAmountInCents(),
							'currency' => $internalPlan->getCurrency(),
							'description' => 'Coupon afrostream : '.$couponCode,
							'source' => $billingCouponsOpts->getOpt('customerBankAccountToken'),
							'metadata' => [
									'AfrSource' => 'afrBillingApi',
									'AfrOrigin' => 'coupon',
									'AfrCouponBillingUuid' => $coupon_billing_uuid,
									'AfrUserBillingUuid' => $user->getUserBillingUuid()
							]
					];
			
					// charge customer
					$charge = \Stripe\Charge::create($chargeData);
			
					if (!$charge->paid) {
						config::getLogger()->addError('Payment refused');
						throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Payment refused');
					}
			
					$billingCouponsOpts->setOpt('chargeId', $charge->id);
					break;
				case CouponCampaignType::sponsorship :
					if($internalCouponsCampaign->getEmailsEnabled() == true) {
						if (is_null($billingCouponsOpts->getOpt('recipientEmail'))) {
							throw new BillingsException(new ExceptionType(ExceptionType::internal), 'You must provide a recipient email');
						}
						$recipentEmail = $billingCouponsOpts->getOpt('recipientEmail');
						//Check if ownerEmail <> recipientEmail
						$ownerEmail = $userOpts->getOpt('email');
						if($ownerEmail == $recipentEmail) {
							throw new BillingsException(new ExceptionType(ExceptionType::internal), 'self sponsorship is forbidden', ExceptionError::AFR_COUPON_SPS_SELF_FORBIDDEN);
						}
						//Check if user has not been already sponsored
						$recipientEmailsTotalCounter = BillingUserInternalCouponDAO::getBillingUserInternalCouponsTotalNumberByRecipientEmails($recipentEmail, $internalCouponsCampaign->getCouponType(), $this->provider->getPlatformId());
						if($recipientEmailsTotalCounter > 0) {
							throw new BillingsException(new ExceptionType(ExceptionType::internal), 'recipient has already been sponsored', ExceptionError::AFR_COUPON_SPS_RECIPIENT_ALREADY_SPONSORED);
						}
						//Check if user has not already an active subscription
						$recipientUsers = UserDAO::getUsersByEmail($recipentEmail, $this->provider->getPlatformId());
						$subscriptionsHandler = new SubscriptionsHandler();
						foreach ($recipientUsers as $recipientUser) {
							$getUserSubscriptionsRequest = new GetUserSubscriptionsRequest();
							$getUserSubscriptionsRequest->setOrigin('api');
							$getUserSubscriptionsRequest->setUserBillingUuid($recipientUser->getUserBillingUuid());
							$getUserSubscriptionsRequest->setPlatform($this->platform);
							$recipientSubscriptions = $subscriptionsHandler->doGetUserSubscriptionsByUser($getUserSubscriptionsRequest);
							if(count($recipientSubscriptions) > 0) {
								$lastSubscription = $recipientSubscriptions[0];
								if($lastSubscription->getIsActive()) {
									throw new BillingsException(new ExceptionType(ExceptionType::internal), 'recipient that has an active subscription cannot be sponsored', ExceptionError::AFR_COUPON_SPS_RECIPIENT_ACTIVE_FORBIDDEN);
								}
							}
						}
					}
					break;
				case CouponCampaignType::prepaid :
					if($internalPlan->getAmount() > 0) {
						throw new BillingsException(new ExceptionType(ExceptionType::internal), 'prepaid coupons cannot be created here if amount > 0');
					}
					break;
				default :
					throw new BillingsException(new ExceptionType(ExceptionType::internal), 'unsupported feature for couponsCampaignType='.$internalCouponsCampaign->getCouponType()->getValue());
					break;
			}
			$coupon_provider_uuid = $couponCode;
			//<-- DB -->
			//Create an internalCoupon
			$internalCoupon = new BillingInternalCoupon();
			$internalCoupon->setInternalCouponsCampaignsId($internalCouponsCampaign->getId());
			$internalCoupon->setCode($coupon_provider_uuid);
			$internalCoupon->setUuid($coupon_billing_uuid);
			$internalCoupon->setExpiresDate(NULL);
			$internalCoupon->setPlatformId($this->provider->getPlatformId());
			$internalCoupon = BillingInternalCouponDAO::addBillingInternalCoupon($internalCoupon);
			//Create an userCoupon linked to the internalCoupon
			$userInternalCoupon = new BillingUserInternalCoupon();
			$userInternalCoupon->setInternalCouponsId($internalCoupon->getId());
			$userInternalCoupon->setCode($coupon_provider_uuid);
			$userInternalCoupon->setUuid($coupon_billing_uuid);
			$userInternalCoupon->setUserId($user->getId());
			$userInternalCoupon->setExpiresDate(NULL);
			$userInternalCoupon->setPlatformId($this->provider->getPlatformId());
			$userInternalCoupon = BillingUserInternalCouponDAO::addBillingUserInternalCoupon($userInternalCoupon);
			//Cretate an userCouponOpts linked to the userCoupon
			$billingUserInternalCouponOpts = new BillingUserInternalCouponOpts();
			$billingUserInternalCouponOpts->setOpts($billingCouponsOpts->getOpts());
			$billingUserInternalCouponOpts->setUserInternalCouponId($userInternalCoupon->getId());
			$billingUserInternalCouponOpts = BillingUserInternalCouponOptsDAO::addBillingUserInternalCouponOpts($billingUserInternalCouponOpts);
			//<-- DB -->
			$this->sendMails($user, $userOpts, $userInternalCoupon, $billingUserInternalCouponOpts, $internalPlan, $internalCouponsCampaign);
			config::getLogger()->addInfo("afr coupon creation done successfully, coupon_provider_uuid=".$coupon_provider_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating an afr coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr coupon creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating an afr coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr coupon creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($coupon_provider_uuid);
	}
	
	public function createDbCouponFromApiCouponUuid(User $user,
			UserOpts $userOpts,
			BillingInternalCouponsCampaign $internalCouponsCampaign,
			BillingProviderCouponsCampaign $providerCouponsCampaign,
			InternalPlan $internalPlan = NULL,
			$coupon_billing_uuid,
			$coupon_provider_uuid) {
		return(BillingUserInternalCouponDAO::getBillingUserInternalCouponByCouponBillingUuid($coupon_billing_uuid, $this->provider->getPlatformId()));
	}
	
	protected function getRandomString($length) {
		$strAlphaNumericString = '23456789bcdfghjkmnpqrstvwxz';
		$strlength             = strlen($strAlphaNumericString) -1 ;
		$strReturnString       = '';

		for ($intCounter = 0; $intCounter < $length; $intCounter++) {
			$strReturnString .= $strAlphaNumericString[random_int(0, $strlength)];
		}

		return $strReturnString;
	}
	
	protected function getSeparator(BillingInternalCouponsCampaign $billingInternalCouponsCampaign) {
		$partner = NULL;
		if($billingInternalCouponsCampaign->getPartnerId() != NULL) {
			$partner = BillingPartnerDAO::getPartnerById($billingInternalCouponsCampaign->getPartnerId());
		}
		if($partner != NULL) {
			if($partner->getName() == 'logista') {
				return("");//logista = alphanumeric only
			}
		}
		return("-");
	}
	
	protected function sendMails(User $user, 
			UserOpts $userOpts, 
			BillingUserInternalCoupon $billingUserInternalCoupon, 
			BillingUserInternalCouponOpts $billingUserInternalCouponOpts, 
			InternalPlan $internalPlan, 
			BillingInternalCouponsCampaign $internalCouponsCampaign) {
		if($internalCouponsCampaign->getEmailsEnabled() == false) {
			return;
		}
		$amountInCentsTax = ($internalPlan->getAmountInCents() - $internalPlan->getAmountInCentsExclTax());
		$firstName        = $userOpts->getOpt('firstName');
		$lastName         = $userOpts->getOpt('lastName');
		$userMail         = $userOpts->getOpt('email');

		$substitutions = [
			'%userreferenceuuid%'       => $user->getUserReferenceUuid(),
			'%userbillinguuid%'         => $user->getUserBillingUuid(),
			'%internalplanname%'        => $internalPlan->getName(),
			'%internalplandesc%'        => $internalPlan->getDescription(),
			'%amountincents%'           => $internalPlan->getAmountInCents(),
			'%amount%'                  => $this->getMoneyFormat($internalPlan->getAmountInCents(), $internalPlan->getCurrency()),
			'%amountincentsexcltax%'    => $internalPlan->getAmountInCentsExclTax(),
			'%amountexcltax%'           => $this->getMoneyFormat($internalPlan->getAmountInCentsExclTax(), $internalPlan->getCurrency()),
			'%vat%'                     => (is_null($internalPlan->getVatRate())) ? 'N/A' : number_format($internalPlan->getVatRate(), 2, ',', '').'%',
			'%amountincentstax%'        => $amountInCentsTax,
			'%amounttax%'               => $this->getMoneyFormat($amountInCentsTax, $internalPlan->getCurrency()),
			'%currency%'                => $internalPlan->getCurrencyForDisplay(),
			'%cycle%'                   => $internalPlan->getCycle(),
			'%periodunit%'              => $internalPlan->getPeriodUnit(),
			'%periodlength%'            => $internalPlan->getPeriodLength(),
			'%email%'                   => $userMail,
			'%firstname%'               => $firstName,
			'%lastname%'                => $lastName,
			'%username%'                => strstr($userMail, '@', true),
			'%fullname%'                => trim($firstName.' '.$lastName),
			'%couponcode%'              => $billingUserInternalCoupon->getCode(),
			'%recipientemail%'          => $billingUserInternalCouponOpts->getOpt('recipientEmail'),
			'%recipientfirstname%'      => $billingUserInternalCouponOpts->getOpt('recipientFirstName'),
			'%recipientlastname%'       => $billingUserInternalCouponOpts->getOpt('recipientLastName')
		];

		$bcc  = getenv('SENDGRID_BCC');
		$nbRecipient = (empty($bcc)) ? 1 : 2;

		array_walk($substitutions, function (&$value, $key) use ($nbRecipient) {
			$value = array_fill(0, $nbRecipient, $value);
		});

		$this->sendMailToOwner($userMail, $substitutions, $internalCouponsCampaign);
		$this->sendMailToRecipient($billingCouponsOpts->getOpt('recipientEmail'), $substitutions, $internalCouponsCampaign);
	}
	
	protected function sendMailToOwner($userMail, array $substitutions, BillingInternalCouponsCampaign $internalCouponsCampaign)
	{
		if($internalCouponsCampaign->getEmailsEnabled() == false) {
			return;
		}
		if (empty($userMail)) {
			$userMail = getenv('SENDGRID_TO_IFNULL');
		}

		$bcc = getenv('SENDGRID_BCC');
		$template = NULL;
		switch($internalCouponsCampaign->getCouponType()) {
			case CouponCampaignType::sponsorship :
				$template = getEnv('SENDGRID_TEMPLATE_COUPON_OWN_SPONSORSHIP_NEW');
			 	break;
			default :
				$template = getEnv('SENDGRID_TEMPLATE_COUPON_OWN_STANDARD_NEW');
				break;
		}

		$sendgrid = new SendGrid(getenv('SENDGRID_API_KEY'));
		$mail = new SendGrid\Mail();
		$email = new SendGrid\Email(getEnv('SENDGRID_FROM_NAME'), getEnv('SENDGRID_FROM'));
		$mail->setFrom($email);
		$personalization = new SendGrid\Personalization();
		$personalization->addTo(new SendGrid\Email(NULL, $userMail));
		if (!empty($bcc)) {
			$personalization->addBcc(new SendGrid\Email(NULL, $bcc));
		}
		foreach($substitutions as $var => $val) {
			//NC $val."" NOT $val which forces to cast to string because of an issue with numerics : https://github.com/sendgrid/sendgrid-php/issues/350
			$personalization->addSubstitution($var, $val."");
		}
		$mail->addPersonalization($personalization);
		$mail->setTemplateId($template);
		$response = $sendgrid->client->mail()->send()->post($mail);
		if($response->statusCode() != 202) {
			config::getLogger()->addError('sending mail using sendgrid failed, statusCode='.$response->statusCode());
			config::getLogger()->addError('sending mail using sendgrid failed, body='.$response->body());
			config::getLogger()->addError('sending mail using sendgrid failed, headers='.var_export($response->headers(), true));
		}
	}
	
	protected function sendMailToRecipient($userMail, array $substitutions, BillingInternalCouponsCampaign $internalCouponsCampaign)
	{
		if($internalCouponsCampaign->getEmailsEnabled() == false) {
			return;
		}
		if (empty($userMail)) {
			$userMail = getenv('SENDGRID_TO_IFNULL');
		}

		$bcc = getenv('SENDGRID_BCC');
		$template = NULL;
		switch($internalCouponsCampaign->getCouponType()) {
			case CouponCampaignType::sponsorship :
				$template = getEnv('SENDGRID_TEMPLATE_COUPON_OFFERED_SPONSORSHIP_NEW');
				break;
			default :
				$template = getEnv('SENDGRID_TEMPLATE_COUPON_OFFERED_STANDARD_NEW');
				break;
		}

		$sendgrid = new SendGrid(getenv('SENDGRID_API_KEY'));
		$mail = new SendGrid\Mail();
		$email = new SendGrid\Email(getEnv('SENDGRID_FROM_NAME'), getEnv('SENDGRID_FROM'));
		$mail->setFrom($email);
		$personalization = new SendGrid\Personalization();
		$personalization->addTo(new SendGrid\Email(NULL, $userMail));
		if (!empty($bcc)) {
			$personalization->addBcc(new SendGrid\Email(NULL, $bcc));
		}
		foreach($substitutions as $var => $val) {
			//NC $val."" NOT $val which forces to cast to string because of an issue with numerics : https://github.com/sendgrid/sendgrid-php/issues/350
			$personalization->addSubstitution($var, $val."");
		}
		$mail->addPersonalization($personalization);
		$mail->setTemplateId($template);
		$response = $sendgrid->client->mail()->send()->post($mail);
		if($response->statusCode() != 202) {
			config::getLogger()->addError('sending mail using sendgrid failed, statusCode='.$response->statusCode());
			config::getLogger()->addError('sending mail using sendgrid failed, body='.$response->body());
			config::getLogger()->addError('sending mail using sendgrid failed, headers='.var_export($response->headers(), true));
		}
	}
	
	/**
	 * Get formatted money
	 *
	 * @param int    $value    Amount in cents
	 * @param string $currency Currency
	 *
	 * @return string
	 */
	protected function getMoneyFormat($value, $currency)
	{
		$money = new Money((integer) $value, new Currency($currency));

		return money_format('%!.2n', (float) ($money->getAmount() / 100));
	}
	
}

?>