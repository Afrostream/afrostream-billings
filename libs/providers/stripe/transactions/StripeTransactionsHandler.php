<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../utils/utils.php';
require_once __DIR__ . '/../../../utils/BillingsException.php';

class StripeTransactionsHandler {
	
	const STRIPE_LIMIT = 50;
	
	public function __construct() {
		\Stripe\Stripe::setApiKey(getenv('STRIPE_API_KEY'));
	}
	
	public function doUpdateTransactionsByUser(User $user, UserOpts $userOpts) {
		try {
			config::getLogger()->addInfo("updating stripe transactions...");
			//CHARGES
			$params = ['customer' => $user->getUserProviderUuid()];
			$options = ['limit' => self::STRIPE_LIMIT];
			$hasMoreCharges = true;
			while ($hasMoreCharges) {
				if (isset($offsetCharges)) {
					$options['starting_after'] = $offsetCharges;
				}
				$stripeChargeTransactionsResponse = \Stripe\Charge::all($params, $options);
				$hasMoreCharges = $stripeChargeTransactionsResponse['has_more'];
				$stripeChargeTransactions = $stripeChargeTransactionsResponse['data'];
				$offsetCharges = end($stripeChargeTransactions);
				reset($stripeChargeTransactions);
				//
				foreach ($stripeChargeTransactions as $stripeChargeTransaction) {
					$billingsTransaction = new BillingsTransaction();
					$billingsTransaction->setTransactionLinkId(NULL);
					$billingsTransaction->setProviderId($user->getProviderId());
					$billingsTransaction->setUserId($user->getId());
					$billingsTransaction->setSubId(NULL);//TODO
					$billingsTransaction->setCouponId(NULL);//TODO
					$billingsTransaction->setInvoiceId(NULL);//TODO
					$billingsTransaction->setTransactionBillingUuid(guid());
					$billingsTransaction->setTransactionProviderUuid($stripeChargeTransaction->id);
					$billingsTransaction->setTransactionCreationDate(DateTime::createFromFormat('U', $stripeChargeTransaction->created));
					$billingsTransaction->setAmountInCents($stripeChargeTransaction->amount);
					$billingsTransaction->setCurrency($stripeChargeTransaction->currency);
					$billingsTransaction->setCountry($stripeChargeTransaction->source->country);
					$billingsTransaction->setTransactionStatus(self::getChargeMappedTransactionStatus($stripeChargeTransaction));
					$billingsTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::purchase));
					if(isset($stripeChargeTransaction->invoice)) {
						$billingsTransaction->setInvoiceProviderUuid($stripeChargeTransaction->invoice);
					}
					$billingsTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsTransaction);
					$this->doImportRefunds($billingsTransaction, $stripeChargeTransaction);
				}
			}
			//
			config::getLogger()->addInfo("updating stripe transactions done successfully");
		} catch(BillingsException $e) {
			$msg = "a billings exception occurred while updating stripe transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating stripe transactions failed : ".$msg);
			throw $e;
		} catch(Recurly_NotFoundError $e) {
			$msg = "a not found error exception occurred while updating stripe transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating stripe transactions failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Recurly_ValidationError $e) {
			$msg = "a validation error exception occurred while updating stripe transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError("updating stripe transactions failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::provider), $e->getMessage(), $e->getCode(), $e);
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while updating stripe transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
		config::getLogger()->addError("updating stripe transactions failed : ".$msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $e->getMessage(), $e->getCode(), $e);
		}
	}
	
	private static function getChargeMappedTransactionStatus(\Stripe\Charge $stripeChargeTransaction) {
		$billingTransactionStatus = NULL;
		if($stripeChargeTransaction->paid) {
			if($stripeChargeTransaction->captured) {
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::success);
			} else {
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
			}
		} else {
			$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::declined);
		}
		return($billingTransactionStatus);
	}
	
	private static function getRefundMappedTransactionStatus(\Stripe\Refund $stripeRefundTransaction) {
		$billingTransactionStatus = NULL;
		switch($stripeRefundTransaction->status) {
			case 'succeeded' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::success);
				break;
			case 'pending' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::waiting);
				break;
			case 'failed' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::failed);
				break;
			case 'cancelled' :
				$billingTransactionStatus = new BillingsTransactionStatus(BillingsTransactionStatus::canceled);
				break;
			default :
				throw new BillingsException(new ExceptionType(ExceptionType::internal), "unknown stripe refund transaction type : ".$stripeRefundTransaction->status);
				break;
		}
		return($billingTransactionStatus);
	}
	
	private function doImportRefunds(BillingsTransaction $billingsTransaction, \Stripe\Charge $stripeChargeTransaction) {
		$params = ['charge' => $stripeChargeTransaction->id];
		$options = ['limit' => self::STRIPE_LIMIT];
		$hasMoreRefunds = true;
		while ($hasMoreRefunds) {
			if (isset($offsetRefunds)) {
				$options['starting_after'] = $offsetRefunds;
			}
			$stripeRefundTransactionsResponse = \Stripe\Refund::all($params, $options);
			$hasMoreRefunds = $stripeRefundTransactionsResponse['has_more'];
			$stripeRefundTransactions = $stripeRefundTransactionsResponse['data'];
			$offsetRefunds = end($stripeRefundTransactions);
			reset($stripeRefundTransactions);
			//
			foreach ($stripeRefundTransactions as $stripeRefundTransaction) {
				$billingsRefundTransaction = new BillingsTransaction();
				$billingsRefundTransaction->setTransactionLinkId($billingsTransaction->getId());
				$billingsRefundTransaction->setProviderId($billingsTransaction->getProviderId());
				$billingsRefundTransaction->setUserId($billingsTransaction->getUserId());
				$billingsRefundTransaction->setSubId($billingsTransaction->getSubId());
				$billingsRefundTransaction->setCouponId($billingsTransaction->getCouponId());
				$billingsRefundTransaction->setInvoiceId($billingsTransaction->getInvoiceId());
				$billingsRefundTransaction->setTransactionBillingUuid(guid());
				$billingsRefundTransaction->setTransactionProviderUuid($stripeRefundTransaction->id);
				$billingsRefundTransaction->setTransactionCreationDate(DateTime::createFromFormat('U', $stripeRefundTransaction->created));
				$billingsRefundTransaction->setAmountInCents($stripeRefundTransaction->amount);
				$billingsRefundTransaction->setCurrency($stripeRefundTransaction->currency);
				$billingsRefundTransaction->setCountry($billingsTransaction->getCountry());//Country = Country of the Charge
				$billingsRefundTransaction->setTransactionStatus(self::getRefundMappedTransactionStatus($stripeRefundTransaction));
				$billingsRefundTransaction->setTransactionType(new BillingsTransactionType(BillingsTransactionType::refund));
				if(isset($stripeChargeTransaction->invoice)) {
					$billingsRefundTransaction->setInvoiceProviderUuid($stripeChargeTransaction->invoice);
				}
				$billingsRefundTransaction = BillingsTransactionDAO::addBillingsTransaction($billingsRefundTransaction);
			}
		}
	}
	
}

?>