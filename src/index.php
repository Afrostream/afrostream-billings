<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../libs/site/UsersController.php';
require_once __DIR__ . '/../libs/site/SubscriptionsController.php';
require_once __DIR__ . '/../libs/site/InternalPlansFilteredController.php';
require_once __DIR__ . '/../libs/site/CouponsController.php';
require_once __DIR__ . '/../libs/site/WebHooksController.php';
require_once __DIR__ . '/../libs/site/CouponsCampaignsController.php';
require_once __DIR__ . '/../libs/site/ContextsController.php';
//require_once __DIR__ . '/test.php';

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
	$newResponse = $next($req, $res);
	return($newResponse);
});

//API BASIC AUTH ACTIVATION

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
		"path" => "/billings/api",
		"secure" => getEnv('API_HTTP_SECURE') == 1 ? true : false,
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
	
	POST /billings/api/users/
	
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

//update : one specific userBillingUuid (not recommended)
	
$app->put("/billings/api/users/{userBillingUuid}", function ($request, $response, $args) {
	$usersController = new UsersController();
	return($usersController->update($request, $response, $args));
});

//update : email, firstName, lastName linked to the same userReferenceUuid. Changes are propagated to providers.

/*
	sample call :
	
	PUT /billings/api/users/?userReferenceUuid=afrostreamUUID
	
	BODY :
	
	{
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
    		"users": [
    			{...},
    			{...}
    		]
  		}
  	}
	
*/

$app->put("/billings/api/users/", function ($request, $response, $args) {
	$usersController = new UsersController();
	return($usersController->updateUsers($request, $response, $args));
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
	      			"name": "name",
	      			"description": "description",
	      			"amount_in_cents": "1000",
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
					"isVisible": true
      			},
      			"creationDate": "2015-12-25 12:00:00+00",
      			"updatedDate": "2015-12-25 12:00:00+00",
      			"subStatus": "active",
      			"subActivatedDate": "2015-12-25 12:00:00+00",
      			"subCanceledDate": null,
      			"subExpiresDate": null,
      			"subPeriodStartedDate": "2015-12-25 12:00:00+00",
      			"subPeriodEndsDate": "2016-01-25 12:00:00+00",
      			"subOpts": {
        			"key1": "value1",
        			"key2": "value2",
        			"key3": "value3"
      			}
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
    	},
    	"subOpts": {
        	"key1": "value1",
        	"key2": "value2",
        	"key3": "value3"
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
      			"inTrial" : "no",
      			"isCancellable" : "yes",
      			"isReactivable" : "no",
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
	      			"name": "name",
	      			"description": "description",
	      			"amount_in_cents": "1000",
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
					"isVisible": true
      			},
      			"creationDate": "2015-12-25 12:00:00+00",
      			"updatedDate": "2015-12-25 12:00:00+00",
      			"subStatus": "active",
      			"subActivatedDate": "2015-12-25 12:00:00+00",
      			"subCanceledDate": null,
      			"subExpiresDate": null,
      			"subPeriodStartedDate": "2015-12-25 12:00:00+00",
      			"subPeriodEndsDate": "2016-01-25 12:00:00+00",
      			"subOpts": {
        			"key1": "value1",
        			"key2": "value2",
        			"key3": "value3"
      			}
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

//cancel a subscription

$app->put("/billings/api/subscriptions/{subscriptionBillingUuid}/cancel", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->cancel($request, $response, $args));
});

//renew a subscription

$app->put("/billings/api/subscriptions/{subscriptionBillingUuid}/renew", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->renew($request, $response, $args));
});

//reactivate a subscription
	
$app->put("/billings/api/subscriptions/{subscriptionBillingUuid}/reactivate", function ($request, $response, $args) {
	$subscriptionsController = new SubscriptionsController();
	return($subscriptionsController->reactivate($request, $response, $args));
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
      			"name": "name",
      			"description": "description",
      			"amount_in_cents": "1000",
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
				"isVisible": true
      		}		
    	}
    }
	
*/

$app->get("/billings/api/internalplans/{internalPlanUuid}", function ($request, $response, $args) {
	$internalPlansController = new InternalPlansFilteredController();
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
		"amount_in_cents" : "1000",					//10 Euros
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
      			"amount_in_cents": "1000",
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

$app->get("/billings/api/coupons/", function ($request, $response, $args) {
	$couponsController = new CouponsController();
	return($couponsController->get($request, $response, $args));
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

$app->post("/billings/api/coupons/", function ($request, $response, $args) {
	$couponsController = new CouponsController();
	return($couponsController->create($request, $response, $args));
});

//get InternalPlans
	
/*

	sample call :
	
	GET /billings/api/couponscampaigns/?providerName=cashway

 	"providerName" : "cashway" (optional) Retrieve internalplans available only to that provider
	
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

$app->get("/billings/api/couponscampaigns/", function ($request, $response, $args) {
	$couponsCampaignsController = new CouponsCampaignsController();
	return($couponsCampaignsController->getMulti($request, $response, $args));
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

//Testing purpose

/*$app->get("/billings/api/test/", function ($request, $response, $args) {
	testMe();
});*/

$app->run();

?>