<?php
require_once __DIR__ . '/../../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/utils.php';
require_once __DIR__ . '/../../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../../db/dbGlobal.php';
require_once __DIR__.'/HookInterface.php';

use \Stripe\Event;
use Money\Money;
use Money\Currency;

class EmailCanceledSubscription implements HookInterface
{
    const REQUESTED_HOOK_TYPE = 'customer.subscription.deleted';

    protected $sendGridTemplateId;
    protected $sendGridBcc;
    public function __construct()
    {
        $this->sendGridTemplateId = getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_CANCEL_ID');
        $this->sendGridBcc        = getEnv('SENDGRID_BCC');
    }

    /**
     * @inheritdoc
     */
    public function event(Event $event, Provider $provider)
    {
        // check type
        if ($event['type'] != self::REQUESTED_HOOK_TYPE) {
            return;
        }

        // check the object
        if ($event['data']['object']['object'] !== 'subscription') {
            return null;
        }

        // check if mail is enbaled
        if (!getEnv('EVENT_EMAIL_ACTIVATED')) {
            return;
        }

        $subscription = $event['data']['object'];
        $billingSubscription = BillingsSubscriptionDAO::getBillingsSubscriptionBySubUuid($provider->getId(), $subscription['id']);

        $user = UserDAO::getUserById($billingSubscription->getUserId());
        $userOpts     = UserOptsDAO::getUserOptsByUserId($user->getId());

        $userMail = $userOpts->getOpt('email');
        $substitutions = $this->getSendGridSubstitution($user, $userOpts, $billingSubscription);
        $nbMail = 1;
        if($this->sendGridBcc) {
            $nbMail += 1;
        }

        array_walk($substitutions, function (&$value, $key) use ($nbMail) {
            $value = array_fill(0, $nbMail, $value);
        });
        
        $sendgrid = new SendGrid(getEnv('SENDGRID_API_KEY'));
        $email = new SendGrid\Email();
        $email->addTo(!empty($userMail) ? $userMail : getEnv('SENDGRID_TO_IFNULL'));
        $email->setFrom(getEnv('SENDGRID_FROM'))
            ->setFromName(getEnv('SENDGRID_FROM_NAME'))
            ->setSubject(' ')
            ->setText(' ')
            ->setHtml(' ')
            ->setTemplateId($this->sendGridTemplateId)
            ->setSubstitutions($substitutions);
        if (!empty($this->sendGridBcc)) {
            $email->setBcc($this->sendGridBcc);
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