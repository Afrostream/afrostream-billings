<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use \Slim\Http\Response;

class BillingsController {
	

	protected function returnBillingsExceptionAsJson(Response $response, BillingsException $e, $statusCode = 200) {
		$json_as_array = array();
		$json_as_array['status'] = 'error';
		$json_as_array['statusMessage'] = $e->getMessage();
		$json_as_array['statusCode'] = $e->getCode();
		$json_as_array['statusType'] =  (string) $e->getExceptionType();
		$json_error_as_array = array(
				"error" => array(
						"errorMessage" => $e->getMessage(),
						"errorType" => (string) $e->getExceptionType(),
						"errorCode" => $e->getCode()
				)
	
		);
		$json_as_array['errors'][] = $json_error_as_array;
		$json = json_encode($json_as_array);
		$response = $response->withStatus(statusCode);
		$response = $response->withHeader('Content-Type', 'application/json');
		$response->getBody()->write($json);
		return($response);
	}
	
	protected function returnExceptionAsJson(Response $response, Exception $e, $statusCode = 200) {
		$json_as_array = array();
		$json_as_array['status'] = 'error';
		$json_as_array['statusMessage'] = $e->getMessage();
		$json_as_array['statusCode'] = $e->getCode();
		$json_as_array['statusType'] =  'unknown';
		$json_error_as_array = array(
				"error" => array(
						"errorMessage" => $e->getMessage(),
						"errorType" => 'unknown',
						"errorCode" => $e->getCode()
				)
	
		);
		$json_as_array['errors'][] = $json_error_as_array;
		$json = json_encode($json_as_array);
		$response = $response->withStatus(statusCode);
		$response = $response->withHeader('Content-Type', 'application/json');
		$response->getBody()->write($json);
		return($response);
	}
	
	protected function returnObjectAsJson(Response $response, $response_name, $object, $statusCode = 200) {
		//
		$json_as_array = array();
		$json_as_array['status'] = 'done';
		$json_as_array['statusMessage'] = 'success';
		$json_as_array['statusCode'] = 0;
		$json_object = json_encode($object, JSON_UNESCAPED_UNICODE);
		$json_as_array['response'][$response_name] = json_decode($json_object, true);
		//
		$json = json_encode($json_as_array);
		$response = $response->withStatus(statusCode);
		$response = $response->withHeader('Content-Type', 'application/json');
		$response->getBody()->write($json);
		return($response);
	}
	
}