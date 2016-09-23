<?php

require_once __DIR__ . '/../../vendor/autoload.php';

date_default_timezone_set("Europe/Paris");

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

if(getEnv('BILLINGS_ENV') === false) {
	putEnv('BILLINGS_ENV=staging');
}

#DATABASE

if(getEnv('AFR_DB_HOST') === false) {
	putEnv('AFR_DB_HOST=ec2-54-228-194-210.eu-west-1.compute.amazonaws.com');
}

if(getEnv('AFR_DB_PORT') == false) {
	putEnv('AFR_DB_PORT=5522');
}

if(getEnv('AFR_DB_NAME') === false) {
	putEnv('AFR_DB_NAME=d71on7act83b7i');
}

if(getEnv('AFR_DB_USER') === false) {
	putEnv('AFR_DB_USER=u4fp4ad34q8qvi');
}

if(getEnv('AFR_DB_PASSWORD') === false) {
	putEnv('AFR_DB_PASSWORD=pt7eht3e9v3lnehhh27m7sfeol');
}

#BOUYGUES

if(getEnv('BOUYGUES_MERCHANTID') === false) {
	putEnv('BOUYGUES_MERCHANTID=0');
}

if(getEnv('BOUYGUES_SERVICEID') === false) {
	putEnv('BOUYGUES_SERVICEID=0');
}

if(getEnv('BOUYGUES_BILLING_SYSTEM_URL') === false) {
	putEnv('BOUYGUES_BILLING_SYSTEM_URL=https://vod.bouyguestelecom.fr/merchant/'.getEnv('BOUYGUES_MERCHANTID').'_'.getEnv('BOUYGUES_SERVICEID'));
}

if(getEnv('BOUYGUES_BILLING_SYSTEM_HTTP_AUTH_USER') === false) {
	putEnv('BOUYGUES_BILLING_SYSTEM_HTTP_AUTH_USER=admin');
}

if(getEnv('BOUYGUES_BILLING_SYSTEM_HTTP_AUTH_PWD') === false) {
	putEnv('BOUYGUES_BILLING_SYSTEM_HTTP_AUTH_PWD=pwd');
}

if(getEnv('BOUYGUES_PROXY_HOST') === false) {
	putEnv('BOUYGUES_PROXY_HOST=');
}

if(getEnv('BOUYGUES_PROXY_PORT') === false) {
	putEnv('BOUYGUES_PROXY_PORT=8080');
}

if(getEnv('BOUYGUES_PROXY_USER') === false) {
	putEnv('BOUYGUES_PROXY_USER=');
}

if(getEnv('BOUYGUES_PROXY_PWD') === false) {
	putEnv('BOUYGUES_PROXY_PWD=');
}

if(getEnv('BOUYGUES_STORE_LAST_TIME_HOUR') === false) {
	putEnv('BOUYGUES_STORE_LAST_TIME_HOUR=0');
}

if(getEnv('BOUYGUES_STORE_LAST_TIME_MINUTE') === false) {
	putEnv('BOUYGUES_STORE_LAST_TIME_MINUTE=25');
}

#PAYPAL

if(getEnv('PAYPAL_API_CLIENT_ID') === false) {
	putEnv('PAYPAL_API_CLIENT_ID=');
}

if(getEnv('PAYPAL_API_SECRET') === false) {
	putEnv('PAYPAL_API_SECRET=');
}

#AMAZON

#AWS_ACCESS_KEY_ID

if(getEnv('AWS_ACCESS_KEY_ID') === false) {
	putEnv('AWS_ACCESS_KEY_ID=');
}

#AWS_SECRET_ACCESS_KEY

if(getEnv('AWS_SECRET_ACCESS_KEY') === false) {
	putEnv('AWS_SECRET_ACCESS_KEY=');
}

#AWS_ENV ( 'staging' / 'production' )

if(getEnv('AWS_ENV') === false) {
	putEnv('AWS_ENV=staging');
}

#AWS_BUCKET_BILLINGS

if(getEnv('AWS_BUCKET_BILLINGS_EXPORTS') === false) {
	putEnv('AWS_BUCKET_BILLINGS_EXPORTS=afrostream-exports-billings');
}

#AWS_FOLDER_TRANSACTIONS

if(getEnv('AWS_FOLDER_TRANSACTIONS') === false) {
	putEnv('AWS_FOLDER_TRANSACTIONS=transactions');
}

#AWS_FOLDER_SUBSCRIPTIONS

if(getEnv('AWS_FOLDER_SUBSCRIPTIONS') === false) {
	putEnv('AWS_FOLDER_SUBSCRIPTIONS=subscriptions');
}

#AWS_REGION

