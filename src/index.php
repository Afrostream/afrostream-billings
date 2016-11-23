<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../libs/site/UsersController.php';
require_once __DIR__ . '/../libs/site/SubscriptionsController.php';
require_once __DIR__ . '/../libs/site/InternalPlansFilteredController.php';
require_once __DIR__ . '/../libs/site/InternalCouponsController.php';
require_once __DIR__ . '/../libs/site/UsersInternalCouponsController.php';
require_once __DIR__ . '/../libs/site/WebHooksController.php';
require_once __DIR__ . '/../libs/site/InternalCouponsCampaignsController.php';
require_once __DIR__ . '/../libs/site/ContextsController.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

$c = new \Slim\Container();
$c['errorHandler'] = function ($c) {
    return function (Request $request, Response $response, Exception $exception) use ($c) {
    	config::getLogger()->addCritical("HTTP 500, ".$request->getMethod().
    			", error_code=".$exception->getCode().
    			", error_message=".$exception->getMessage().
    			", params=".print_r($request->getParams(), true));
		return $c['response']->withStatus(500)
                             ->withHeader('Content-Type', 'text/html')
                             ->write('Something went wrong!');
    };
};

$app = new \Slim\App($c);

$app->add(function (Request $req, Response $res, callable $next) {
	if(getEnv('LOG_REQUESTS_ACTIVATED') == 1) {
		$msg = "REQUEST method=".$req->getMethod();
		$msg.= " path='".$req->getUri()->getPath()."'";
		$msg.= " params='".http_build_query($req->getQueryParams())."'";
		$msg.= " body='".$req->getBody()."'";
		config::getLogger()->addInfo($msg);
	}
	return $next($req, $res);
});

//API BASIC AUTH ACTIVATION

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
		"path" => "/billings/api",
		"secure" => getEnv('API_HTTP_SECURE') == 1 ? true : false,
		"users" => [
				getEnv('API_HTTP_AUTH_USER') => getEnv('API_HTTP_AUTH_PWD')
		]
]));

/**
 * @api {get} /billings/api/users/:userBillingUuid Request User Information
 * @apiDescription It returns the user with the userBillingUuid given.
 * @apiParam {String} :userBillingUuid Api uuid of the user.
 *
 * @apiExample {curl} Example usage:
 *     curl -i http://localhost/billings/api/users/11111111-1111-1111-1111-111111111111
 * @apiSuccess {json} User Information
 *
 * @apiSuccessExample Success-Response:
 * 		HTTP/1.1 200 OK
 *		{
 *			"status": "done",
 *			"statusMessage": "success",
 *			"statusCode": 0,
 *			"response": {
 *   			"user": {
 *     				"userBillingUuid": "11111111-1111-1111-1111-111111111111",
 *     				"userReferenceUuid": "1111",
 *     				"userProviderUuid": "8888",
 *     				"provider": {
 *       				"providerName": "recurly"
 *     				},
 *     			"userOpts": {
 *       			"email": "email@domain.com",
 *       			"lastName": "lastNameValue",
 *       			"firstName": "firstNameValue"
 *					}
 *				}
 *			}
 *		}
 *
 * @apiError UserNotFound	When the user cannot be found
 *
 * @apiErrorExample Error-Response:
 *		HTTP/1.1 404 Not Found
 *		{
 * 			"status": "error",
 * 			"statusMessage": "NOT FOUND",
 * 			"statusCode": 0,
 * 			"statusType": "internal",
 * 			"errors": [
 *   			{
 *     			"error": {
 *       			"errorMessage": "NOT FOUND",
 *       			"errorType": "internal",
 *       			"errorCode": 0
 *     				}
 *   			}
 * 			]
 *		}
 */

$app->get("/billings/api/users/{userBillingUuid}", function ($request, $response, $args) {
	$usersController = new UsersController();
	return($usersController->get($request, $response, $args));
});

/**
 * @api {get} /billings/api/users/ Request User Information
 * @apiDescription It returns user which belongs to the provider named by providerName and which reference uuid equals the userReferenceUuid given.
 * @apiParam {String} providerName Provider name to which the user belongs.
 * @apiParam {String} userReferenceUuid Reference uuid of the user.
 *
 * @apiExample {curl} Example usage:
 *     curl -i http://localhost/billings/api/users/?providerName=recurly&userReferenceUuid=8888
 *
 * @apiSuccess {json} User Information
 *
 * @apiSuccessExample Success-Response:
 * 		HTTP/1.1 200 OK
 *		{
 *			"status": "done",
 *			"statusMessage": "success",
 *			"statusCode": 0,
 *			"response": {
 *   			"user": {
 *     				"userBillingUuid": "11111111-1111-1111-1111-111111111111",
 *     				"userReferenceUuid": "1111",
 *     				"userProviderUuid": "8888",
 *     				"provider": {
 *       				"providerName": "recurly"
 *     				},
 *     			"userOpts": {
 *       			"email": "email@domain.com",
 *       			"lastName": "lastNameValue",
 *       			"firstName": "firstNameValue"
 *					}
 *				}
 *			}
 *		}
 */

$app->get("/billings/api/users/", function ($request, $response, $args) {
	$usersController = new UsersController();
	return($usersController->get($request, $response, $args));
});

