<?php
require_once __DIR__ . '/../../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/utils.php';
require_once __DIR__ . '/../../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../../db/dbGlobal.php';
require_once __DIR__.'/HookInterface.php';
require_once __DIR__.'/../EmailTrait.php';

use \Stripe\Event;


class EmailCanceledSubscription implements HookInterface
{
    const REQUESTED_HOOK_TYPE = 'customer.subscription.deleted';

    protected $sendGridTemplateId;

    use EmailTrait;
    
    public function __construct()
    {
        $this->sendGridTemplateId = getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_CANCEL_ID');
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

        
        
        $this->sendMail($this->sendGridTemplateId, $userMail, $substitutions);

        config::getLogger()->addInfo('STRIPE - customer.subscription.deleted : email customer '.$userMail);
    }
}