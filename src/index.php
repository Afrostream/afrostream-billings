<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../libs/site/UsersController.php';
require_once __DIR__ . '/../libs/site/SubscriptionsController.php';
require_once __DIR__ . '/../libs/site/WebHooksController.php';

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

//API BASIC AUTH ACTIVATION

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
		"path" => "/billings/api",
		"secure" => (getEnv('API_HTTP_SECURE') === 'true' ? true : false),
		"users" => [
				getEnv('API_HTTP_AUTH_USER') => getEnv('API_HTTP_AUTH_PWD')
		]
]));

//Users

//get

/*
 * sample call :
 * 
 * ?providerName=recurly&userReferenceUuid=afrostreamUUID
    	
    "providerName" : "recurly"
    "userReferenceUuid" : "afrostreamUUID"	//our own UUID (database ID)

    sample answer :
    
    {
  		"status": "done",
  		"statusMessage": "success",
  		"statusCode": 0,
  		"response": {
    		"user": {
      			"userBillingUuid": "UserBillingUUID",	//User Billings UUID (to be used in other calls)
      			"userReferenceUuid": "afrostreamUUID",
      			"userProviderUuid": "UserProviderUUID",
      			"provider": {
        			"providerName": "recurly"
      			},
      			"userOpts": {
        			"email": "email@domain.com",
        			"firstName": "myFirstName",
        			"lastName": "myLastName"
      			}
    		}
  		}
  	}

 */

$app->get("/billings/api/users/", function ($request, $response, $args) {
	$usersController = new UsersController();
	return($usersController->get($request, $response, $args));
});

//create

/*
 * 	sample call :
 * 
 * 	{
    	"providerName" : "recurly",
    	"userReferenceUuid" : "afrostreamUUID",		//our own UUID (database ID)
    	"userProviderUuid" : "UserProviderUUID",	//given by the provider when user is created from provider side
    	"userOpts" : {
        	"email" : "email@domain.com",
        	"firstName" : "myFirstName",
        	"lastName" : "myLastName"
    	}
    }
    
    sample answer :
    
    {
  		"status": "done",
  		"statusMessage": "success",
  		"statusCode": 0,
  		"response": {
    		"user": {
      			"userBillingUuid": "UserBillingUUID",	//User Billings UUID (to be used in other calls)
      			"userReferenceUuid": "afrostreamUUID",
      			"userProviderUuid": "UserProviderUUID",
      			"provider": {
        			"providerName": "recurly"
      			},
      			"userOpts": {
        			"email": "email@domain.com",
        			"firstName": "myFirstName",
        			"lastName": "myLastName"
      			}
    		}
  		}
  	}

 */

$app->post("/billings/api/users/", function ($request, $response, $args) {
	$usersController = new UsersController();
	return($usersController->create($request, $response, $args));
});


//Subscriptions

//create

/*
 * 
 * sample call :
 * 
 *	{
    	"userBillingUuid" : "UserBillingUUID",				//given when creating a user
    	"internalPlanUuid" : "InternalPlanUuid",			//Plan (internal name)	 
    	"subscriptionProviderUuid" : "SubscriptionProviderUUID",//given by the provider when subscription is created from provider side
    	"billingInfoOpts" : {								//nothing for now
    	}
	}
 
 	sample answer :
 	
	{
  		"status": "done",
  		"statusMessage": "success",
  		"statusCode": 0,
  		"response": {
    		"subscription": {
      			"subscriptionBillingUuid": "SubscriptionBillingUUID",
      			"subscriptionProviderUuid": "SubscriptionProviderUUID",
      			"isActive": "yes",
      			"user": {
        			"userBillingUuid": "UserBillingUUID",
        			"userReferenceUuid": "afrostreamUUID",
        			"userProviderUuid": "UserProviderUUID",
        			"provider": {
          				"providerName": "recurly"
        			},
        			"userOpts": {
          				"email": "email@domain.com",
          				"firstName": "myFirstName",
          				"lastName": "myLastName"
        			}
      			},
      			"provider": {
        			"providerName": "recurly"
      			},
      			"internalPlan": {
        			"internalPlanUuid": "InternalPlanUuid",
        			"name": "InternalPlanName",
        			"description": "InternalPlanDescription",
        			"internalPlanOpts": []
      			},
      			"creationDate": "2015-12-25 12:00:00+00",
      			"updatedDate": "2015-12-25 12:00:00+00",
      			"subStatus": "active",
      			"subActivatedDate": "2015-12-25 12:00:00+00",
      			"subCanceledDate": null,
      			"subExpiresDate": null,
      			"subPeriodStartedDate": "2015-12-25 12:00:00+00",
      			"subPeriodEndsDate": "2016-01-25 12:00:00+00"
    		}
  		}
	}
 
 */

$app->post("/billings/api/subscriptions/", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->create($request, $response, $args));
});

//update

/*
*
* sample call :
* 
* 
* 
* 	{
	 	"userReferenceUuid": "afrostreamUUID"	//our own UUID (database ID)
	}
	
	or :
	
	{
		"userBillingUuid" : "UserBillingUUID"	//given when creating a user
	}
	
	sample answer :
	
	//TODO
*/

$app->put("/billings/api/subscriptions/", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->update($request, $response, $args));
});

//WebHooks

//WebHooks - Recurly

$app->post("/billings/providers/recurly/webhooks/", function ($request, $response, $args) {
	$webHooksController = new WebHooksController();
	return($webHooksController->recurlyWebHooksPosting($request, $response, $args));
});

//WebHooks - Gocardless

$app->post("/billings/providers/gocardless/webhooks/", function ($request, $response, $args) {
	$webHooksController = new WebHooksController();
	return($webHooksController->gocardlessWebHooksPosting($request, $response, $args));
});

$app->run();

?>