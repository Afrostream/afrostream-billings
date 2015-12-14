<?php

date_default_timezone_set("Europe/Paris");

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

#Database

if(getEnv('DBHOST') === false) {
	putEnv('DBHOST=localhost');
}

if(getEnv('DBPORT') == false) {
	putEnv('DBPORT=5432');
}

if((getEnv('DBNAME') === false)) {
	putEnv('DBNAME=afr-billings-local');
}

if((getEnv('DBUSER') === false)) {
	putEnv('DBUSER=postgres');
}

if((getEnv('DBPASSWORD') === false)) {
	putEnv('DBPASSWORD=password');
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

#Logs
#

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
			$connection_string = 'host='.getEnv('DBHOST').' port='.getEnv('DBPORT').' dbname='.getEnv('DBNAME').' user='.getEnv('DBUSER').' password='.getEnv('DBPASSWORD');
			self::$db_conn = pg_connect($connection_string)
				or die('connection to database impossible : '.pg_last_error());
		}
		return(self::$db_conn);
	}
}

?>