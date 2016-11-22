<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';

class StripePlanHandler
{

    public function __construct()
    {
        \Stripe\Stripe::setApiKey(getenv('STRIPE_API_KEY'));
    }

    /**
     * Create a plan to stripe provider
     *
     * @param InternalPlan $internalPlan
     *
     * @return string
     */
    public function createProviderPlan(InternalPlan $internalPlan)
    {
        // stripe does not support plan who is'nt recurrent
        if ($internalPlan->getCycle() != PlanCycle::auto) {
            return $internalPlan->getInternalPlanUuid();
        }

        $data = [
            'id' => $internalPlan->getInternalPlanUuid(),
            'amount' => $internalPlan->getAmountInCents(),
            'currency' => $internalPlan->getCurrency(),
            'interval' => $internalPlan->getPeriodUnit(),
            'name' => $internalPlan->getName()
        ];

        if ($internalPlan->getTrialEnabled()) {
            $data['trial_period_days'] = $this->getTrialDays($internalPlan);
        }

        $stripePlan = \Stripe\Plan::create($data);

        return $stripePlan['id'];
    }

    /**
     * Get trial period in days
     *
     * @param InternalPlan $internalPlan
     *
     * @return int
     */
    protected function getTrialDays(InternalPlan $internalPlan)
    {
        $trialPeriodUnit = $internalPlan->getTrialPeriodUnit()->getValue();

        if ($trialPeriodUnit == TrialPeriodUnit::day) {
            $days = 1;
        } else {
            $days = 30; //month unit in days
        }

        return ($days * $internalPlan->getTrialPeriodLength());
    }

}

?>