<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../transactions/TransactionsHandler.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../providers/global/requests/RefundTransactionRequest.php';
require_once __DIR__ . '/../providers/global/requests/GetTransactionRequest.php';

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
			$transactionBillingUuid = NULL;
			if(!isset($args['transactionBillingUuid'])) {
				//exception
				$msg = "field 'transactionBillingUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$transactionBillingUuid = $args['transactionBillingUuid'];
			$transactionsHandler = new TransactionsHandler();
			$refundTransactionRequest = new RefundTransactionRequest();
			$refundTransactionRequest->setOrigin('api');
			$refundTransactionRequest->setTransactionBillingUuid($transactionBillingUuid);
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
	
}

?>