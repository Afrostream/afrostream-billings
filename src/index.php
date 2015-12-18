<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../libs/site/UsersController.php';
require_once __DIR__ . '/../libs/site/SubscriptionsController.php';

$app = new \Slim\App();

//Users

$app->post("/billings/api/users/", function ($request, $response, $args) {
	$usersController = new UsersController();
	return($usersController->create($request, $response, $args));
});


//Subscriptions

$app->post("/billings/api/subscriptions/", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->create($request, $response, $args));
});

//WebHooks

//WebHooks - Recurly



//WebHooks - Gocardless



$app->run();

?>