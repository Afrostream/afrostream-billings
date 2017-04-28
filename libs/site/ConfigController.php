<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../db/dbGlobal.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class ConfigController extends BillingsController {
	
	public function getConfig(Request $request, Response $response, array $args) {
		try {
			//TODO : use GetConfigRequest + platformisation
			$config = BillingConfigDAO::getBillingConfigByPlatformId(getEnv('PLATFORM_DEFAULT_ID'));
			return($this->returnObjectAsJson($response, 'config', $config));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting config, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting config, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
	
}