/**
 * @api {post} /billings/api/users/ Request User Creation
 * @apiDescription It creates (or get) an user linked to a provider.
 * @apiParam (postData) userProviderUuid userProviderUuid is not mandatory. 
 * It has to be used when you provider is compatible with 
 * and when you want to get back a user from the provider instead of creating it from scratch.
 *
 * @apiParamExample {json} Request-Example:
 * 
 *		{
 *   		"providerName" : "recurly",
 *   		"userReferenceUuid" : "1111",	
 *   		"userProviderUuid" : "8888",
 *   		"userOpts" : {
 *       		"email" : "email@domain.com",
 *       		"firstName" : "firstNameValue",
 *       		"lastName" : "lastNameValue"
 *   		}
 *  	}
 *
 * @apiSuccess {json} User Information
 *
 * @apiSuccessExample Success-Response:
 * 		HTTP/1.1 200 OK
 *		{
 *			"status": "done",
 *			"statusMessage": "success",
 *			"statusCode": 0,
 *			"response": {
 *   			"user": {
 *     				"userBillingUuid": "11111111-1111-1111-1111-111111111111",
 *     				"userReferenceUuid": "1111",
 *     				"userProviderUuid": "8888",
 *     				"provider": {
 *       				"providerName": "recurly"
 *     				},
 *     			"userOpts": {
 *       			"email": "email@domain.com",
 *       			"firstName": "firstNameValue",
 *       			"lastName": "lastNameValue"
 *					}
 *				}
 *			}
 *		}
 */

$app->post("/billings/api/users/", function ($request, $response, $args) {
	$usersController = new UsersController();
	return($usersController->create($request, $response, $args));
});

/**
 * @api {put} /billings/api/users/ Request User(s) Update
 * @apiDescription It updates User Informations given in the userOpts section (email, firstName, lastName, etc.).
 * @apiParam {String} [userBillingUuid] (NOT RECOMMENDED) Api uuid of the user. 
 * Will update User Informations for only one provider.
 * @apiParam {String} [userReferenceUuid] (RECOMMENDED) Provider uuid of the user.
 * Will update User Informations for all providers linked to the userReferenceUuid given.
 * 
 * @apiParamExample {json} Request-Example:
 * 
 *		{
 *   		"userOpts" : {
 *       		"email" : "email@domain.com",
 *       		"firstName" : "firstNameValue",
 *       		"lastName" : "lastNameValue"
 *   		}
 *  	}
 *  
 * @apiSuccess {json} User Information
 *
 * @apiSuccessExample Success-Response:
 * 		HTTP/1.1 200 OK
 *		{
 *			"status": "done",
 *			"statusMessage": "success",
 *			"statusCode": 0,
 *			"response": {
 *   			"user": {
 *     				"userBillingUuid": "11111111-1111-1111-1111-111111111111",
 *     				"userReferenceUuid": "1111",
 *     				"userProviderUuid": "8888",
 *     				"provider": {
 *       				"providerName": "recurly"
 *     				},
 *     			"userOpts": {
 *       			"email": "email@domain.com",
 *       			"firstName": "firstNameValue",
 *       			"lastName": "lastNameValue"
 *					}
 *				}
 *			}
 *		}
 */

$app->put("/billings/api/users/{userBillingUuid}", function ($request, $response, $args) {
	$usersController = new UsersController();
	return($usersController->update($request, $response, $args));
});

$app->put("/billings/api/users/", function ($request, $response, $args) {
	$usersController = new UsersController();
	return($usersController->updateUsers($request, $response, $args));
});

