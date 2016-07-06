<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';

use Money\Money;
use Money\Currency;

class AfrCouponsHandler {
	
	public function __construct() {
		\Stripe\Stripe::setApiKey(getenv('STRIPE_API_KEY'));
	}
		
	public function doGetCoupon(User $user = NULL, UserOpts $userOpts = NULL, $couponCode) {
		$db_coupon = NULL;
		try {
			if(isset($user)) {
				$msg = "unsupported feature for provider named : afr, user has NOT to be provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);				
			}
			//provider
			$provider = ProviderDAO::getProviderByName('afr');
			if($provider == NULL) {
				$msg = "provider named 'afr' not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$db_coupon = CouponDAO::getCoupon($provider->getId(), $couponCode);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while getting a afr coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr coupon getting failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a afr coupon, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("afr coupon getting failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
		return($db_coupon);
	}

	public function doCreateCoupon(User $user, UserOpts $userOpts, CouponsCampaign $couponsCampaign, $couponBillingUuid, BillingsCouponsOpts $billingCouponsOpts)
	{
		$hasCharge = ($couponsCampaign->getCouponType()->getValue() == CouponCampaignType::standard);

		if ($hasCharge && is_null($billingCouponsOpts->getOpt('customerBankAccountToken'))) {
			throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Error while creating afr coupon. Missing stripe token');
		}

		if (is_null($billingCouponsOpts->getOpt('recipientEmail'))) {
			throw new BillingsException(new ExceptionType(ExceptionType::internal), 'You must provide a recipient mail');
		}

		$internalPlanId = InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($couponsCampaign->getProviderPlanId());
		$internalPlan   = InternalPlanDAO::getInternalPlanById($internalPlanId);

		if (empty($internalPlan)) {
			throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Unknow internal plan');
		}

		$couponCode = $couponsCampaign->getPrefix()."-".$this->getRandomString($couponsCampaign->getGeneratedCodeLength());

		if ($hasCharge) {
		$chargeData = [
			'amount' => $internalPlan->getAmountInCents(),
			'currency' => $internalPlan->getCurrency(),
			'description' => "Coupon afrostream : $couponCode",
			'source' => $billingCouponsOpts->getOpt('customerBankAccountToken'),
			"metadata" => [
				'AfrSource' => 'afrBillingApi',
				'AfrOrigin' => 'coupon',
				'AfrCouponBillingUuid' => $couponBillingUuid
			]
		];

			// charge customer
			$charge = \Stripe\Charge::create($chargeData);

			if (!$charge->paid) {
				config::getLogger()->addError('Payment refused');
				throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Payment refused');
			}

			$billingCouponsOpts->setOpt('chargeId', $charge->id);
		}


		$coupon = new Coupon();
		$coupon->setCouponBillingUuid($couponBillingUuid);
		$coupon->setCouponsCampaignId($couponsCampaign->getId());
		$coupon->setProviderId($couponsCampaign->getProviderId());
		$coupon->setProviderPlanId($couponsCampaign->getProviderPlanId());
		$coupon->setCode($couponCode);
		$coupon->setUserId($user->getId());

		$coupon = CouponDAO::addCoupon($coupon);

		$billingCouponsOpts->setCouponId($coupon->getId());

		$billingCouponsOpts = BillingsCouponsOptsDAO::addBillingsCouponsOpts($billingCouponsOpts);

		$this ->sendMails($user, $userOpts, $coupon, $billingCouponsOpts, $internalPlan, $hasCharge);
		return $coupon->getCode();
	}

	public function createDbCouponFromApiCouponUuid(User $user,  UserOpts $userOpts, CouponsCampaign $couponsCampaign, $coupon_billing_uuid, $coupon_provider_uuid) {
		return CouponDAO::getCouponByCouponBillingUuid($coupon_billing_uuid);
	}

	protected function getRandomString($length) {
		$strAlphaNumericString = '23456789bcdfghjkmnpqrstvwxz';
		$strlength             = strlen($strAlphaNumericString) -1 ;
		$strReturnString       = '';

		for ($intCounter = 0; $intCounter < $length; $intCounter++) {
			$strReturnString .= $strAlphaNumericString[rand(0, $strlength)];
		}

		return $strReturnString;
	}

	protected function sendMails(User $user, UserOpts $userOpts, Coupon $coupon, BillingsCouponsOpts $billingCouponsOpts, InternalPlan $internalPlan, $hasCharge)
	{
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
			'%couponcode%'              => $coupon->getCode(),
			'%recipientemail%'          => $billingCouponsOpts->getOpt('recipientEmail'),
			'%recipientfirstname%'      => $billingCouponsOpts->getOpt('recipientFirstName'),
			'%recipientlastname%'       => $billingCouponsOpts->getOpt('recipientLastName')
		];

		$bcc  = getenv('SENDGRID_BCC');
		$nbRecipient = (empty($bcc)) ? 1 : 2;

		array_walk($substitutions, function (&$value, $key) use ($nbRecipient) {
			$value = array_fill(0, $nbRecipient, $value);
		});

		$this->sendMailToOwner($userMail, $substitutions, $hasCharge);
		$this->sendMailToRecipient($billingCouponsOpts->getOpt('recipientEmail'), $substitutions, $hasCharge);
	}

	protected function sendMailToOwner($userMail, array $substitutions, $hasCharge)
	{
		if (empty($userMail)) {
			$userMail = getenv('SENDGRID_TO_IFNULL');
		}

		$bcc  = getenv('SENDGRID_BCC');
		$template = ($hasCharge) ? getEnv('SENDGRID_TEMPLATE_COUPON_OWN_STANDARD_NEW') : getEnv('SENDGRID_TEMPLATE_COUPON_OWN_SPONSORSHIP_NEW');

		$sendgrid = new SendGrid(getenv('SENDGRID_API_KEY'));
		$email = new SendGrid\Email();

		$email->addTo($userMail);
		$email->setFrom(getenv('SENDGRID_FROM'))
			->setFromName(getenv('SENDGRID_FROM_NAME'))
			->setSubject(' ')
			->setText(' ')
			->setHtml(' ')
			->setTemplateId($template)
			->setSubstitutions($substitutions);
		if (!empty($bcc)) {
			$email->setBcc($bcc);
		}

		$sendgrid->send($email);
	}

	protected function sendMailToRecipient($userMail, array $substitutions, $hasCharge)
	{
		if (empty($userMail)) {
			$userMail = getenv('SENDGRID_TO_IFNULL');
		}

		$bcc  = getenv('SENDGRID_BCC');
		$template = ($hasCharge) ? getEnv('SENDGRID_TEMPLATE_COUPON_OFFERED_STANDARD_NEW') : getEnv('SENDGRID_TEMPLATE_COUPON_OFFERED_SPONSORSHIP_NEW');

		$sendgrid = new SendGrid(getenv('SENDGRID_API_KEY'));
		$email = new SendGrid\Email();

		$email->addTo($userMail);
		$email->setFrom(getenv('SENDGRID_FROM'))
			->setFromName(getenv('SENDGRID_FROM_NAME'))
			->setSubject(' ')
			->setText(' ')
			->setHtml(' ')
			->setTemplateId($template)
			->setSubstitutions($substitutions);
		if (!empty($bcc)) {
			$email->setBcc($bcc);
		}

		$sendgrid->send($email);
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