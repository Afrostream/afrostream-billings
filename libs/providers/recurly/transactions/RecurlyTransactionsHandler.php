<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class RecurlyTransactionsHandler {
	
	public function __construct() {
	}
	
	public function doUpdateTransactionsByUser(User $user, UserOpts $userOpts) {
		try {
			config::getLogger()->addInfo("xxx ...");
			//
			Recurly_Client::$subdomain = getEnv('RECURLY_API_SUBDOMAIN');
			Recurly_Client::$apiKey = getEnv('RECURLY_API_KEY');
			//
			$recurlyAccount = Recurly_Account::get($user->getUserProviderUuid());
			//
			$recurlyTransactions = Recurly_TransactionList::getForAccount($user->getUserProviderUuid());
			//
			foreach ($recurlyTransactions as $recurlyTransaction) {
				$billingsTransaction = new BillingsTransaction();
				$billingsTransaction->setProviderId($user->getProviderId());
				$billingsTransaction->setUserId($user->getId());
				$billingsTransaction->setSubId(NULL);//TODO
				$billingsTransaction->setCouponId(NULL);//TODO
				$billingsTransaction->setInvoiceId(NULL);//TODO
				$billingsTransaction->setTransactionBillingUuid(guid());
				$billingsTransaction->setTransactionProviderUuid($recurlyTransaction->uuid);
				$billingsTransaction->setTransactionCreationDate($recurlyTransaction->created_at);
				$billingsTransaction->setAmountInCents($recurlyTransaction->amount_in_cents);
				$billingsTransaction->setCurrency($recurlyTransaction->currency);
				$billingsTransaction->setCountry($recurlyAccount->billing_info->get()->country);
				$billingsTransaction->setTransactionStatus(new BillingsTransactionStatus(BillingsTransactionStatus::success));//TODO
				$billingsTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::purchase));//TODO
				if(isset($recurlyTransaction->invoice)) {
					$billingsTransaction->setInvoiceProviderUuid($recurlyTransaction->invoice->get()->uuid);
				}
				//TODO
				/*$msg =
				"transaction : uuid=".$recurlyTransaction->uuid.
				", action=".$recurlyTransaction->action.
				", status=".$recurlyTransaction->status.
				", amount_in_cents=".$recurlyTransaction->amount_in_cents.
				", currency=".$recurlyTransaction->currency.
				", tax_in_cents=".$recurlyTransaction->tax_in_cents.
				", source=".$recurlyTransaction->source;
				if(isset($recurlyTransaction->invoice)) {
					$msg.= ", invoiceUid=".$recurlyTransaction->invoice->get()->uuid;
				}
				if($recurlyTransaction->source == 'subscription') {
					$msg.= ", subscriptionUid=".$recurlyTransaction->subscription->get()->uuid;
				}
				$msg.= ", country=".$recurlyAccount->billing_info->get()->country;
				ScriptsConfig::getLogger()->addInfo($msg);*/
				$billingsTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsTransaction);
			}
			//
			config::getLogger()->addInfo("xxx done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while xxx, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("xxx failed : ".$msg);
			throw $e;
		} catch(Recurly_NotFoundError $e) {
			$msg = "a not found error exception occurred while xxx, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("xxx failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while xxx, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("xxx failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while xxx, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("xxx failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
	}
}

?>