/**
 * @api {get} /billings/api/subscriptions/:subscriptionBillingUuid Request Subscription Information
 * @apiDescription It returns Subscription Information.
 * @apiParam {String} :subscriptionBillingUuid Api uuid of the subscription. It returns the subscription with the subscriptionBillingUuid given.
 * @apiExample {curl} Example usage:
 *     curl -i http://localhost/billings/api/subscriptions/33333333-3333-3333-3333-333333333333
 *
 * @apiSuccess {json} Subscription Information
 *
 * @apiSuccessExample Success-Response:
 * 		HTTP/1.1 200 OK
 *		{
 *			"status": "done",
 *			"statusMessage": "success",
 *			"statusCode": 0,
 *			"response": {
 *   			"subscription": {
 *     				"subscriptionBillingUuid": "33333333-3333-3333-3333-333333333333",
 *     				"subscriptionProviderUuid": "9999",
 *     				"isActive": "no",
 *     				"inTrial": "no",
 *     				"isCancelable": "no",
 *     				"isReactivable": "no",
 *     				"user": {
 *       				"userBillingUuid": "11111111-1111-1111-1111-111111111111",
 *       				"userReferenceUuid": "1111",
 *       				"userProviderUuid": "8888",
 *       				"provider": {
 *         				"providerName": "recurly"
 *       				},
 *       				"userOpts": {
 *         					"lastName": "lastNameValue",
 *         					"firstName": "firstNameValue",
 *         					"email": "email@domain.com"
 *       				}
 *     				},
 *     				"provider": {
 *       				"providerName": "recurly"
 *     				},
 *     				"creationDate": "2016-09-18T02:00:22+0000",
 *     				"updatedDate": "2016-09-28T07:39:14+0000",
 *     				"subStatus": "expired",
 *     				"subActivatedDate": "2016-09-18T02:00:22+0000",
 *     				"subCanceledDate": "2016-09-28T07:39:07+0000",
 *     				"subExpiresDate": "2016-09-28T07:39:07+0000",
 *     				"subPeriodStartedDate": "2016-09-18T02:00:22+0000",
 *     				"subPeriodEndsDate": "2017-09-18T21:59:59+0000",
 *     				"subOpts": {
 *       				"customerBankAccountToken": "ABCD"
 *     				},
 *     				"billingInfo": {
 *       				"billingInfoBillingUuid": "77777777-7777-7777-7777-777777777777",
 *       				"creationDate": "2016-09-18T02:00:22+0000",
 *       				"updatedDate": "2016-09-18T02:00:22+0000",
 *       				"firstName": null,
 *       				"lastName": null,
 *       				"email": null,
 *       				"iban": null,
 *       				"countryCode": null,
 *       				"billingInfoOpts": [],
 *       				"paymentMethod": null
 *     				},
 *     				"internalPlan": {
 *       				"internalPlanUuid": "afrostreamambassadeursrts",
 *       				"name": "Sérénité",
 *       				"description": "59,99€ pour 1 an de films et séries afro",
 *       				"amountInCents": "5999",
 *       				"amount": "59,99",
 *       				"amountInCentsExclTax": "4999",
 *       				"amountExclTax": "49,99167",
 *       				"vatRate": "20,00",
 *       				"currency": "EUR",
 *       				"cycle": "auto",
 *       				"periodUnit": "year",
 *       				"periodLength": "1",
 *       				"internalPlanOpts": {
 *         					"internalMaxScreens": "2",
 *         					"internalVip": "true"
 *       				},
 *       				"thumb": null,
 *       				"trialEnabled": false,
 *       				"trialPeriodUnit": null,
 *       				"trialPeriodLength": null,
 *       				"isVisible": true,
 *       				"countries": [
 *         					{
 *           				"country": "FR"
 *         					}
 *       				]
 *     				}
 *   			}
 * 			}
 *		}
 *
 * @apiError SubscriptionNotFound When the subscription cannot be found
 *
 * @apiErrorExample Error-Response:
 *		HTTP/1.1 404 Not Found
 *		{
 * 			"status": "error",
 * 			"statusMessage": "NOT FOUND",
 * 			"statusCode": 0,
 * 			"statusType": "internal",
 * 			"errors": [
 *   			{
 *     			"error": {
 *       			"errorMessage": "NOT FOUND",
 *       			"errorType": "internal",
 *       			"errorCode": 0
 *     				}
 *   			}
 * 			]
 *		}
 */

$app->get("/billings/api/subscriptions/{subscriptionBillingUuid}", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->getOne($request, $response, $args));
});

//create a subscription

/*
	sample call :
 
 	{
    	"userBillingUuid" : "UserBillingUUID",				//given when creating a user
    	"internalPlanUuid" : "InternalPlanUuid",			//Plan (internal name)	 
    	"subscriptionProviderUuid" : "SubscriptionProviderUUID",//given by the provider when subscription is created from provider side
    	"billingInfo" : {
    		"firstName" : "firstNameValue",
    		"lastName" : "lastNameValue",
    		"email" : "emailValue",
    		"iban" : "ibanValue",
    		"countryCode" : "countryCodeValue",
    		"billingInfoOpts" : {
        		"key1": "value1",
        		"key2": "value2",
        		"key3": "value3"    			
    		},
    		"paymentMethod" : {
    			"paymentMethodType" : "card"
    		}
    	},
    	"subOpts": {
        	"key1": "value1",
        	"key2": "value2",
        	"key3": "value3"
      	}
	}
 */

$app->post("/billings/api/subscriptions/", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->create($request, $response, $args));
});

/**
 * @api {put} /billings/api/subscriptions/self-update Request Subscriptions Update From Provider
 * @apiDescription It updates Subscriptions Informations from provider side.
 * @apiParam {String} [userBillingUuid] Api uuid of the user. 
 * Will update Subscriptions Informations linked to the userBillingUuid given.
 * @apiParam {String} [userReferenceUuid] Provider uuid of the user.
 * Will update Subscriptions Informations linked to the userReferenceUuid given.
 *
 * @apiSuccess {json} Subscriptions Information
 *
 * @apiSuccessExample Success-Response:
 * 		HTTP/1.1 200 OK
 *		{
 *			"status": "done",
 *			"statusMessage": "success",
 *			"statusCode": 0,
 *			"response": {
 *   			"subscriptions": [
 *   				{...},{...}
 *   			]
 *			}
 *		}
 */

$app->put("/billings/api/subscriptions/self-update", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->updateMulti($request, $response, $args));
});

/**
 * @api {put} /billings/api/subscriptions/:subscriptionBillingUuid/self-update Request Subscription Update From Provider
 * @apiDescription It updates Subscription Information from provider side.
 * @apiParam {String} :subscriptionBillingUuid Api uuid of the subscription.
 * Will update Subscription Informations which Api uuid is the subscriptionBillingUuid given.
 *
 * @apiSuccess {json} Subscription Information
 *
 * @apiSuccessExample Success-Response:
 * 		HTTP/1.1 200 OK
 *		{
 *			"status": "done",
 *			"statusMessage": "success",
 *			"statusCode": 0,
 *			"response": {
 *				"subscription" {
 *					"..."
 *				}
 *			}
 *		}
 */

$app->put("/billings/api/subscriptions/{subscriptionBillingUuid}/self-update", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->updateOne($request, $response, $args));
});

