<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../global/users/ProviderUsersHandler.php';
require_once __DIR__ . '/../../global/requests/CreateUserRequest.php';
require_once __DIR__ . '/../../global/requests/UpdateUserRequest.php';
require_once __DIR__ . '/../../global/requests/UpdateUsersRequest.php';

class StripeUsersHandler extends ProviderUsersHandler
{
    
	public function __construct($provider) {
    	parent::__construct($provider);
    	\Stripe\Stripe::setApiKey(getenv('STRIPE_API_KEY'));
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
        checkUserOptsArray($createUserRequest->getUserOpts(), $this->provider->getName());

        $customer = \Stripe\Customer::create([
            'email' => $createUserRequest->getUserOpts()['email'],
            'metadata' => [
                'firstName' => $createUserRequest->getUserOpts()['firstName'],
                'lastName' => $createUserRequest->getUserOpts()['lastName'],
                'AfrSource' => 'afrBillingApi',
            	'AfrOrigin' => 'user',
                'AfrUserBillingUuid' => $createUserRequest->getUserBillingUuid()
            ]
        ]);

        $this->log('Create customer : email : %s, firstname: %s, lastname: %s', [$createUserRequest->getUserOpts()['email'], $createUserRequest->getUserOpts()['firstName'], $createUserRequest->getUserOpts()['lastName']]);

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
}

?>