<?php

require_once __DIR__ . '/../../vendor/autoload.php';

date_default_timezone_set("Europe/Paris");

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

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

if(getEnv('BOUYGUES_STORE_LAST_TIME_HOUR' === false)) {
	putEnv('BOUYGUES_STORE_LAST_TIME_HOUR=0');
}

if(getEnv('BOUYGUES_STORE_LAST_TIME_MINUTE' === false)) {
	putEnv('BOUYGUES_STORE_LAST_TIME_MINUTE=25');
}

#logger, #db_conn, ...

class ScriptsConfig {

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