/**
 * @api {get} /billings/api/subscriptions/ Request Subscriptions Information
 * @apiDescription It returns Subscriptions Information.
 * @apiParam {String} [userReferenceUuid] reference uuid of the user. It returns subscriptions which belong to users with the userReferenceUuid given.
 * @apiParam {String} [userBillingUuid] Api uuid of the user. It returns subscriptions which belong to users with the userBillingUuid given.
 *
 * @apiExample {curl} Example usage:
 *     curl -i http://localhost/billings/api/users/?userReferenceUuid=1111
 * @apiExample {curl} Example usage:
 *     curl -i http://localhost/billings/api/users/?userBillingUuid=11111111-1111-1111-1111-111111111111
 *
 * @apiSuccess {json} Subscriptions Information
 *
 * @apiSuccessExample Success-Response:
 * 		HTTP/1.1 200 OK
 *		{
 *			"status": "done",
 *			"statusMessage": "success",
 *			"statusCode": 0,
 *			"response": {
 *   			"subscriptions": [
 *   			{
 *     				"subscriptionBillingUuid": "33333333-3333-3333-3333-333333333333",
 *     				"subscriptionProviderUuid": "9999",
 *     				"isActive": "no",
 *     				"inTrial": "no",
 *     				"isCancelable": "no",
 *     				"isReactivable": "no",
 *     				"user": {
 *       				"userBillingUuid": "11111111-1111-1111-1111-111111111111",
 *       				"userReferenceUuid": "1111",
 *       				"userProviderUuid": "8888",
 *       				"provider": {
 *         				"providerName": "recurly"
 *       				},
 *       				"userOpts": {
 *         					"lastName": "lastNameValue",
 *         					"firstName": "firstNameValue",
 *         					"email": "email@domain.com"
 *       				}
 *     				},
 *     				"provider": {
 *       				"providerName": "recurly"
 *     				},
 *     				"creationDate": "2016-09-18T02:00:22+0000",
 *     				"updatedDate": "2016-09-28T07:39:14+0000",
 *     				"subStatus": "expired",
 *     				"subActivatedDate": "2016-09-18T02:00:22+0000",
 *     				"subCanceledDate": "2016-09-28T07:39:07+0000",
 *     				"subExpiresDate": "2016-09-28T07:39:07+0000",
 *     				"subPeriodStartedDate": "2016-09-18T02:00:22+0000",
 *     				"subPeriodEndsDate": "2017-09-18T21:59:59+0000",
 *     				"subOpts": {
 *       				"customerBankAccountToken": "ABCD"
 *     				},
 *     				"billingInfo": {
 *       				"billingInfoBillingUuid": "77777777-7777-7777-7777-777777777777",
 *       				"creationDate": "2016-09-18T02:00:22+0000",
 *       				"updatedDate": "2016-09-18T02:00:22+0000",
 *       				"firstName": null,
 *       				"lastName": null,
 *       				"email": null,
 *       				"iban": null,
 *       				"countryCode": null,
 *       				"billingInfoOpts": [],
 *       				"paymentMethod": null
 *     				},
 *     				"internalPlan": {
 *       				"internalPlanUuid": "afrostreamambassadeursrts",
 *       				"name": "Sérénité",
 *       				"description": "59,99€ pour 1 an de films et séries afro",
 *       				"amountInCents": "5999",
 *       				"amount": "59,99",
 *       				"amountInCentsExclTax": "4999",
 *       				"amountExclTax": "49,99167",
 *       				"vatRate": "20,00",
 *       				"currency": "EUR",
 *       				"cycle": "auto",
 *       				"periodUnit": "year",
 *       				"periodLength": "1",
 *       				"internalPlanOpts": {
 *         					"internalMaxScreens": "2",
 *         					"internalVip": "true"
 *       				},
 *       				"thumb": null,
 *       				"trialEnabled": false,
 *       				"trialPeriodUnit": null,
 *       				"trialPeriodLength": null,
 *       				"isVisible": true,
 *       				"countries": [
 *         					{
 *           				"country": "FR"
 *         					}
 *       				]
 *     				}
 *   			}]
 * 			}
 *		}
 */

$app->get("/billings/api/subscriptions/", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->getMulti($request, $response, $args));
});

/**
 * @api {put} /billings/api/subscriptions/:subscriptionBillingUuid/cancel Request Subscription Cancellation
 * @apiDescription It cancels a subscription.
 * @apiParam {String} :subscriptionBillingUuid Api uuid of the subscription.
 * Will cancel Subscription which Api uuid is the subscriptionBillingUuid given.
 *
 * @apiSuccess {json} Subscription Information
 *
 * @apiSuccessExample Success-Response:
 * 		HTTP/1.1 200 OK
 *		{
 *			"status": "done",
 *			"statusMessage": "success",
 *			"statusCode": 0,
 *			"response": {
 *				"subscription" {
 *					"..."
 *				}
 *			}
 *		}
 */

$app->put("/billings/api/subscriptions/{subscriptionBillingUuid}/cancel", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->cancel($request, $response, $args));
});

//renew a subscription (???needed in API ???)

$app->put("/billings/api/subscriptions/{subscriptionBillingUuid}/renew", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->renew($request, $response, $args));
});

