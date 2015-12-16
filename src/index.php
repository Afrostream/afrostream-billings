<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../libs/site/UsersController.php';

header("Content-Type: text/html");

$router = new AltoRouter();
$router->setBasePath('');


$router->map('POST', '/billings/api/users/', function() {
	$usersController = new UsersController();
	$usersController->create();
});

// Match the current request 
$match = $router->match();
if( $match && is_callable( $match['target'] ) ) {
	call_user_func_array( $match['target'], $match['params'] );
} else {
	header("HTTP/1.0 404 Not Found");
	//require '404.html';
}





?>