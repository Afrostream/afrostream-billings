<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ .'/InternalPlansController.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class InternalPlansFilteredController extends InternalPlansController {
	
	public function getOne(Request $request, Response $response, array $args) {
		return(parent::getOne($request, $response, $args));
	}
	
	public function getMulti(Request $request, Response $response, array $args) {
		return(parent::getMulti($request, $response, $args));
	}
	
	public function create(Request $request, Response $response, array $args) {
		return(parent::create($request, $response, $args));
	}
	
	public function addToProvider(Request $request, Response $response, array $args) {
		return(parent::addToProvider($request, $response, $args));
	}
	
	public function update(Request $request, Response $response, array $args) {
		return(parent::update($request, $response, $args));
	}
	
}

?>