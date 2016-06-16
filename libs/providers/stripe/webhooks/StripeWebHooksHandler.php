<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__.'/observers/HookInterface.php';
require_once __DIR__.'/observers/CancelSubscription.php';
require_once __DIR__.'/observers/EmailCanceledSubscription.php';
require_once __DIR__.'/observers/EmailCreatedSubscription.php';
require_once __DIR__.'/observers/UpdateSubscription.php';

/**
 * Handler for stripe web hook
 *
 * Web hook are event sent by stripe who reflect the modification on stripe side
 * like subscriptino updated, customer canceled a subscription,...
 */
class StripeWebHooksHandler
{
    /**
     * @var SplObjectStorage
     */
    protected $observers;

    /**
     * StripeWebHooksHandler constructor.
     */
    public function __construct()
    {
        $this->observers = new \SplObjectStorage();
        \Stripe\Stripe::setApiKey(getenv('STRIPE_API_KEY'));
    }

    /**
     * Add a hook observer
     *
     * @param HookInterface $hookObserver
     *
     * @return $this
     */
    public function addHookObserver(HookInterface $hookObserver)
    {
        if (!$this->observers->contains($hookObserver)) {
            $this->observers->attach($hookObserver);
        }

        return $this;
    }

    /**
     * Process the hook sent by stripe
     *
     * @param BillingsWebHook $billingsWebHook
     * @param string          $updateType
     */
    public function doProcessWebHook(BillingsWebHook $billingsWebHook, $updateType = 'hook')
    {
        $postedEvent = json_decode($billingsWebHook->getPostData(), true);

        $this->log('Process new event '.$postedEvent['id']);

        // request event to be sure it's a real one
        $event = \Stripe\Event::retrieve($postedEvent['id']);

        // bad event, return quietly
        if (empty($event['id']) || empty($event['data']['object'])) {
            $this->log('Bad event , no id or no object found in event');
            return;
        }

        $provider = ProviderDAO::getProviderByName('stripe');

        // send event to observers
        foreach ($this->observers as $hookObserver) {
            $hookObserver->event($event, $provider);
        }
    }

    /**
     * Load all hook
     */
    public function loadHooks()
    {
        $this->addHookObserver(new CancelSubscription())
            ->addHookObserver(new EmailCanceledSubscription())
            ->addHookObserver(new EmailCreatedSubscription())
            ->addHookObserver(new UpdateSubscription());
    }

    protected function log($message, array $values =  [])
    {
        $message = vsprintf($message, $values);
        config::getLogger()->addInfo('STRIPE - '.$message);
    }
}