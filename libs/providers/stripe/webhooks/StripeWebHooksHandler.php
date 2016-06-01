<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__.'/observers/HookInterface.php';

class StripeWebHooksHandler
{
    protected $observers;

    public function __construct()
    {
        $this->observers = new \SplObjectStorage();
        \Stripe\Stripe::setApiKey(getenv('STRIPE_API_KEY'));

    }

    public function addHookObserver(HookInterface $hookObserver)
    {
        $this->observers->attach($hookObserver);
    }

    public function doProcessWebHook(BillingsWebHook $billingsWebHook, $update_type = 'hook')
    {
        $postedEvent = json_decode($billingsWebHook->getPostData(), true);

        // request event to be sure it's a real one
        $event = \Stripe\Event::retrieve($postedEvent['id']);

        // bad event, return quietly
        if (empty($event['id'])) {
            return;
        }

        $provider = ProviderDAO::getProviderByName('stripe');

        // send event to observers
        foreach ($this->observers as $hookObserver) {
            $hookObserver->event($event, $provider);
        }
    }
}