/**
 * @api {put} /billings/api/subscriptions/:subscriptionBillingUuid/reactivate Request Subscription Reactivation
 * @apiDescription It reactivates a subscription.
 * @apiParam {String} :subscriptionBillingUuid Api uuid of the subscription.
 * Will reactivate Subscription which Api uuid is the subscriptionBillingUuid given.
 *
 * @apiSuccess {json} Subscription Information
 *
 * @apiSuccessExample Success-Response:
 * 		HTTP/1.1 200 OK
 *		{
 *			"status": "done",
 *			"statusMessage": "success",
 *			"statusCode": 0,
 *			"response": {
 *				"subscription" {
 *					"..."
 *				}
 *			}
 *		}
 */

$app->put("/billings/api/subscriptions/{subscriptionBillingUuid}/reactivate", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->reactivate($request, $response, $args));
});

//change plan from a subscription (???needed in API ???)

$app->put("/billings/api/subscriptions/{subscriptionBillingUuid}/updateinternalplan/{internalPlanUuid}", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->updateInternalPlan($request, $response, $args));
});

/**
 * @api {get} /billings/api/internalplans/:internalPlanUuid Request InternalPlan Information
 * @apiDescription It returns an InternalPlan Information.
 * @apiParam {String} :internalPlanUuid Api uuid of the internalPlan. It returns the internalPlan with the internalPlanUuid given.
 * @apiExample {curl} Example usage:
 *     curl -i http://localhost/billings/api/internalplans/afrostreammonthly
 *
 * @apiSuccess {json} InternalPlan Information
 *
 * @apiSuccessExample Success-Response:
 * 		HTTP/1.1 200 OK
 *		{
 *			"status": "done",
 *			"statusMessage": "success",
 *			"statusCode": 0,
 *			"response": {
 *		    "internalPlan": {
 *			      "internalPlanUuid": "afrostreammonthly",
 *			      "name": "Mensuel",
 *			      "description": "7 jours d'essai puis 6,99€ par mois sans engagement",
 *			      "amountInCents": "699",
 *			      "amount": "6,99",
 *			      "amountInCentsExclTax": "583",
 *			      "amountExclTax": "5,82500",
 *			      "vatRate": "20,00",
 *			      "currency": "EUR",
 *			      "cycle": "auto",
 *			      "periodUnit": "month",
 *			      "periodLength": "1",
 *			      "internalPlanOpts": {
 *			        "internalMaxScreens": "1",
 *			        "internalVip": "false"
 *			      },
 *			      "thumb": null,
 *			      "trialEnabled": true,
 *			      "trialPeriodUnit": "day",
 *			      "trialPeriodLength": "7",
 *			      "isVisible": true,
 *			      "countries": [
 *			        {
 *			          "country": "FR"
 *			        },
 *			        {
 *			          "country": "BE"
 *			        },
 *			        {
 *			          "country": "CH"
 *			        },
 *			        {
 *			          "country": "GF"
 *			        },
 *			        {
 *			          "country": "GP"
 *			        },
 *			        {
 *			          "country": "LU"
 *			        },
 *			        {
 *			          "country": "MF"
 *			        },
 *			        {
 *			          "country": "MQ"
 *			        },
 *			        {
 *			          "country": "PF"
 *			        },
 *			        {
 *			          "country": "RE"
 *			        }
 *			      ],
 *			      "providerPlans": {
 *			        "gocardless": {
 *			          "providerPlanUuid": "gcafrostreammonthly",
 *			          "name": "Formule mensuelle Afrostream",
 *			          "description": "Formule mensuelle Afrostream",
 *			          "provider": {
 *			            "providerName": "gocardless"
 *			          },
 *			          "paymentMethods": [
 *			            {
 *			              "paymentMethodType": "sepa",
 *			              "index": "2"
 *			            }
 *			          ],
 *			          "isVisible": true,
 *			          "isCouponCodeCompatible": false
 *			        },
 *			        "stripe": {
 *			          "providerPlanUuid": "afrostreammonthly",
 *			          "name": "afrostreammonthly-3",
 *			          "description": "afrostreammonthly-3",
 *			          "provider": {
 *			            "providerName": "stripe"
 *			          },
 *			          "paymentMethods": [
 *			            {
 *			              "paymentMethodType": "card",
 *			              "index": "1"
 *			            }
 *			          ],
 *			          "isVisible": true,
 *			          "isCouponCodeCompatible": true
 *			        },
 *			        "braintree": {
 *			          "providerPlanUuid": "afrostreammonthly",
 *			          "name": "afrostreammonthly-2",
 *			          "description": "afrostreammonthly-2",
 *			          "provider": {
 *			            "providerName": "braintree"
 *			          },
 *			          "paymentMethods": [
 *			            {
 *			              "paymentMethodType": "paypal",
 *			              "index": "3"
 *			            }
 *			          ],
 *			          "isVisible": true,
 *			          "isCouponCodeCompatible": true
 *			        }
 *			      },
 *			      "providerPlansByPaymentMethodType": {
 *			        "card": [
 *			          {
 *			            "stripe": {
 *			              "providerPlanUuid": "afrostreammonthly",
 *			              "name": "afrostreammonthly-3",
 *			              "description": "afrostreammonthly-3",
 *			              "provider": {
 *			                "providerName": "stripe"
 *			              },
 *			              "paymentMethods": [
 *			                {
 *			                  "paymentMethodType": "card",
 *			                  "index": "1"
 *			                }
 *			              ],
 *			              "isVisible": true,
 *			              "isCouponCodeCompatible": true
 *			            }
 *			          }
 *			        ],
 *			        "sepa": [
 *			          {
 *			            "gocardless": {
 *			              "providerPlanUuid": "gcafrostreammonthly",
 *			              "name": "Formule mensuelle Afrostream",
 *			              "description": "Formule mensuelle Afrostream",
 *			              "provider": {
 *			                "providerName": "gocardless"
 *			              },
 *			              "paymentMethods": [
 *			                {
 *			                  "paymentMethodType": "sepa",
 *			                  "index": "2"
 *			                }
 *			              ],
 *			              "isVisible": true,
 *			              "isCouponCodeCompatible": false
 *			            }
 *			          }
 *			        ],
 *			        "paypal": [
 *			          {
 *			            "braintree": {
 *			              "providerPlanUuid": "afrostreammonthly",
 *			              "name": "afrostreammonthly-2",
 *			              "description": "afrostreammonthly-2",
 *			              "provider": {
 *			                "providerName": "braintree"
 *			              },
 *			              "paymentMethods": [
 *			                {
 *			                  "paymentMethodType": "paypal",
 *			                  "index": "3"
 *			                }
 *			              ],
 *			              "isVisible": true,
 *			              "isCouponCodeCompatible": true
 *			            }
 *			          }
 *			        ]
 *			      }
 *			    }
 * 			}
 *		}
 *
 * @apiError InternalPlanNotFound When the internalPlan cannot be found
 *
 * @apiErrorExample Error-Response:
 *		HTTP/1.1 404 Not Found
 *		{
 * 			"status": "error",
 * 			"statusMessage": "NOT FOUND",
 * 			"statusCode": 0,
 * 			"statusType": "internal",
 * 			"errors": [
 *   			{
 *     			"error": {
 *       			"errorMessage": "NOT FOUND",
 *       			"errorType": "internal",
 *       			"errorCode": 0
 *     				}
 *   			}
 * 			]
 *		}
 */

