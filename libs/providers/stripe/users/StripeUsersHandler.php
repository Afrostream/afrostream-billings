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

    public function doCreateUser($userReferenceUuid, $userProviderUuid, array $userOpts)
    {
        if ($userProviderUuid) {
            $user = $this->getUser($userProviderUuid);
        } else {
            $user = $this->createUser($userOpts);
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
        $customer = \Stripe\Customer::retrieve($userProviderUuid);

        if (empty($customer['id'])) {
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
    protected function createUser(array $userOpts)
    {
        checkUserOptsArray($userOpts, 'stripe');

        $customer = \Stripe\Customer::create([
            'email' => $userOpts['email'],
            'metadata' => [
                'firstName' => $userOpts['firstName'],
                'lastName' => $userOpts['lastName']
            ]
        ]);

        if (empty($customer['id'])) {
            throw new BillingsException(new ExceptionType(ExceptionType::internal), 'Error on recording user on stripe side');
        }

        return $customer;
    }
}