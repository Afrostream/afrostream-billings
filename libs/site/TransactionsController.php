<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../transactions/TransactionsHandler.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../providers/global/requests/RefundTransactionRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetTransactionRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetUserTransactionsRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetSubscriptionTransactionsRequest.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class TransactionsController extends BillingsController {
	
	public function getOne(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$transactionBillingUuid = NULL;
			if(!isset($args['transactionBillingUuid'])) {
				//exception
				$msg = "field 'transactionBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$transactionBillingUuid = $args['transactionBillingUuid'];
			//
			$transactionsHandler = new TransactionsHandler();
			$getTransactionRequest = new GetTransactionRequest();
			$getTransactionRequest->setOrigin('api');
			$getTransactionRequest->setTransactionBillingUuid($transactionBillingUuid);
			$transaction = $transactionsHandler->doGetTransaction($getTransactionRequest);
			if($transaction == NULL) {
				return($this->returnNotFoundAsJson($response));
			} else {
				return($this->returnObjectAsJson($response, 'transaction', $transaction));
			}
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting a transaction, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a transaction, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function refund(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			$transactionBillingUuid = NULL;
			if(!isset($args['transactionBillingUuid'])) {
				//exception
				$msg = "field 'transactionBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$amountInCents = NULL;
			if(isset($data['amountInCents'])) {
				$amountInCents = $data['amountInCents'];
			}
			$transactionBillingUuid = $args['transactionBillingUuid'];
			$transactionsHandler = new TransactionsHandler();
			$refundTransactionRequest = new RefundTransactionRequest();
			$refundTransactionRequest->setOrigin('api');
			$refundTransactionRequest->setTransactionBillingUuid($transactionBillingUuid);
			$refundTransactionRequest->setAmountInCents($amountInCents);
			$transaction = $transactionsHandler->doRefundTransaction($refundTransactionRequest);
			return($this->returnObjectAsJson($response, 'transaction', $transaction));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while refunding a transaction, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while refunding a transaction, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function getMulti(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			$requestIsOk = false;
			$userReferenceUuid = NULL;
			if(isset($data['userReferenceUuid'])) {
				$requestIsOk = true;
				$userReferenceUuid = $data['userReferenceUuid'];
			}
			$subscriptionBillingUuid = NULL;
			if(isset($data['subscriptionBillingUuid'])) {
				$requestIsOk = true;
				$subscriptionBillingUuid = $data['subscriptionBillingUuid'];
			}
			if(!$requestIsOk) {
				//exception
				$msg = "field 'userReferenceUuid' or field 'subscriptionBillingUuid' are missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$offset = NULL;
			if(isset($data['offset'])) {
				$offset = $data['offset'];
			}
			$limit = NULL;
			if(isset($data['limit'])) {
				$limit = $data['limit'];
			}			
			$transactions = NULL;
			$transactionsHandler = new TransactionsHandler();
			if(isset($userReferenceUuid)) {
				$getUserTransactionsRequest = new GetUserTransactionsRequest();
				$getUserTransactionsRequest->setOrigin('api');
				$getUserTransactionsRequest->setUserReferenceUuid($userReferenceUuid);
				$getUserTransactionsRequest->setOffset($offset);
				$getUserTransactionsRequest->setLimit($limit);
				$transactions = $transactionsHandler->doGetUserTransactions($getUserTransactionsRequest);
			} else if(isset($subscriptionBillingUuid)) {
				$getSubscriptionTransactionsRequest = new GetSubscriptionTransactionsRequest();
				$getSubscriptionTransactionsRequest->setOrigin('api');
				$getSubscriptionTransactionsRequest->setSubscriptionBillingUuid($subscriptionBillingUuid);
				$getSubscriptionTransactionsRequest->setOffset($offset);
				$getSubscriptionTransactionsRequest->setLimit($limit);
				$transactions = $transactionsHandler->doGetSubscriptionTransactions($getSubscriptionTransactionsRequest);
			} else {
				//exception (should not happen)
				$msg = "field 'userReferenceUuid' or field 'subscriptionBillingUuid' are missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}			
			return($this->returnObjectAsJson($response, NULL, $transactions));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting transactions, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting transactions, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
	public function importTransactions(Request $request, Response $response, array $args) {
		try {
			$data = json_decode($request->getBody(), true);
			if(!isset($data['providerName'])) {
				//exception
				$msg = "field 'providerName' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$providerName = $data['providerName'];
			//FILE
			//TODO
		} catch(Exception $e) {
			//TODO
		}
	}
	
}

?>