if(getEnv('AWS_REGION') === false) {
	putEnv('AWS_REGION=eu-central-1');
}

#AWS_VERSION

if(getEnv('AWS_VERSION') === false) {
	putEnv('AWS_VERSION=latest');
}

#TRANSACTIONS EXPORTS

//EMAIL

if(getEnv('EXPORTS_DAILY_EMAIL_ACTIVATED') === false) {
	putEnv('EXPORTS_DAILY_EMAIL_ACTIVATED=1');
}

if(getEnv('EXPORTS_MONTHLY_EMAIL_ACTIVATED') === false) {
	putEnv('EXPORTS_MONTHLY_EMAIL_ACTIVATED=1');
}

//EMAIL FROM (COMMON)

if(getEnv('EXPORTS_EMAIL_FROM') === false) {
	putEnv('EXPORTS_EMAIL_FROM=exports@afrostream.tv');
}

if(getEnv('EXPORTS_EMAIL_FROMNAME') === false) {
	putEnv('EXPORTS_EMAIL_FROMNAME=Afrostream Export');
}

//EMAIL TRANSACTIONS TOS

if(getEnv('EXPORTS_TRANSACTIONS_DAILY_EMAIL_TOS') === false) {
	putEnv('EXPORTS_TRANSACTIONS_DAILY_EMAIL_TOS=exports@afrostream.tv');
}

if(getEnv('EXPORTS_TRANSACTIONS_MONTHLY_EMAIL_TOS') === false) {
	putEnv('EXPORTS_TRANSACTIONS_MONTHLY_EMAIL_TOS=exports@afrostream.tv');
}

//EMAIL SOUSCRIPTIONS TOS

if(getEnv('EXPORTS_SUBSCRIPTIONS_DAILY_EMAIL_TOS') === false) {
	putEnv('EXPORTS_SUBSCRIPTIONS_DAILY_EMAIL_TOS=exports@afrostream.tv');
}

if(getEnv('EXPORTS_SUBSCRIPTIONS_MONTHLY_EMAIL_TOS') === false) {
	putEnv('EXPORTS_SUBSCRIPTIONS_MONTHLY_EMAIL_TOS=exports@afrostream.tv');
}

//EMAIL TRANSACTIONS BCCS

if(getEnv('EXPORTS_TRANSACTIONS_DAILY_EMAIL_BCCS') === false) {
	putEnv('EXPORTS_TRANSACTIONS_DAILY_EMAIL_BCCS=');
}

if(getEnv('EXPORTS_TRANSACTIONS_MONTHLY_EMAIL_BCCS') === false) {
	putEnv('EXPORTS_TRANSACTIONS_MONTHLY_EMAIL_BCCS=');
}

//EMAIL SUBSCRIPTION BCCS

if(getEnv('EXPORTS_SUBSCRIPTIONS_DAILY_EMAIL_BCCS') === false) {
	putEnv('EXPORTS_SUBSCRIPTIONS_DAILY_EMAIL_BCCS=');
}

if(getEnv('EXPORTS_SUBSCRIPTIONS_MONTHLY_EMAIL_BCCS') === false) {
	putEnv('EXPORTS_SUBSCRIPTIONS_MONTHLY_EMAIL_BCCS=');
}

if(getEnv('EXPORTS_DAILY_NUMBER_OF_DAYS') === false) {
	putEnv('EXPORTS_DAILY_NUMBER_OF_DAYS=31');
}

if(getEnv('EXPORTS_MONTHLY_FIRST_DAY_OF_MONTH') === false) {
	putEnv('EXPORTS_MONTHLY_FIRST_DAY_OF_MONTH=5');
}

if(getEnv('EXPORTS_MONTHLY_NUMBER_OF_MONTHS') === false) {
	putEnv('EXPORTS_MONTHLY_NUMBER_OF_MONTHS=1');
}

#logger, #db_conn, ...

class ScriptsConfig {
	
	public static $timezone = "Europe/Paris";

	private static $logger;

	public static function getLogger() {
		if(self::$logger == NULL) {
			self::$logger = new Logger('afrostream-billings-scripts');
			self::$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));
		}
		return(self::$logger);
	}

	private static $db_conn;

	public static function getDbConn() {
		if(self::$db_conn == null) {
			$connection_string = 'host='.getEnv('AFR_DB_HOST').' port='.getEnv('AFR_DB_PORT').' dbname='.getEnv('AFR_DB_NAME').' user='.getEnv('AFR_DB_USER').' password='.getEnv('AFR_DB_PASSWORD');
			self::$db_conn = pg_connect($connection_string)
			or die('connection to database impossible : '.pg_last_error());
		}
		return(self::$db_conn);
	}
}

?>