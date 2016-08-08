<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class StripeUsersHandler
{
    public function __construct()
    {
        \Stripe\Stripe::setApiKey(getenv('STRIPE_API_KEY'));
    }

    public function doCreateUser($userReferenceUuid, $user_billing_uuid, $userProviderUuid, array $userOpts)
    {
        if ($userProviderUuid) {
            $user = $this->getUser($userProviderUuid);
        } else {
            $user = $this->createUser($userOpts, $user_billing_uuid);
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
    protected function createUser(array $userOpts, $user_billing_uuid)
    {
        checkUserOptsArray($userOpts, 'stripe');

        $customer = \Stripe\Customer::create([
            'email' => $userOpts['email'],
            'metadata' => [
                'firstName' => $userOpts['firstName'],
                'lastName' => $userOpts['lastName'],
                'AfrSource' => 'afrBillingApi',
                'AfrUserBillingUuid' => $user_billing_uuid
            ]
        ]);

        $this->log('Create customer : email : %s, firstname: %s, lastname: %s', [$userOpts['email'], $userOpts['firstName'], $userOpts['lastName']]);

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