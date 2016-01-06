<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../libs/site/UsersController.php';
require_once __DIR__ . '/../libs/site/SubscriptionsController.php';
require_once __DIR__ . '/../libs/site/InternalPlansController.php';
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

//get a user

/*
	sample call :
	
	GET /billings/api/users/userBillingUuid
	
	or
	
	GET /billings/api/users/?providerName=recurly&userReferenceUuid=afrostreamUUID
    	
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

$app->get("/billings/api/users/{userBillingUuid}", function ($request, $response, $args) {
	$usersController = new UsersController();
	return($usersController->get($request, $response, $args));
});

$app->get("/billings/api/users/", function ($request, $response, $args) {
	$usersController = new UsersController();
	return($usersController->get($request, $response, $args));
});

//create a user

/*
	sample call :
	
	POST GET /billings/api/users/
	
	BODY :
	
	{
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

//get one subscription

/*
	sample call :
	
	GET /billings/api/subscriptions/subscriptionBillingUuid

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
        			"internalPlanOpts": {
        			}
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
        			"internalPlanOpts": {
        			}
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

//update subscriptions

/*
	sample call :
	 
 	{
	 	"userReferenceUuid": "afrostreamUUID"	//our own UUID (database ID)
	}
	
	or :
	
	{
		"userBillingUuid" : "UserBillingUUID"	//given when creating a user
	}
	
	sample answer :
	
	{
  		"status": "done",
  		"statusMessage": "success",
  		"statusCode": 0,
  		"response": {
    		"subscriptions": [
      			{...},
      			{...}
    		]
    	}
    }
    
*/

$app->put("/billings/api/subscriptions/", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->update($request, $response, $args));
});

//get subscriptions

/*
	sample call :
	 
 	{
	 	"userReferenceUuid": "afrostreamUUID"	//our own UUID (database ID)
	}
	
	or :
	
	{
		"userBillingUuid" : "UserBillingUUID"	//given when creating a user
	}
	
	sample answer :
	
	{
  		"status": "done",
  		"statusMessage": "success",
  		"statusCode": 0,
  		"response": {
    		"subscriptions": [
      			{...},
      			{...}
    		]
    	}
    }
    
*/

$app->get("/billings/api/subscriptions/", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->getMulti($request, $response, $args));
});

//InternalPlans

//get one InternalPlan

	/*
	 
	 sample call :
	
	 GET /billings/api/internalplans/internalPlanUuid
	 
	 sample answer :
	 
	{
  		"status": "done",
  		"statusMessage": "success",
  		"statusCode": 0,
  		"response": {
      		"internalPlan": {
        		"internalPlanUuid": "InternalPlanUuid",
        		"name": "InternalPlanName",
        		"description": "InternalPlanDescription",
        		"internalPlanOpts": {
        			"internalMaxScreens" : "2"
        		}
      		}		
    	}
    }
	
	*/

$app->get("/billings/api/internalplans/{internalPlanUuid}", function ($request, $response, $args) {
	$internalPlansController = new InternalPlansController();
	return($internalPlansController->getOne($request, $response, $args));
});

//get InternalPlans

/*
	sample call :
	
	GET /billings/api/internalplans/?providerName=recurly
	
	"providerName" : "recurly" (optional) Retrieve internalplans available only to that provider
	
	sample answer :
	
	{
  		"status": "done",
  		"statusMessage": "success",
  		"statusCode": 0,
  		"response": {
    		"internalPlans": [
      			{...},
      			{...}
    		]
    	}
    }

 */

$app->get("/billings/api/internalplans/", function ($request, $response, $args) {
	$internalPlansController = new InternalPlansController();
	return($internalPlansController->getMulti($request, $response, $args));
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