$app->get("/billings/api/internalplans/{internalPlanUuid}", function ($request, $response, $args) {
	$internalPlansController = new InternalPlansFilteredController();
	return($internalPlansController->getOne($request, $response, $args));
});

/**
 * @api {get} /billings/api/internalplans/ Request InternalPlans Information
 * @apiDescription It returns InternalPlans Information.
 * @apiParam {String} [providerName] Name of the provider.
 * It returns internalplans which belongs to the named provider.
 * @apiParam {String} [isVisible=true]
 * @apiParam {String} [country]
 * @apiParam {String} [contextBillingUuid]
 * @apiParam {String} [contextCountry]
 * @apiParam {String} [filterEnabled=false]
 * @apiParam {String} [filterUserReferenceUuid]
 * @apiParam {String} [filterCountry]
 * @apiExample {curl} Example usage:
 *     curl -i http://localhost/billings/api/internalplans/?providerName=recurly
 *
 * @apiSuccess {json} InternalPlans Information
 *
 * @apiSuccessExample Success-Response:
 * 		HTTP/1.1 200 OK
 *		{
 *			"status": "done",
 *			"statusMessage": "success",
 *			"statusCode": 0,
 *			"response": {
 *		     	"internalPlans": [
 * 					{...},{...}
 * 				]}
 *		}
 */

$app->get("/billings/api/internalplans/", function ($request, $response, $args) {
	$internalPlansController = new InternalPlansFilteredController();
	return($internalPlansController->getMulti($request, $response, $args));
});

//create

/*
	sample call :
	
	{				
		"internalPlanUuid" : "InternalPlanUuid",	//internal plan uuid (should not be changed)
		"name" : "name",							//internal name (can be changed)
		"description" : "description",				//internal description (can be changed)
		"amountInCents" : "1000",					//10 Euros
		"currency" : "EUR",							//ISO 4217
		"cycle" : "once",							//	"once", "auto"
		"periodUnit" : "month",						//	"day", "month", "year"
		"periodLength" : "1",						//  number of days, months, years in the period
		"internalPlanOpts": {
        	"internalMaxScreens" : "2",				//  array of free key value pairs
        	"key1" : "value1",
        	"key2" : "value2"
        }
	}
	
 	sample answer :
 	
	{
  		"status": "done",
  		"statusMessage": "success",
  		"statusCode": 0,
  		"response": {
  		    "internalPlan": {
      			"internalPlanUuid": "InternalPlanUuid",
      			"name": "name",
      			"description": "description",
      			"amountInCents": "1000",
      			"currency": "EUR",
      			"cycle": "once",
      			"periodUnit": "month",
      			"periodLength" : "1",
				"internalPlanOpts": {
        			"internalMaxScreens" : "2",
        			"key1" : "value1",
        			"key2" : "value2"
        		},
				"thumb": {
					"path": "/path/jpeg.jpg",
					"imgix": "https://mydomain.com/path/jpeg.jpg"
				},
				"trialEnabled": true,
				"trialPeriodUnit": "day",
				"trialPeriodLength": "7",
				"isVisible": true,
				"countries": [
			     	{
			        	"country": "FR"
			        },
			       	{
			       		"country": "ES"
			        },
			        {
			        	"country": "EP"
			        },
			       	{
			        	"country": "PT"
			        }
			    ]
    		}
  		}
  	}
	
*/

$app->post("/billings/api/internalplans/", function ($request, $response, $args) {
	$internalPlansController = new InternalPlansFilteredController();
	return($internalPlansController->create($request, $response, $args));
});

