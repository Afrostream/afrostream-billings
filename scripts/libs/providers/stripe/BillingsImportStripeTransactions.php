<?php

require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';
require_once __DIR__ . '/../../../../libs/transactions/TransactionsHandler.php';

class BillingsImportStripeTransactions
{
    private $providerId = NULL;

    const STRIPE_LIMIT = 50;

    public function __construct()
    {
        $this->providerId = ProviderDAO::getProviderByName('stripe')->getId();
        \Stripe\Stripe::setApiKey(getenv('STRIPE_API_KEY'));
    }

    public function doImportTransactions()
    {
    	try {
    		ScriptsConfig::getLogger()->addInfo("importing transactions from stripe...");
    		$hasMoreCharges = true;
    		$options = ['limit' => self::STRIPE_LIMIT];
    		while ($hasMoreCharges) {
    			if (isset($offsetCharges)) {
    		 		$options['starting_after'] = $offsetCharges;
    		 	}
    		 	$listCharges = \Stripe\Charge::all($options);
    		 	$hasMoreCharges = $listCharges['has_more'];
    		 	$list = $listCharges['data'];
    		 	$offsetCharges = end($list);
    		 	reset($list);
    		
    			foreach($list as $charge) {
    		 		try {
    		 			if(is_null($charge->customer)) {
    		 				$this->doImportTransaction($charge);
    		 			}
    		 		} catch (Exception $e) {
    		 			ScriptsConfig::getLogger()->addError("unexpected exception while importing stand-alone transaction with id=".$charge->id." from stripe, message=".$e->getMessage());
    		 		}
    		 	}
    		}
	        $hasMoreCustomers = true;
	        $options = ['limit' => self::STRIPE_LIMIT];
	        while ($hasMoreCustomers) {
	            if (isset($offsetCustomers)) {
	                $options['starting_after'] = $offsetCustomers;
	            }
	            $listCustomers = \Stripe\Customer::all($options);
	            $hasMoreCustomers = $listCustomers['has_more'];
	            $list = $listCustomers['data'];
	            $offsetCustomers = end($list);
	            reset($list);
	
	            foreach($list as $customer) {
	            	try {
						$this->doImportUserTransactions($customer);
					} catch (Exception $e) {
						ScriptsConfig::getLogger()->addError("unexpected exception while importing transactions from stripe with account_code=".$customer->id.", message=".$e->getMessage());
					}
	            }
	        }
    	} catch(Exception $e) {
    		ScriptsConfig::getLogger()->addError("unexpected exception while importing transactions from stripe, message=".$e->getMessage());
	    }
	    ScriptsConfig::getLogger()->addInfo("importing transactions from stripe done");
    }

    protected function doImportTransaction(Stripe\Charge $charge)
    {
    	ScriptsConfig::getLogger()->addInfo("importing stand-alone transaction from stripe...");
    	$transactionHandler = new TransactionsHandler();
    	$transactionHandler->doUpdateTransactionByTransactionProviderUuid('stripe', $charge->id);
    	ScriptsConfig::getLogger()->addInfo("importing stand-alone transaction from stripe done successfully");
    }
    
    protected function doImportUserTransactions(Stripe\Customer $customer)
    {
        ScriptsConfig::getLogger()->addInfo("importing transactions from stripe account with account_code=".$customer->id."...");
        $user = UserDAO::getUserByUserProviderUuid($this->providerId, $customer->id);

        if($user == NULL) {
            throw new Exception("user with account_code=".$customer->id." does not exist in billings database");
        }
	
        $transactionHandler = new TransactionsHandler();
        $transactionHandler->doUpdateTransactionsByUser($user);
        ScriptsConfig::getLogger()->addInfo("importing transactions from stripe account with account_code=".$customer->id." done successfully");
    }
 
}