<?php

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set("Europe/Paris");

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

#Database

#DATABASE_URL is filled in by heroku

if(getEnv('DB_HOST') === false) {
	putEnv('DB_HOST=localhost');
}

if(getEnv('DB_PORT') == false) {
	putEnv('DB_PORT=5432');
}

if((getEnv('DB_NAME') === false)) {
	putEnv('DB_NAME=afr-billings-local');
}

if((getEnv('DB_USER') === false)) {
	putEnv('DB_USER=postgres');
}

if((getEnv('DB_PASSWORD') === false)) {
	putEnv('DB_PASSWORD=password');
}

#Billings API
if((getEnv('API_HTTP_AUTH_USER') === false)) {
	putEnv('API_HTTP_AUTH_USER=admin');
}

if((getEnv('API_HTTP_AUTH_PWD') === false)) {
	putEnv('API_HTTP_AUTH_PWD=pwd');
}

if((getEnv('API_HTTP_SECURE') === false)) {
	putEnv('API_HTTP_SECURE=true');
}

#Recurly API
if((getEnv('RECURLY_API_SUBDOMAIN') === false)) {
	putEnv('RECURLY_API_SUBDOMAIN=johnarch');
}

if((getEnv('RECURLY_API_KEY') === false)) {
	putEnv('RECURLY_API_KEY=67dbb29f0dbe4e219bc247a3b5387652');
}

#Recurly WebHooks
if((getEnv('RECURLY_WH_HTTP_AUTH_USER') === false)) {
	putEnv('RECURLY_WH_HTTP_AUTH_USER=admin');
}
if((getEnv('RECURLY_WH_HTTP_AUTH_PWD') === false)) {
	putEnv('RECURLY_WH_HTTP_AUTH_PWD=pwd');
}

#Gocardless API
if((getEnv('GOCARDLESS_API_ENV') === false)) {
	putEnv('GOCARDLESS_API_ENV=sandbox');
}

if((getEnv('GOCARDLESS_API_KEY') === false)) {
	putEnv('GOCARDLESS_API_KEY=YXwxcLeTGwGv3sPd1-CpmNh3nAsqtDfzGuV8_Vji');
}

if((getEnv('GOCARDLESS_WH_SECRET') === false)) {
	putEnv('GOCARDLESS_WH_SECRET=nelsounet');
}

#logger, #db_conn, ...

class config {
	
	private static $logger;
	
	public static function getLogger() {
		if(self::$logger == NULL) {
			self::$logger = new Logger('afrostream-billings');
			self::$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));
		}
		return(self::$logger);
	}
	
	private static $db_conn;
	
	public static function getDbConn() {
		if(self::$db_conn == null) {
			$connection_string = NULL;
			if(getEnv('DATABASE_URL') === false) {
				$connection_string = 'host='.getEnv('DB_HOST').' port='.getEnv('DB_PORT').' dbname='.getEnv('DB_NAME').' user='.getEnv('DB_USER').' password='.getEnv('DB_PASSWORD');
			} else {
				$connection_string = getEnv('DATABASE_URL');
			}
			self::$db_conn = pg_connect($connection_string)
				or die('connection to database impossible : '.pg_last_error());
		}
		return(self::$db_conn);
	}
}

?>