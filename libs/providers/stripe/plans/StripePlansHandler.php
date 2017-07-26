<?php

require_once __DIR__ . '/../../global/plans/ProviderPlansHandler.php';

class StripePlansHandler extends ProviderPlansHandler {

	public function __construct(Provider $provider) {
  		parent::__construct($provider);
        \Stripe\Stripe::setApiKey($this->provider->getApiSecret());
    }

    /**
     * Create a plan to stripe provider
     *
     * @param InternalPlan $internalPlan
     *
     * @return string
     */
    public function createProviderPlan(InternalPlan $internalPlan) {
    	$providerPlanUuid = NULL;
        // stripe does not support plan which is not recurrent
        switch($internalPlan->getCycle()) {
        	case PlanCycle::once :
        		if ($internalPlan->getTrialEnabled()) {
        			//exception
        			$msg = "trial not supported when cycle is 'once'";
        			config::getLogger()->addError($msg);
        			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
        		}
        		$providerPlanUuid = $internalPlan->getInternalPlanUuid();
        		break;
        	case PlanCycle::auto :
        		$data = [
        				'id' => $internalPlan->getInternalPlanUuid(),
        				'amount' => $internalPlan->getAmountInCents(),
        				'currency' => $internalPlan->getCurrency(),
        				'interval' => $internalPlan->getPeriodUnit(),
        				'interval_count' => $internalPlan->getPeriodLength(),
        				'name' => $internalPlan->getName()
        		];
        		if ($internalPlan->getTrialEnabled()) {
        			$data['trial_period_days'] = $this->getTrialDays($internalPlan);
        		}
        		$stripePlan = \Stripe\Plan::create($data);
        		$providerPlanUuid = $stripePlan['id'];
        		break;
        	default :
				//exception
				$msg = "unknown cycle : ".$internalPlan->getCycle();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
        }
        return $providerPlanUuid;
    }

    /**
     * Get trial period in days
     *
     * @param InternalPlan $internalPlan
     *
     * @return int
     */
    protected function getTrialDays(InternalPlan $internalPlan) {
        $days = NULL;
        switch($internalPlan->getTrialPeriodUnit()) {
        	case TrialPeriodUnit::day :
        		$days = 1;
        		break;
        	case TrialPeriodUnit::month :
        		$days = 30; //month unit in days
        		break;
        	default :
				//exception
				$msg = "unknown trialPeriodUnit : ".$internalPlan->getTrialPeriodUnit();
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				break;
        }
        return ($days * $internalPlan->getTrialPeriodLength());
    }

}

?>