//update

$app->put("/billings/api/internalplans/{internalPlanUuid}", function ($request, $response, $args) {
	$internalPlansController = new InternalPlansFilteredController();
	return($internalPlansController->update($request, $response, $args));
});

//actions to internalPlan : addtoprovider

$app->put("/billings/api/internalplans/{internalPlanUuid}/addtoprovider/{providerName}", function ($request, $response, $args) {
	$internalPlansController = new InternalPlansFilteredController();
	return($internalPlansController->addToProvider($request, $response, $args));
});

//actions to internalPlan : addotocountry

$app->put("/billings/api/internalplans/{internalPlanUuid}/addtocountry/{country}", function ($request, $response, $args) {
	$internalPlansController = new InternalPlansFilteredController();
	return($internalPlansController->addToCountry($request, $response, $args));
});

//actions to internalPlan : removefromcountry

$app->put("/billings/api/internalplans/{internalPlanUuid}/removefromcountry/{country}", function ($request, $response, $args) {
	$internalPlansController = new InternalPlansFilteredController();
	return($internalPlansController->removeFromCountry($request, $response, $args));
});

//actions to internalPlan : addtocontext

$app->put("/billings/api/internalplans/{internalPlanUuid}/addtocontext/{contextBillingUuid}/{contextCountry}", function ($request, $response, $args) {
	$internalPlansController = new InternalPlansFilteredController();
	return($internalPlansController->addToContext($request, $response, $args));
});

//actions to internalPlan : removefromcontext

$app->put("/billings/api/internalplans/{internalPlanUuid}/removefromcontext/{contextBillingUuid}/{contextCountry}", function ($request, $response, $args) {
	$internalPlansController = new InternalPlansFilteredController();
	return($internalPlansController->removeFromContext($request, $response, $args));
});

//get coupon
	
/*
	sample call :
	
	GET /billings/api/coupons/?providerName=afr&couponCode=prefix-1111&userBillingUuid=UserBillingUUID
	userBillingUuid is not necessary for 'afr', it is mandatory for 'cashway'
	sample answer :
	
	{
	  "status": "done",
	  "statusMessage": "success",
	  "statusCode": 0,
	  "response": {
	    "coupon": {
	      "couponBillingUuid": "11111111-1111-1111-1111-1111111",
	      "code": "prefix-1111",
	      "status": "waiting",
	      "couponsCampaign": {
	        "couponsCampaignBillingUuid": "11111111-1111-1111-1111-1111111",
	        "creationDate": "2016-01-01 00:00:00.00000+01",
	        "name": "campaign_name",
	        "description": "campaign_desc",
	        "provider": {
	          "providerName": "afr"
	        },
	        "internalPlan": {...}
	      },
	      "provider": {
	        "providerName": "afr"
	      },
	      "internalPlan": {..}
	    }
	  }
	}
	
*/

//for backward compatibility - to be removed later -

$app->get("/billings/api/coupons/", function ($request, $response, $args) {
	$internalCouponsController = new InternalCouponsController();
	return($internalCouponsController->get($request, $response, $args));
});

$app->get("/billings/api/internalcoupons/", function ($request, $response, $args) {
	$internalCouponsController = new InternalCouponsController();
	return($internalCouponsController->get($request, $response, $args));
});

//create coupon
	
/*
	sample call :
	
	POST /billings/api/coupons/
	
	BODY :
	
	{
		"userBillingUuid" : "UserBillingUUID",
		"couponsCampaignBillingUuid": "11111111-1111-1111-1111-1111111"
	}
	
*/

//for backward compatibility - to be removed later -

$app->post("/billings/api/coupons/", function ($request, $response, $args) {
	$usersInternalCouponsController = new UsersInternalCouponsController();
	return($usersInternalCouponsController->create($request, $response, $args));
});

$app->post("/billings/api/users/coupons/", function ($request, $response, $args) {
	$usersInternalCouponsController = new UsersInternalCouponsController();
	return($usersInternalCouponsController->create($request, $response, $args));
});

/**
 * GET /billings/api/coupons/list?userBillingUuid=UserBillingUUID
 *
 * Mandatory :
 *  - userBillingUuid=UserBillingUUID
 *
 * Filters :
 *  - campaignUuid=111111-1111-1111-11111111
 */

//for backward compatibility - to be removed later -

$app->get("/billings/api/coupons/list/", function ($request, $response, $args) {
	$usersInternalCouponsController = new UsersInternalCouponsController();
	return($usersInternalCouponsController->getList($request, $response, $args));
});

$app->get("/billings/api/users/coupons/list/", function ($request, $response, $args) {
	$usersInternalCouponsController = new UsersInternalCouponsController();
	return($usersInternalCouponsController->getList($request, $response, $args));
});

//get couponscampaigns
	
/*

	sample call :
	
	GET /billings/api/couponscampaigns/
	
 	sample answer :
	
	{
	 	"status": "done",
	 	"statusMessage": "success",
		"statusCode": 0,
		"response": {
			"couponscampaigns": [
		 		{...},
		 		{...}
		 	]
		 }
	}
	
*/

//for backward compatibility - to be removed later -

$app->get("/billings/api/couponscampaigns/", function ($request, $response, $args) {
	$internalCouponsCampaignsController = new InternalCouponsCampaignsController();
	return($internalCouponsCampaignsController->getMulti($request, $response, $args));
});

