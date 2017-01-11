<?php

require_once __DIR__ . '/../../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/utils.php';
require_once __DIR__ . '/../../../../utils/BillingsException.php';

use Stripe\Event;

interface HookInterface
{
    /**
     * Process an event for the given provider
     *
     * @param Event    $event
     * @param Provider $provider
     *
     * @return mixed
     */
    public function event(Event $event, Provider $provider);
    
}

?>