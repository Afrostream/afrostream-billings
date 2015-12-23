<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../libs/site/UsersController.php';
require_once __DIR__ . '/../libs/site/SubscriptionsController.php';
require_once __DIR__ . '/../libs/site/WebHooksController.php';

$app = new \Slim\App();

//API BASIC AUTH ACTIVATION

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
		"path" => "/billings/api",
		"users" => [
				getEnv('API_HTTP_AUTH_USER') => getEnv('API_HTTP_AUTH_PWD')
		]
]));

//Users

//create

/*
 * 	sample call :
 * 
 * 	{
    	"providerName" : "recurly",
    	"userReferenceUuid" : "afrostreamUUID",	//our own UUID (database ID)
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