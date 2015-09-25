<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

#Database

define('DBHOST', 'localhost');
define('DBPORT', '5432');
define('DBNAME', 'afrostream');
define('DBUSER', 'neo');
define('DBPASSWORD', 'toto');

#Recurly API
define('RECURLY_API_SUBDOMAIN','johnarch');
define('RECURLY_API_KEY', '67dbb29f0dbe4e219bc247a3b5387652');
#Recurly WebHooks
define('RECURLY_WH_HTTP_AUTH_USER', 'admin');
define('RECURLY_WH_HTTP_AUTH_PWD', 'pwd');

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
			$connection_string = 'host='.DBHOST.' port='.DBPORT.' dbname='.DBNAME.' user='.DBUSER.' password='.DBPASSWORD;
			self::$db_conn = pg_connect($connection_string)
				or die('connection to database impossible : '.pg_last_error());
		}
		return(self::$db_conn);
	}
}

?>