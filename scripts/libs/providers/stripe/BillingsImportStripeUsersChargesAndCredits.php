<?php
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/db/dbGlobal.php';
require_once __DIR__ . '/../../../../libs/utils/utils.php';

class BillingsImportStripeUsersChargesAndCredits
{
    private $providerId = NULL;

    const STRIPE_LIMIT = 50;

    public function __construct()
    {
        $this->providerId = ProviderDAO::getProviderByName('stripe')->getId();
        \Stripe\Stripe::setApiKey(getenv('STRIPE_API_KEY'));
    }

    public function doImportUsersChargesAndCredits()
    {
        $hasMore = true;
        $options = ['limit' => self::STRIPE_LIMIT];
        while ($hasMore) {
            if (isset($offset)) {
                $options['starting_after'] = $offset;
            }
            $listCustomers = \Stripe\Customer::all($options);
            $hasMore = $listCustomers['has_more'];
            $list = $listCustomers['data'];
            $offset = end($list);
            reset($list);

            foreach($list as $customer) {
                $this->doImportUserChargesAndCredits($customer);
            }

        }

    }

    protected function doImportUserChargesAndCredits(Stripe\Customer $customer)
    {
        ScriptsConfig::getLogger()->addInfo("importing charges and credits from recurly account with account_code=".$customer->id."...");
        $user = UserDAO::getUserByUserProviderUuid($this->providerId, $customer->id);

        if($user == NULL) {
            throw new Exception("user with account_code=".$customer->id." does not exist in billings database");
        }

        $listCharge = \Stripe\Charge::all(['customer' => $customer->id]);
        $list = $listCharge['data'];
        foreach ($list as $charge) {
            $msg =
                "charge : id=".$charge->id.
                ", amount_in_cents=".$charge->amount.
                ", currency=".$charge->currency.
                ", application_fee=".$charge->application_fee.
                ", amount_refunded=".$charge->amount_refunded.
                ", balance_transaction=".$charge->balance_transaction.
                ", invoice=".$charge->invoice.
                ", source=".$charge->source->id;

            ScriptsConfig::getLogger()->addInfo($msg);
        }
    }
}