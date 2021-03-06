<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../libs/db/dbGlobal.php';
require_once __DIR__ . '/../libs/subscriptions/SubscriptionsHandler.php';
require_once __DIR__ . '/../libs/providers/global/requests/CancelSubscriptionRequest.php';

/*
 * Tool to import users from Afrostream DB
 */

print_r("starting tool to cancel afrostream subscriptions from a given plan...\n");

foreach ($argv as $arg) {
	$e=explode("=",$arg);
	if(count($e)==2)
		$_GET[$e[0]]=$e[1];
		else
			$_GET[$e[0]]=0;
}

$platform = BillingPlatformDAO::getPlatformById(1);

$internalPlanUuid = NULL;

if(isset($_GET["-internalPlanUuid"])) {
	$internalPlanUuid = $_GET["-internalPlanUuid"];
} else {
	print_r("-internalPlanUuid is missing\n");
	exit;
}

print_r("using internalPlanUuid=".$internalPlanUuid."\n");

$internalPlan = InternalPlanDAO::getInternalPlanByUuid($internalPlanUuid, $platform->getId());

if($internalPlan == NULL) {
	print_r("internalPlan with internalPlanUuid=".$internalPlanUuid." does not exist\n");
	exit;
}

$loopingSleepTimeInMillis = NULL;

if(isset($_GET["-loopingSleepTimeInMillis"])) {
	$loopingSleepTimeInMillis = $_GET["-loopingSleepTimeInMillis"];
} else {
	print_r("-loopingSleepTimeInMillis is missing\n");
	exit;
}

print_r("using loopingSleepTimeInMillis=".$loopingSleepTimeInMillis."\n");

print_r("processing in 5 secs...\n");

sleep(5);

$query =<<<EOL
SELECT count(*) OVER() as total_hits, 
BU._id as _id, 
(CASE WHEN length(BUO.value) = 0 THEN 'unknown@domain.com' ELSE BUO.value END) as email,
BS.subscription_billing_uuid
FROM billing_subscriptions BS
INNER JOIN
billing_plans BP ON (BP._id = BS.planid)
INNER JOIN
billing_internal_plans BIP ON (BIP._id = BP.internal_plan_id)
INNER JOIN
billing_users BU ON (BS.userid = BU._id)
LEFT JOIN 
billing_users_opts BUO ON (BUO.userid = BU._id AND BUO.key = 'email' AND BUO.deleted = 'no')
WHERE BS.deleted = false 
AND BU.deleted = false 
AND BS.sub_activated_date <= CURRENT_TIMESTAMP
AND (BS.sub_expires_date IS NULL OR BS.sub_expires_date > CURRENT_TIMESTAMP) 
AND BS.sub_canceled_date IS NULL
AND BU.platformid = 1 AND BIP.internal_plan_uuid = '%s'
ORDER BY BU._id ASC
EOL;

$query = sprintf($query, $internalPlanUuid);

$limit = 10000;
$offset = 0;
$index = 1;

do {
	$result = dbGlobal::loadSqlResult(config::getReadOnlyDbConn(), $query, $limit, $offset);
	$offset = $offset + $limit;
	if(is_null($totalHits)) {$totalHits = $result['total_hits'];}
	$idx+= count($result['rows']);
	$lastId = $result['lastId'];
	//
	foreach($result['rows'] as $row) {
		print_r("total=".$totalHits.",current=".$index.",email=".$row['email'].",subscription_billing_uuid=".$row['subscription_billing_uuid'].",processing\n");
		//
		try {
			$subscriptionsHandler = new SubscriptionsHandler();
			$cancelSubscriptionRequest = new CancelSubscriptionRequest();
			$cancelSubscriptionRequest->setPlatform($platform);
			$cancelSubscriptionRequest->setSubscriptionBillingUuid($row['subscription_billing_uuid']);
			$cancelSubscriptionRequest->setOrigin('script');
			$cancelSubscriptionRequest->setCancelDate(new DateTime());
			//
			$subscriptionsHandler->doCancelSubscription($cancelSubscriptionRequest);
			//
			print_r("total=".$totalHits.",current=".$index.",email=".$row['email'].",subscription_billing_uuid=".$row['subscription_billing_uuid'].",done\n");
		} catch(Exception $e) {
			print_r("total=".$totalHits.",current=".$index.",email=".$row['email'].",subscription_billing_uuid=".$row['subscription_billing_uuid'].",failed,message=".$e->getMessage()."\n");
		}
		//
		usleep($loopingSleepTimeInMillis * 1000);
		//
		$index++;
		//
	}
} while ($idx < $totalHits && count($result['rows']) > 0);

print_r("processing done\n");

?>