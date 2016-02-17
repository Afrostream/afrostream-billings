<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../db/dbGlobal.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../client/IdipperClient.php';

class IdipperSubscriptionsHandler extends SubscriptionsHandler {
	
	public function __construct() {
	}

	public function doCreateUserSubscription(User $user, UserOpts $userOpts, Provider $provider, InternalPlan $internalPlan, InternalPlanOpts $internalPlanOpts, Plan $plan, PlanOpts $planOpts, $subscription_provider_uuid, BillingInfoOpts $billingInfoOpts, BillingsSubscriptionOpts $subOpts) {
		$sub_uuid = NULL;
		try {
			config::getLogger()->addInfo("idipper subscription creation...");
			//pre-requisite
			checkSubOptsArray($subOpts->getOpts(), 'idipper');
			if(!isset($subscription_provider_uuid)) {
				$msg = "field 'subscriptionProviderUuid' was not provided";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			//Verification : Just that abonne = 1 for the good Rubrique (we cannot do more)
			$idipperClient = new IdipperClient();
			$utilisateurRequest = new UtilisateurRequest();
			$utilisateurRequest->setExternalUserID($user->getUserProviderUuid());
			$utilisateurResponse = $idipperClient->getUtilisateur($utilisateurRequest);
			$rubriqueFound = false;
			$hasSubscribed = false;
			foreach ($utilisateurResponse->getRubriques() as $rubrique) {
				if($rubrique->getIDRubrique() == $plan->getPlanUuid()) {
					$rubriqueFound = true;
					if($rubrique->getAbonne() == '1') {
						$hasSubscribed = true;
					}
					break;
				}
			}
			if(!$rubriqueFound) {
				$msg = "rubrique with id=".$plan->getPlanUuid()." was not found";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!$hasSuscribed) {
				$msg = "rubrique with id=".$plan->getPlanUuid()." not subscribed";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$sub_uuid = $subscription_provider_uuid;
			config::getLogger()->addInfo("idipper subscription creation done successfully, idipper_subscription_uuid=".$sub_uuid);
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while creating a idipper subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("idipper subscription creation failed : ".$msg);
			throw $e;
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while creating a idipper subscription for user_reference_uuid=".$user->getUserReferenceUuid().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("idipper subscription creation failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
		return($sub_uuid);
	}
	
	protected function doFillSubscription(BillingsSubscription $subscription = NULL) {
		if($subscription == NULL) {
			return;
		}
		$is_active = NULL;
		switch($subscription->getSubStatus()) {
			case 'active' :
			case 'canceled' :
				$now = new DateTime();
				//check dates
				if(
						($now < (new DateTime($subscription->getSubPeriodEndsDate())))
						&&
						($now >= (new DateTime($subscription->getSubPeriodStartedDate())))
						) {
							//inside the period
							$is_active = 'yes';
						} else {
							//outside the period
							$is_active = 'no';
						}
						break;
			case 'future' :
				$is_active = 'no';
				break;
			case 'expired' :
				$is_active = 'no';
				break;
			default :
				$is_active = 'no';
				config::getLogger()->addWarning("idipper dbsubscription unknown subStatus=".$subscription->getSubStatus().", idipper_subscription_uuid=".$subscription->getSubUid().", id=".$subscription->getId());
				break;
		}
		//done
		$subscription->setIsActive($is_active);
	}
	
	public function doSendSubscriptionEvent(BillingsSubscription $subscription_before_update = NULL, BillingsSubscription $subscription_after_update) {
		parent::doSendSubscriptionEvent($subscription_before_update, $subscription_after_update);
	}
	
}

?>