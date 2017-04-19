<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/users/ProviderUsersHandler.php';

class StripeUsersHandler extends ProviderUsersHandler
{
    
	public function __construct($provider) {
    	parent::__construct($provider);
    	\Stripe\Stripe::setApiKey($this->provider->getApiSecret());
    }

    public function doCreateUser(CreateUserRequest $createUserRequest)
    {
        if ($createUserRequest->getUserProviderUuid() != NULL) {
            $user = $this->getUser($createUserRequest->getUserProviderUuid());
        } else {
            $user = $this->createUser($createUserRequest);
        }

        return $user['id'];
    }

    /**
     * @param $userProviderUuid
     *
     * @throws BillingsException
     *
     * @return \Stripe\Customer
     */
    protected function getUser($userProviderUuid)
    {
        $this->log('Retrieve user '.$userProviderUuid);

        $customer = \Stripe\Customer::retrieve($userProviderUuid);

        if (empty($customer['id'])) {
            $this->log('No user available with the given id');
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'No user available with the given id');
        }

        return $customer;
    }

    /**
     * Create user to stripe provider
     *
     * @param array $userOpts
     *
     * @throws BillingsException
     *
     * @return \Stripe\Customer
     */
    protected function createUser(CreateUserRequest $createUserRequest)
    {
        checkUserOptsArray($createUserRequest->getUserOptsArray(), $this->provider->getName());

        $customer = \Stripe\Customer::create([
            'email' => $createUserRequest->getUserOptsArray()['email'],
            'metadata' => [
                'firstName' => $createUserRequest->getUserOptsArray()['firstName'],
                'lastName' => $createUserRequest->getUserOptsArray()['lastName'],
                'AfrSource' => 'afrBillingApi',
            	'AfrOrigin' => 'user',
                'AfrUserBillingUuid' => $createUserRequest->getUserBillingUuid()
            ]
        ]);

        $this->log('Create customer : email : %s, firstname: %s, lastname: %s', [$createUserRequest->getUserOptsArray()['email'], $createUserRequest->getUserOptsArray()['firstName'], $createUserRequest->getUserOptsArray()['lastName']]);

        if (empty($customer['id'])) {
            $this->log('Error on recording user on stripe side');
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Error on recording user on stripe side');
        }

        return $customer;
    }

    protected function log($message, array $values =  [])
    {
        $message = vsprintf($message, $values);
        config::getLogger()->addInfo('STRIPE - '.$message);
    }
    
    public function doUpdateUserOpts(UpdateUserRequest $updateUserRequest) {
    	try {
    		config::getLogger()->addInfo("stripe user data updating...");
    		//
    		checkUserOptsArray($updateUserRequest->getUserOptsArray(), $this->provider->getName());
    		//
    		$customer = \Stripe\Customer::retrieve($updateUserRequest->getUserProviderUuid());
    		
    		$customer->email = $updateUserRequest->getUserOptsArray()['email'];
    		
    		$customer->metadata['firstName'] = $updateUserRequest->getUserOptsArray()['firstName'];
    		$customer->metadata['lastName'] = $updateUserRequest->getUserOptsArray()['lastName'];
    		//
    		$customer->save();
    		config::getLogger()->addInfo("stripe user data updating done successfully, user_provider_uuid=".$updateUserRequest->getUserProviderUuid());
    	} catch(BillingsException $e) {
    		$msg = "a billings exception occurred while updating stripe user data for user_provider_uuid=".$updateUserRequest->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
    		config::getLogger()->addError("stripe user data updating failed : ".$msg);
    		throw $e;
		} catch(Exception $e) {
    		$msg = "an unknown exception occurred while updating a stripe user data for user_provider_uuid=".$updateUserRequest->getUserProviderUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
    		config::getLogger()->addError("stripe user data updating failed : ".$msg);
    		throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
    	}
    }
    
}

?>