$app->get("/billings/api/internalcouponscampaigns/", function ($request, $response, $args) {
	$internalCouponsCampaignsController = new InternalCouponsCampaignsController();
	return($internalCouponsCampaignsController->getMulti($request, $response, $args));
});

//for backward compatibility - to be removed later -

$app->get("/billings/api/couponscampaigns/{couponsCampaignInternalBillingUuid}", function ($request, $response, $args) {
	$internalCouponsCampaignsController = new InternalCouponsCampaignsController();
	return($internalCouponsCampaignsController->getOne($request, $response, $args));
});

$app->get("/billings/api/internalcouponscampaigns/{couponsCampaignInternalBillingUuid}", function ($request, $response, $args) {
	$internalCouponsCampaignsController = new InternalCouponsCampaignsController();
	return($internalCouponsCampaignsController->getOne($request, $response, $args));
});

//actions to internalPlan : addtoprovider

$app->put("/billings/api/internalcouponscampaigns/{couponsCampaignInternalBillingUuid}/addtoprovider/{providerName}", function ($request, $response, $args) {
	$internalCouponsCampaignsController = new InternalCouponsCampaignsController();
	return($internalCouponsCampaignsController->addToProvider($request, $response, $args));
});

//contexts

//get one context
	
/*
	
	sample call :
	
	GET /billings/api/contexts/common/FR
	
	sample answer :
	
	{
		"status": "done",
		"statusMessage": "success",
		"statusCode": 0,
		"response": {
		    "context": {
      			"contextBillingUuid": "common",
      			"contextCountry": "FR",
      			"name": "common",
      			"description": "common",
				"internalPlans": [
	 				{...},
	 				{...}
	 			]
	
			}
		}
	}
	
*/

$app->get("/billings/api/contexts/{contextBillingUuid}/{contextCountry}", function ($request, $response, $args) {
	$contextsController = new ContextsController();
	return($contextsController->getOne($request, $response, $args));
});

//get contexts
	
/*
	
	sample call :
	
	GET /billings/api/contexts/?contextCountry=FR
	
	contextCountry=FR	:	contexts available in a given country (optional)
	
	sample answer :
	
	{
		"status": "done",
		"statusMessage": "success",
		"statusCode": 0,
		"response": {
			"contexts": [
				{...},
				{...}
			]
	
		}
	}
	
*/

$app->get("/billings/api/contexts/", function ($request, $response, $args) {
	$contextsController = new ContextsController();
	return($contextsController->getMulti($request, $response, $args));
});

$app->post("/billings/api/contexts/", function ($request, $response, $args) {
	$contextsController = new ContextsController();
	return($contextsController->create($request, $response, $args));
});

//actions to context : addinternalplan
	
$app->put("/billings/api/contexts/{contextBillingUuid}/{contextCountry}/addinternalplan/{internalPlanUuid}", function ($request, $response, $args) {
	$contextsController = new ContextsController();
	return($contextsController->AddInternalPlanToContext($request, $response, $args));
});
	
//actions to context : removeinternalplan

$app->put("/billings/api/contexts/{contextBillingUuid}/{contextCountry}/removeinternalplan/{internalPlanUuid}", function ($request, $response, $args) {
	$contextsController = new ContextsController();
	return($contextsController->RemoveInternalPlanFromContext($request, $response, $args));
});

//actions to context : moveinternalplan

$app->put("/billings/api/contexts/{contextBillingUuid}/{contextCountry}/moveinternalplan/{internalPlanUuid}/{index}", function ($request, $response, $args) {
	$contextsController = new ContextsController();
	return($contextsController->setInternalPlanIndexInContext($request, $response, $args));
});

//WebHooks

//WebHooks - Recurly

$app->post("/billings/providers/recurly/webhooks/", function ($request, $response, $args) {
	$webHooksController = new WebHooksController();
	return($webHooksController->recurlyWebHooksPosting($request, $response, $args));
});

//WebHooks - Stripe

$app->post("/billings/providers/stripe/webhooks/", function ($request, $response, $args) {
	$webHooksController = new WebHooksController();
	return($webHooksController->stripeWebHooksPosting($request, $response, $args));
});

//WebHooks - Gocardless

$app->post("/billings/providers/gocardless/webhooks/", function ($request, $response, $args) {
	$webHooksController = new WebHooksController();
	return($webHooksController->gocardlessWebHooksPosting($request, $response, $args));
});

//WebHooks - Bachat

$app->post("/billings/providers/bachat/webhooks/", function ($request, $response, $args) {
	$webHooksController = new WebHooksController();
	return($webHooksController->bachatWebHooksPosting($request, $response, $args));
});

//WebHooks - Cashway

$app->post("/billings/providers/cashway/webhooks/", function ($request, $response, $args) {
	$webHooksController = new WebHooksController();
	return($webHooksController->cashwayWebHooksPosting($request, $response, $args));
});

//WebHooks - braintree
	
$app->post("/billings/providers/braintree/webhooks/", function ($request, $response, $args) {
	$webHooksController = new WebHooksController();
	return($webHooksController->braintreeWebHooksPosting($request, $response, $args));
});

//WebHooks - Netsize
	
$app->post("/billings/providers/netsize/webhooks/", function ($request, $response, $args) {
	$webHooksController = new WebHooksController();
	return($webHooksController->netsizeWebHooksPosting($request, $response, $args));
});

$app->run();

?>