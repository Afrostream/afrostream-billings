<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

use Money\Money;
use Money\Currency;

/**
 * Trait to mailing availability
 */
trait EmailTrait
{

    /**
     * Send mail through sendgrid.
     *
     * @param string $templateId    Id of tempalte to use
     * @param string $userMail      Recipient of the mail
     * @param array  $substitutions Substitution data for template. Provided by {@see getSendGridSubstitution()}
     *
     *
     * @throws \SendGrid\Exception
     */
    protected function sendMail($templateId, $userMail, array $substitutions)
    {

        if (empty($userMail)) {
            $userMail = getenv('SENDGRID_TO_IFNULL');
        }

        $bcc  = getenv('SENDGRID_BCC');

        $nbRecipient = (empty($bcc)) ? 1 : 2;

        array_walk($substitutions, function (&$value, $key) use ($nbRecipient) {
            $value = array_fill(0, $nbRecipient, $value);
        });

        $sendgrid = new SendGrid(getenv('SENDGRID_API_KEY'));
        $email = new SendGrid\Email();

        $email->addTo($userMail);
        $email->setFrom(getenv('SENDGRID_FROM'))
            ->setFromName(getenv('SENDGRID_FROM_NAME'))
            ->setSubject(' ')
            ->setText(' ')
            ->setHtml(' ')
            ->setTemplateId($templateId)
            ->setSubstitutions($substitutions);
        if (!empty($bcc)) {
            $email->setBcc($bcc);
        }

        $sendgrid->send($email);
    }

    /**
     * Build sendgrid substitution data
     *
     * @param User                 $user
     * @param UserOpts             $userOpts
     * @param BillingsSubscription $billingSubscription
     *
     * @return array
     */
    protected function getSendGridSubstitution(User $user, UserOpts $userOpts, BillingsSubscription $billingSubscription)
    {
        setlocale(LC_MONETARY, 'fr_FR.utf8');


        $providerPlan = PlanDAO::getPlanById($billingSubscription->getPlanId());
        $internalPlan = InternalPlanDAO::getInternalPlanById(InternalPlanLinksDAO::getInternalPlanIdFromProviderPlanId($providerPlan->getId()));

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
            '%subscriptionbillinguuid%' => $billingSubscription->getSubscriptionBillingUuid()
        ];

        return $substitutions;
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