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

if(getEnv('BOUYGUES_BILLING_SYSTEM_URL_PREFIX') === false) {
	putEnv('BOUYGUES_BILLING_SYSTEM_URL_PREFIX=https://vod.bouyguestelecom.fr/merchant/');
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

#BOUYGUES (FTP STATS)

if(getEnv('BOUYGUES_FTP_STATS_USER') === false) {
	putEnv('BOUYGUES_FTP_STATS_USER=bouygues');
}

if(getEnv('BOUYGUES_FTP_STATS_PWD') === false) {
	putEnv('BOUYGUES_FTP_STATS_PWD=dXCpHR0OUx');
}

if(getEnv('BOUYGUES_FTP_STATS_HOST') === false) {
	putEnv('BOUYGUES_FTP_STATS_HOST=ftp.afrostream.net');
}

if(getEnv('BOUYGUES_FTP_STATS_PORT') === false) {
	putEnv('BOUYGUES_FTP_STATS_PORT=21');
}

if(getEnv('BOUYGUES_FTP_STATS_PRIVATE_KEY_FILE') === false) {
	putEnv('BOUYGUES_FTP_STATS_PRIVATE_KEY_FILE='.dirname(__FILE__).'/../libs/providers/bouygues/client-sftp-stats/bouygues-sftp-stats-rsa-private.pub');
}

if(getEnv('BOUYGUES_FTP_STATS_PROTOCOL') === false) {
	putEnv('BOUYGUES_FTP_STATS_PROTOCOL=ftp');
}

if(getEnv('BOUYGUES_FTP_STATS_PROXY_ENABLED') === false) {
	putEnv('BOUYGUES_FTP_STATS_PROXY_ENABLED=0');
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

#AWS_FOLDER_CSVS

if(getEnv('AWS_FOLDER_CSVS') === false) {
	putEnv('AWS_FOLDER_CSVS=csvs');
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

#CHARTMOGUL

if(getEnv('CHARTMOGUL_API_ACCOUNT_TOKEN') === false) {
	putEnv('CHARTMOGUL_API_ACCOUNT_TOKEN=2ae202120bda414f28aac7621df6f423');
}

if(getEnv('CHARTMOGUL_API_SECRET_KEY') === false) {
	putEnv('CHARTMOGUL_API_SECRET_KEY=7f7d1e2011fc11860adcfb30492a8e3f');
}

#PLAN_CHANGE

if(getEnv('PLAN_CHANGE_NOTIFY_SENDGRID_TEMPLATE_ID') === false) {
	putEnv('PLAN_CHANGE_NOTIFY_SENDGRID_TEMPLATE_ID=66a8e538-641e-4ebb-9b5a-0b7ab4ae72ba');//SUBSCRIPTION_NOTIFY_PLAN_CHANGE
}

if(getEnv('PLAN_CHANGE_NOTIFY_DAYS_AGO_COUNTER') === false) {
	putEnv('PLAN_CHANGE_NOTIFY_DAYS_AGO_COUNTER=35');
}

if(getEnv('PLAN_CHANGE_NOTIFY_SLEEPING_TIME_IN_MILLIS') === false) {
	putEnv('PLAN_CHANGE_NOTIFY_SLEEPING_TIME_IN_MILLIS=3000');
}

if(getEnv('PLAN_CHANGE_PROCESS_DAYS_AGO_COUNTER') === false) {
	putEnv('PLAN_CHANGE_PROCESS_DAYS_AGO_COUNTER=3');
}

if(getEnv('PLAN_CHANGE_PROCESS_SLEEPING_TIME_IN_MILLIS') === false) {
	putEnv('PLAN_CHANGE_PROCESS_SLEEPING_TIME_IN_MILLIS=3000');
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
	
	private static $db_conns = array();
	
	public static function getDbConn($connection_string_options = NULL, $read_only = false) {
		$connection_string = 'host='.getEnv('AFR_DB_HOST').' port='.getEnv('AFR_DB_PORT').' dbname='.getEnv('AFR_DB_NAME').' user='.getEnv('AFR_DB_USER').' password='.getEnv('AFR_DB_PASSWORD');
		if(isset($connection_string_options)) {
			$connection_string.= ' '.$connection_string_options;
		}
		$db_conn = NULL;
		$key = $connection_string.'-'.$read_only;
		if(key_exists($key, self::$db_conns)) {
			$db_conn = self::$db_conns[$key];
		} else {
			/* NC - keep in mind -
			 * an old connection can be kept by pg_connect.
			 * By forcing PGSQL_CONNECT_FORCE_NEW will create one connection with read-only mode that will not be returned for connections with read-write mode
			 */
			if($read_only == true) {
				$db_conn = pg_connect($connection_string, PGSQL_CONNECT_FORCE_NEW)
				or die('connection to database impossible : '.pg_last_error());
				pg_query($db_conn, "SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY");
			} else {
				$db_conn = pg_connect($connection_string)
				or die('connection to database impossible : '.pg_last_error());
			}
			self::$db_conns[$key] = $db_conn;
		}
		return($db_conn);
	}
	
	public static function getReadOnlyDbConn() {
		$db_conn = self::getDbConn(NULL, true);
		return($db_conn);
	}
	
}

?>