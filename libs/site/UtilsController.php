<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../utils/MoneyUtils.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class UtilsController extends BillingsController {
	
	public function getCurrencyQuotes(Request $request, Response $response, array $args) {
		try {
			$data = $request->getQueryParams();
			if(!isset($args['fromCurrency'])) {
				//exception
				$msg = "field 'fromCurrency' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$fromCurrency = $args['fromCurrency'];
			if(!isset($args['toCurrencies'])) {
				//exception
				$msg = "field 'toCurrencies' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			$toCurrencies = $args['toCurrencies'];
			$toCurrencies_as_array = explode(';', $toCurrencies);
			//
			$currencyQuotes = array();
			$currencyQuotes['currency'] = $fromCurrency;
			foreach ($toCurrencies_as_array as $toCurrency) {
				$currencyQuotes['quotes'][$toCurrency] = MoneyUtils::getLatestRate($fromCurrency.'/'.$toCurrency);
			}
			return($this->returnObjectAsJson($response, 'currencyQuotes', $currencyQuotes));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting currency quotes, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting currency quotes, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}