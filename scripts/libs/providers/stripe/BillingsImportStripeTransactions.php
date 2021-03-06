<?php

require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';
require_once __DIR__ . '/../../../../libs/transactions/TransactionsHandler.php';
require_once __DIR__ . '/../../../../libs/providers/global/requests/UpdateTransactionRequest.php';

class BillingsImportStripeTransactions
{
    private $provider = NULL;
    private $platform = NULL;

    const STRIPE_LIMIT = 50;

    public function __construct(Provider $provider)
    {
        $this->provider = $provider;
        $this->platform = BillingPlatformDAO::getPlatformById($this->provider->getPlatformId());
        \Stripe\Stripe::setApiKey($this->provider->getApiSecret());
    }

    public function doImportTransactions(DateTime $from = NULL, DateTime $to = NULL)
    {
    	try {
    		ScriptsConfig::getLogger()->addInfo("importing transactions from stripe...");
    		$paramsCharges = array();
    		$paramsCharges['limit'] = self::STRIPE_LIMIT;
    		if(isset($from)) {
    			$paramsCharges['created']['gte'] = $from->getTimestamp();
    		}
    		if(isset($to)) {
    			$paramsCharges['created']['lte'] = $to->getTimestamp();
    		}
    		$hasMoreCharges = true;
    		while ($hasMoreCharges) {
    			if (isset($offsetCharges)) {
    		 		$paramsCharges['starting_after'] = $offsetCharges->id;
    		 	}
    		 	$listCharges = \Stripe\Charge::all($paramsCharges);
    		 	$hasMoreCharges = $listCharges['has_more'];
    		 	$list = $listCharges['data'];
    		 	$offsetCharges = end($list);
    		 	reset($list);
    		
    			foreach($list as $charge) {
    		 		try {
    		 			if(is_null($charge->customer)) {
    		 				$this->doImportTransaction($charge);
    		 			} else {
    		 				ScriptsConfig::getLogger()->addInfo("ignoring stand-alone transaction with id =".$charge->id.", it is linked to a customer");
    		 			}
    		 		} catch (Exception $e) {
    		 			ScriptsConfig::getLogger()->addError("unexpected exception while importing stand-alone transaction with id=".$charge->id." from stripe, message=".$e->getMessage());
    		 		}
    		 	}
    		}
	        $hasMoreCustomers = true;
	        $paramsCustomers = array();
	        $paramsCustomers['limit'] = self::STRIPE_LIMIT;
	        while ($hasMoreCustomers) {
	            if (isset($offsetCustomers)) {
	                $paramsCustomers['starting_after'] = $offsetCustomers->id;
	            }
	            $listCustomers = \Stripe\Customer::all($paramsCustomers);
	            $hasMoreCustomers = $listCustomers['has_more'];
	            $list = $listCustomers['data'];
	            $offsetCustomers = end($list);
	            reset($list);
	
	            foreach($list as $customer) {
	            	try {
						$this->doImportUserTransactions($customer, $from, $to);
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
    	$updateTransactionRequest = new UpdateTransactionRequest();
    	$updateTransactionRequest->setPlatform($this->platform);
    	$updateTransactionRequest->setProviderName($this->provider->getName());
    	$updateTransactionRequest->setTransactionProviderUuid($charge->id);
    	$updateTransactionRequest->setOrigin('import');
    	$transactionHandler->doUpdateTransactionByTransactionProviderUuid($updateTransactionRequest);
    	ScriptsConfig::getLogger()->addInfo("importing stand-alone transaction from stripe done successfully");
    }
    
    protected function doImportUserTransactions(Stripe\Customer $customer, DateTime $from = NULL, DateTime $to = NULL)
    {
        ScriptsConfig::getLogger()->addInfo("importing transactions from stripe account with account_code=".$customer->id."...");
        $metadata = $customer->metadata->__toArray();
        $hasToBeProcessed = false;
        $isRecurlyCustomer = false;
        if(array_key_exists('recurlyAccountCode', $metadata)) {
        	$isRecurlyCustomer = true;
        }
        $hasToBeProcessed = !$isRecurlyCustomer;
        if($hasToBeProcessed) {
	        $user = UserDAO::getUserByUserProviderUuid($this->provider->getId(), $customer->id);
	        if($user == NULL) {
	            throw new Exception("user with account_code=".$customer->id." does not exist in billings database");
	        }
	        $transactionHandler = new TransactionsHandler();
	        $transactionHandler->doUpdateTransactionsByUser($user, $from, $to, 'import');
        } else {
        	ScriptsConfig::getLogger()->addInfo("stripe account with account_code=".$customer->id." is ignored");
        }
        ScriptsConfig::getLogger()->addInfo("importing transactions from stripe account with account_code=".$customer->id." done successfully");
    }
 
}