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
    
    /**
     * Return date with the given timestamp
     *
     * @param int|null $timestamp
     *
     * @return null|string
     */
    protected function createDate($timestamp) {
    	if (empty($timestamp)) {
    		return null;
    	}
    
    	return new \DateTime(date('c', $timestamp));
    }
    
}

?>